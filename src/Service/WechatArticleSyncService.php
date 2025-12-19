<?php

namespace App\Service;

use App\Entity\Official;
use App\Entity\WechatPublicAccount;
use App\Repository\OfficialRepository;
use App\Repository\WechatPublicAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Monolog\Logger;

class WechatArticleSyncService
{
    private LoggerInterface $logger;

    public function __construct(
        private WechatApiService $wechatApiService,
        private OfficialRepository $officialRepository,
        private WechatPublicAccountRepository $wechatPublicAccountRepository,
        private EntityManagerInterface $entityManager,
        private MediaResourceProcessor $mediaResourceProcessor,
        private ResourceExtractor $resourceExtractor,
        LoggerInterface $logger
    ) {
        // 使用专用的微信日志通道
        if ($logger instanceof Logger) {
            $this->logger = $logger->withName('wechat');
        } else {
            $this->logger = $logger;
        }
    }

    /**
     * 同步已发布文章
     *
     * @param string $publicAccountId 公众号ID
     * @param array $options 选项参数
     * @return array 同步结果
     */
    public function syncPublishedArticles(string $publicAccountId, array $options = []): array
    {
        $result = [
            'success' => false,
            'total_processed' => 0,
            'new_articles' => 0,
            'updated_articles' => 0,
            'errors' => [],
            'message' => ''
        ];

        try {
            $this->logger->info('开始同步微信文章', ['publicAccountId' => $publicAccountId]);

            // 获取公众号信息
            $publicAccount = $this->wechatPublicAccountRepository->find($publicAccountId);
            if (!$publicAccount) {
                $result['errors'][] = '公众号不存在: ' . $publicAccountId;
                $result['message'] = '公众号不存在';
                return $result;
            }

            // 获取微信访问令牌
            $accessToken = $this->wechatApiService->getAccessToken($publicAccount);
            if (!$accessToken) {
                $result['errors'][] = '获取微信访问令牌失败';
                $result['message'] = '获取访问令牌失败';
                return $result;
            }

            // 解析选项参数
            $batchSize = $options['batch_size'] ?? 20;
            $noContent = $options['no_content'] ?? 0;
            $beginDate = $options['begin_date'] ?? 0;
            $endDate = $options['end_date'] ?? 0;
            $maxArticles = $options['max_articles'] ?? null;
            $processMedia = $options['process_media'] ?? true; // 默认启用媒体处理

            $this->logger->info('开始获取已发布文章', [
                'batchSize' => $batchSize,
                'noContent' => $noContent,
                'beginDate' => $beginDate,
                'endDate' => $endDate,
                'maxArticles' => $maxArticles,
                'processMedia' => $processMedia
            ]);

            // 获取所有已发布文章
            $publishedItems = $this->wechatApiService->getAllPublishedArticles(
                $accessToken,
                $batchSize,
                $noContent,
                $beginDate,
                $endDate
            );

            if (empty($publishedItems)) {
                $result['success'] = true;
                $result['message'] = '没有找到已发布文章';
                $this->logger->info('没有找到已发布文章');
                return $result;
            }

            // 提取文章数据
            $articlesData = $this->wechatApiService->extractAllPublishedArticles($publishedItems);

            // 限制最大文章数量
            if ($maxArticles && count($articlesData) > $maxArticles) {
                $articlesData = array_slice($articlesData, 0, $maxArticles);
                $this->logger->info('限制最大文章数量', ['maxArticles' => $maxArticles]);
            }

            $this->logger->info('获取到文章数据', ['count' => count($articlesData)]);

            // 处理文章数据
            $articles = [];
            $errors = [];

            foreach ($articlesData as $articleData) {
                try {
                    $article = $this->processArticleData($articleData, $publicAccountId, $processMedia);
                    if ($article) {
                        $articles[] = $article;
                    }
                } catch (\Exception $e) {
                    $errorMsg = '处理文章数据失败: ' . $e->getMessage();
                    $errors[] = $errorMsg;
                    $this->logger->error($errorMsg, [
                        'articleData' => $articleData,
                        'exception' => $e
                    ]);
                }
            }

            $result['total_processed'] = count($articlesData);

            if (empty($articles)) {
                $result['success'] = true;
                $result['message'] = '没有有效的文章数据';
                $result['errors'] = array_merge($result['errors'], $errors);
                return $result;
            }

            // 保存文章
            $saveResult = $this->saveArticles($articles);

            $result['new_articles'] = $saveResult['new_count'];
            $result['updated_articles'] = $saveResult['updated_count'];
            $result['errors'] = array_merge($result['errors'], $errors, $saveResult['errors']);

            if (empty($result['errors'])) {
                $result['success'] = true;
                $result['message'] = sprintf(
                    '同步完成，新增 %d 篇，更新 %d 篇',
                    $result['new_articles'],
                    $result['updated_articles']
                );
            } else {
                $result['message'] = '同步完成，但存在错误';
            }

            $this->logger->info('微信文章同步完成', $result);

        } catch (\Exception $e) {
            $errorMsg = '同步微信文章失败: ' . $e->getMessage();
            $result['errors'][] = $errorMsg;
            $result['message'] = $errorMsg;

            $this->logger->error($errorMsg, [
                'publicAccountId' => $publicAccountId,
                'exception' => $e
            ]);
        }

        return $result;
    }

    /**
     * 处理文章数据，转换为Official实体
     *
     * @param array $articleData 微信API返回的文章数据
     * @param string $publicAccountId 公众号ID
     * @param bool $processMedia 是否处理媒体资源
     * @return Official|null
     */
    private function processArticleData(array $articleData, string $publicAccountId, bool $processMedia = true): ?Official
    {
        try {
            // 验证必要字段
            if (empty($articleData['article_id']) || empty($articleData['title'])) {
                $this->logger->warning('文章数据缺少必要字段', ['articleData' => $articleData]);
                return null;
            }

            $articleId = $articleData['article_id'];

            // 检查文章是否已存在
            $existingArticle = $this->officialRepository->findByArticleId($articleId);

            if ($existingArticle) {
                // 更新现有文章
                $article = $existingArticle;
                $this->logger->debug('更新现有文章', ['articleId' => $articleId]);
            } else {
                // 创建新文章
                $article = new Official();
                $article->setArticleId($articleId);
                $article->setWechatAccountId($publicAccountId);
                $this->logger->debug('创建新文章', ['articleId' => $articleId]);
            }

            // 设置基本信息
            $article->setTitle($articleData['title'] ?? '');
            $article->setAuthor($articleData['author'] ?? null);
            $article->setDigest($articleData['digest'] ?? null);

            // 处理内容和媒体资源
            $content = $articleData['content'] ?? '';
            $thumbUrl = $articleData['thumb_url'] ?? null;

            if ($processMedia) {
                // 处理媒体资源
                $mediaResult = $this->mediaResourceProcessor->processArticleMedia($content, $thumbUrl);

                // 更新内容和缩略图URL
                $content = $mediaResult['content'];
                $thumbUrl = $mediaResult['thumb_url'];

                // 记录媒体处理结果
                if (!empty($mediaResult['errors'])) {
                    $this->logger->warning('媒体资源处理存在错误', [
                        'articleId' => $articleId,
                        'errors' => $mediaResult['errors']
                    ]);
                }

                if (!empty($mediaResult['processed_resources'])) {
                    $this->logger->info('媒体资源处理成功', [
                        'articleId' => $articleId,
                        'processed_count' => count($mediaResult['processed_resources'])
                    ]);
                }
            }

            if (empty($content)) {
                $content = '<p>暂无内容</p>';
            }
            $article->setContent($content);

            // 设置URL信息
            $article->setOriginalUrl($articleData['url'] ?? '');

            // 设置缩略图信息
            $article->setThumbMediaId($articleData['thumb_media_id'] ?? null);
            $article->setThumbUrl($thumbUrl);

            // 设置显示选项
            $article->setShowCoverPic($articleData['show_cover_pic'] ?? 0);
            $article->setNeedOpenComment($articleData['need_open_comment'] ?? 0);

            // DEBUG: 添加调试日志验证问题
            $this->logger->debug('处理文章数据', [
                'articleId' => $articleId,
                'title' => $articleData['title'] ?? '',
                'hasUpdateTime' => isset($articleData['update_time']),
                'update_time_value' => $articleData['update_time'] ?? 'not_set'
            ]);

            // 设置发布时间到 releaseTime 字段 - 修复后的时间处理逻辑
            $releaseTime = null;
            $timeSource = '';

            // 优先级1: 使用微信API的 publish_time
            if (isset($articleData['publish_time']) && !empty($articleData['publish_time'])) {
                $releaseTime = \DateTime::createFromFormat('U', $articleData['publish_time']);
                if ($releaseTime) {
                    $timeSource = 'publish_time';
                    $this->logger->debug('使用发布时间', ['source' => 'publish_time', 'timestamp' => $articleData['publish_time']]);
                } else {
                    $this->logger->warning('创建发布时间DateTime失败', ['publish_time' => $articleData['publish_time']]);
                }
            }

            // 优先级2: 使用 update_time 作为备选
            if ($releaseTime === null && isset($articleData['update_time']) && !empty($articleData['update_time'])) {
                $releaseTime = \DateTime::createFromFormat('U', $articleData['update_time']);
                if ($releaseTime) {
                    $timeSource = 'update_time';
                    $this->logger->debug('使用更新时间作为发布时间', ['source' => 'update_time', 'timestamp' => $articleData['update_time']]);
                } else {
                    $this->logger->warning('创建更新时间DateTime失败', ['update_time' => $articleData['update_time']]);
                }
            }

            // 优先级3: 使用当前时间作为默认值，确保永远不会为空
            if ($releaseTime === null) {
                $releaseTime = new \DateTime();
                $timeSource = 'current_time';
                $this->logger->warning('未找到有效的时间字段，使用当前时间作为默认值', [
                    'articleId' => $articleId,
                    'available_fields' => array_keys($articleData),
                    'has_publish_time' => isset($articleData['publish_time']),
                    'has_update_time' => isset($articleData['update_time']),
                    'default_time' => $releaseTime->format('Y-m-d H:i:s')
                ]);
            }

            // 设置最终的时间值，确保格式正确
            if ($releaseTime instanceof \DateTime) {
                $formattedTime = $releaseTime->format('Y-m-d H:i:s');
                $article->setReleaseTime($formattedTime);
                $this->logger->info('发布时间设置成功', [
                    'articleId' => $articleId,
                    'timeSource' => $timeSource,
                    'releaseTime' => $formattedTime
                ]);
            } else {
                // 额外的安全检查，理论上不应该到达这里
                $fallbackTime = new \DateTime();
                $article->setReleaseTime($fallbackTime->format('Y-m-d H:i:s'));
                $this->logger->error('时间创建失败，使用紧急备用时间', [
                    'articleId' => $articleId,
                    'fallbackTime' => $fallbackTime->format('Y-m-d H:i:s')
                ]);
            }

            // 设置更新时间
            if (isset($articleData['update_time'])) {
                $updateTime = \DateTime::createFromFormat('U', $articleData['update_time']);
                if ($updateTime) {
                    $article->setUpdatedAt($updateTime);
                    $this->logger->debug('设置更新时间成功', ['updateTime' => $updateTime->format('Y-m-d H:i:s')]);
                } else {
                    $this->logger->warning('创建DateTime失败', ['update_time' => $articleData['update_time']]);
                }
            }

            return $article;
        } catch (\Exception $e) {
            $this->logger->error('处理文章数据时发生异常', [
                'articleData' => $articleData,
                'exception' => $e
            ]);
            return null;
        }
    }

    /**
     * 保存文章列表
     *
     * @param array $articles
     * @return array
     */
    private function saveArticles(array $articles): array
    {
        $result = [
            'new_count' => 0,
            'updated_count' => 0,
            'errors' => []
        ];

        foreach ($articles as $article) {
            try {
                $isNew = $article->getId() === null;

                $this->entityManager->persist($article);
                $this->entityManager->flush();

                if ($isNew) {
                    $result['new_count']++;
                } else {
                    $result['updated_count']++;
                }
            } catch (\Exception $e) {
                $errorMsg = '保存文章失败: ' . $e->getMessage();
                $result['errors'][] = $errorMsg;
                $this->logger->error($errorMsg, [
                    'articleId' => $article->getArticleId(),
                    'exception' => $e
                ]);
            }
        }

        return $result;
    }

    /**
     * 同步文章 - syncPublishedArticles的包装器方法
     *
     * @param string $accountId 账户ID
     * @param bool $forceSync 是否强制同步
     * @param bool $bypassLock 是否绕过锁定
     * @return array 格式化的同步结果
     */
    public function syncArticles(string $accountId, bool $forceSync = false, bool $bypassLock = false): array
    {
        // 转换参数格式
        $options = [
            'force_sync' => $forceSync,
            'bypass_lock' => $bypassLock
        ];

        // 调用现有方法
        $result = $this->syncPublishedArticles($accountId, $options);

        // 调整返回格式以匹配预期
        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'stats' => [
                'total' => $result['total_processed'],
                'created' => $result['new_articles'],
                'updated' => $result['updated_articles'],
                'skipped' => 0, // 需要从其他地方计算或设置为0
                'failed' => count($result['errors'])
            ],
            'errors' => $result['errors']
        ];
    }

    /**
     * 获取同步状态
     *
     * @param string $accountId 公众号ID
     * @return array 同步状态信息
     */
    public function getSyncStatus(string $accountId): array
    {
        try {
            $this->logger->info('获取同步状态', ['accountId' => $accountId]);

            // 验证公众号是否存在
            $publicAccount = $this->wechatPublicAccountRepository->find($accountId);
            if (!$publicAccount) {
                return [
                    'error' => '公众号不存在: ' . $accountId,
                    'accountId' => $accountId,
                    'exists' => false
                ];
            }

            // 获取该账号的文章统计信息
            $totalArticles = $this->officialRepository->countByAccountId($accountId);
            $activeArticles = $this->officialRepository->countActiveByAccountId($accountId);
            $lastSyncTime = $this->officialRepository->getLastSyncTime($accountId);

            // 获取最近的同步记录
            $recentArticles = $this->officialRepository->findRecentByAccountId($accountId, 5);

            $status = [
                'accountId' => $accountId,
                'accountName' => $publicAccount->getName() ?? '未知公众号',
                'exists' => true,
                'lastSyncTime' => $lastSyncTime ? $lastSyncTime->format('Y-m-d H:i:s') : null,
                'statistics' => [
                    'totalArticles' => $totalArticles,
                    'activeArticles' => $activeArticles,
                    'inactiveArticles' => $totalArticles - $activeArticles
                ],
                'recentArticles' => array_map(function ($article) {
                    return [
                        'id' => $article->getId(),
                        'articleId' => $article->getArticleId(),
                        'title' => $article->getTitle(),
                        'updateTime' => $article->getUpdatedAt()->format('Y-m-d H:i:s'),
                        'status' => $article->getStatus()
                    ];
                }, $recentArticles),
                'syncStatus' => $this->determineSyncStatus($lastSyncTime, $totalArticles)
            ];

            $this->logger->info('同步状态获取成功', $status);

            return $status;

        } catch (\Exception $e) {
            $this->logger->error('获取同步状态失败', [
                'accountId' => $accountId,
                'error' => $e->getMessage(),
                'exception' => $e
            ]);

            return [
                'error' => '获取同步状态失败: ' . $e->getMessage(),
                'accountId' => $accountId,
                'exists' => false
            ];
        }
    }

    /**
     * 确定同步状态
     *
     * @param \DateTime|null $lastSyncTime 最后同步时间
     * @param int $totalArticles 文章总数
     * @return string
     */
    private function determineSyncStatus(?\DateTime $lastSyncTime, int $totalArticles): string
    {
        if (!$lastSyncTime) {
            return 'never_synced';
        }

        $now = new \DateTime();
        $interval = $now->diff($lastSyncTime);
        $hoursAgo = $interval->h + ($interval->days * 24);

        if ($hoursAgo < 1) {
            return 'recently_synced';
        } elseif ($hoursAgo < 24) {
            return 'synced_today';
        } elseif ($hoursAgo < 168) { // 7 days
            return 'synced_this_week';
        } else {
            return 'needs_sync';
        }
    }
}
