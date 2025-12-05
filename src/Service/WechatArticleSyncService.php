<?php

namespace App\Service;

use App\Entity\Official;
use App\Entity\WechatPublicAccount;
use App\Repository\OfficialRepository;
use App\Repository\WechatPublicAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class WechatArticleSyncService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OfficialRepository $officialRepository,
        private readonly WechatPublicAccountRepository $wechatAccountRepository,
        private readonly WechatApiService $wechatApiService,
        private readonly ImageUploadService $imageUploadService,
        private readonly DistributedLockService $distributedLockService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * 同步公众号文章
     */
    public function syncArticles(string $accountId, bool $forceSync = false, bool $bypassLock = false): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'stats' => [
                'total' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
            ],
            'errors' => [],
        ];

        // 获取公众号账户
        $account = $this->wechatAccountRepository->find($accountId);
        if (!$account) {
            $result['message'] = '公众号账户不存在';
            return $result;
        }

        if (!$account->isActive()) {
            $result['message'] = '公众号账户未激活';
            return $result;
        }

        // 使用分布式锁防止并发同步（除非使用绕过锁检查选项）
        $lockKey = 'wechat_sync_' . $accountId;
        if (!$bypassLock) {
            if (!$this->distributedLockService->acquireLock($lockKey, 1800)) { // 30分钟锁
                $result['message'] = '同步任务正在进行中，请稍后再试';
                return $result;
            }
        } else {
            // 如果绕过锁检查，直接尝试获取锁但不检查结果
            $this->distributedLockService->acquireLock($lockKey, 1800);
        }

        try {
            // 获取access_token
            $accessToken = $this->wechatApiService->getAccessToken($account);
            if (!$accessToken) {
                $result['message'] = '获取access_token失败';
                return $result;
            }

            // 批量获取已发布消息列表 - 获取公众号已发布的文章
            $publishedItems = $this->wechatApiService->getAllPublishedArticles($accessToken, 20, 0);

            if (empty($publishedItems)) {
                $result['message'] = '没有获取到历史发布文章';
                $result['success'] = true;
                return $result;
            }

            $result['stats']['total'] = count($publishedItems);
            $this->logger->info(sprintf(
                '获取到已发布消息：%d 篇',
                count($publishedItems)
            ));

            // 提取文章数据
            $articles = $this->wechatApiService->extractAllPublishedArticles($publishedItems);

            // 处理每篇文章
            foreach ($articles as $articleData) {
                $processResult = $this->processArticle($account, $articleData, $forceSync);

                switch ($processResult['status']) {
                    case 'created':
                        $result['stats']['created']++;
                        break;
                    case 'updated':
                        $result['stats']['updated']++;
                        break;
                    case 'skipped':
                        $result['stats']['skipped']++;
                        break;
                    case 'failed':
                        $result['stats']['failed']++;
                        $result['errors'][] = $processResult['error'];
                        break;
                }
            }

            $result['success'] = true;
            $result['message'] = sprintf(
                '同步完成：总计%d篇，新增%d篇，更新%d篇，跳过%d篇，失败%d篇',
                $result['stats']['total'],
                $result['stats']['created'],
                $result['stats']['updated'],
                $result['stats']['skipped'],
                $result['stats']['failed']
            );

        } catch (\Exception $e) {
            $result['message'] = '同步过程中发生异常: ' . $e->getMessage();
            $result['errors'][] = $e->getMessage();
            $this->logger->error('公众号文章同步异常: ' . $e->getMessage());
        } finally {
            // 释放锁
            $this->distributedLockService->releaseLock($lockKey);
        }

        return $result;
    }

    /**
     * 处理单篇文章
     */
    private function processArticle(WechatPublicAccount $account, array $articleData, bool $forceSync): array
    {
        $result = ['status' => 'skipped'];

        try {
            // 检查文章是否已存在（基于原始URL或文章ID）
            $originalUrl = $articleData['content_source_url'] ?? $articleData['url'] ?? '';
            $articleId = $articleData['article_id'] ?? '';

            // 优先使用article_id进行去重，如果没有则使用URL
            $existingArticle = null;
            if ($articleId) {
                $existingArticle = $this->officialRepository->findOneBy(['articleId' => $articleId]);
            }
            if (!$existingArticle && $originalUrl) {
                $existingArticle = $this->officialRepository->findOneBy(['originalUrl' => $originalUrl]);
            }

            // 如果强制同步或者文章不存在，则处理
            if ($forceSync || !$existingArticle) {
                // 提取图片URL
                $content = $articleData['content'] ?? '';
                $imageUrls = $this->imageUploadService->extractImageUrls($content);

                // 上传图片并获取URL映射
                $urlMapping = [];
                if (!empty($imageUrls)) {
                    $urlMapping = $this->imageUploadService->uploadImages($imageUrls);
                }

                // 替换内容中的图片URL
                $processedContent = $this->imageUploadService->replaceImageUrls($content, $urlMapping);

                // 处理封面图
                $coverImageUrl = $articleData['thumb_url'] ?? '';
                $processedCoverUrl = $coverImageUrl ? $this->imageUploadService->uploadImage($coverImageUrl) : '';

                if ($existingArticle) {
                    // 更新现有文章
                    $this->updateArticle($existingArticle, $articleData, $processedContent, $processedCoverUrl);
                    $result['status'] = 'updated';
                } else {
                    // 创建新文章
                    $this->createArticle($account, $articleData, $processedContent, $processedCoverUrl);
                    $result['status'] = 'created';
                }
            } else {
                $result['status'] = 'skipped';
            }

        } catch (\Exception $e) {
            $result['status'] = 'failed';
            $result['error'] = '处理文章失败: ' . $e->getMessage() . ' - 文章标题: ' . ($articleData['title'] ?? '未知');
            $this->logger->error($result['error']);
        }

        return $result;
    }

    /**
     * 创建新文章
     */
    private function createArticle(WechatPublicAccount $account, array $articleData, string $processedContent, ?string $coverUrl): void
    {
        $article = new Official();

        $article->setTitle($articleData['title'] ?? '');
        $article->setContent($processedContent);
        $article->setTitleImg($coverUrl ?? '');
        $article->setOriginalUrl($articleData['content_source_url'] ?? $articleData['url'] ?? '');

        // 设置文章ID（如果有）
        if (isset($articleData['article_id'])) {
            $article->setArticleId($articleData['article_id']);
        }

        // 设置发布时间（优先使用publish_time，其次使用update_time）
        $timestamp = $articleData['publish_time'] ?? $articleData['update_time'] ?? time();
        $releaseTime = (new \DateTime())->setTimestamp($timestamp);
        $article->setReleaseTime($releaseTime->format('Y-m-d H:i:s'));

        // 设置状态为激活
        $article->setStatus(1);

        // 设置分类为固定分类ID 18 (GZH_001)
        $category = $this->entityManager->getRepository(\App\Entity\SysNewsArticleCategory::class)->find(18);
        if ($category) {
            $article->setCategory($category);
        }

        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $this->logger->info('创建文章成功: ' . $article->getTitle());
    }

    /**
     * 更新现有文章
     */
    private function updateArticle(Official $article, array $articleData, string $processedContent, ?string $coverUrl): void
    {
        $article->setTitle($articleData['title'] ?? $article->getTitle());
        $article->setContent($processedContent);

        if ($coverUrl) {
            $article->setTitleImg($coverUrl);
        }

        // 更新文章ID（如果有）
        if (isset($articleData['article_id'])) {
            $article->setArticleId($articleData['article_id']);
        }

        // 更新发布时间（优先使用publish_time，其次使用update_time）
        $timestamp = $articleData['publish_time'] ?? $articleData['update_time'] ?? time();
        $releaseTime = (new \DateTime())->setTimestamp($timestamp);
        $article->setReleaseTime($releaseTime->format('Y-m-d H:i:s'));

        // 更新分类为固定分类ID 18 (GZH_001)
        $category = $this->entityManager->getRepository(\App\Entity\SysNewsArticleCategory::class)->find(18);
        if ($category) {
            $article->setCategory($category);
        }

        $this->entityManager->flush();

        $this->logger->info('更新文章成功: ' . $article->getTitle());
    }

    /**
     * 获取同步状态
     */
    public function getSyncStatus(string $accountId): array
    {
        $account = $this->wechatAccountRepository->find($accountId);
        if (!$account) {
            return ['error' => '公众号账户不存在'];
        }

        $lockKey = 'wechat_sync_' . $accountId;
        $isSyncing = $this->distributedLockService->isLocked($lockKey);

        return [
            'account_id' => $accountId,
            'account_name' => $account->getName(),
            'is_syncing' => $isSyncing,
            'last_sync_time' => null, // 可以扩展记录最后同步时间
        ];
    }

    /**
     * 同步已发布消息（根据微信官方文档）
     */
    public function syncPublishedArticles(string $accountId, bool $forceSync = false, bool $bypassLock = false, int $beginDate = 0, int $endDate = 0): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'stats' => [
                'total' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
            ],
            'errors' => [],
        ];

        // 获取公众号账户
        $account = $this->wechatAccountRepository->find($accountId);
        if (!$account) {
            $result['message'] = '公众号账户不存在';
            return $result;
        }

        if (!$account->isActive()) {
            $result['message'] = '公众号账户未激活';
            return $result;
        }

        // 使用分布式锁防止并发同步（除非使用绕过锁检查选项）
        $lockKey = 'wechat_published_sync_' . $accountId;
        if (!$bypassLock) {
            if (!$this->distributedLockService->acquireLock($lockKey, 1800)) { // 30分钟锁
                $result['message'] = '已发布消息同步任务正在进行中，请稍后再试';
                return $result;
            }
        } else {
            // 如果绕过锁检查，直接尝试获取锁但不检查结果
            $this->distributedLockService->acquireLock($lockKey, 1800);
        }

        try {
            // 获取access_token
            $accessToken = $this->wechatApiService->getAccessToken($account);
            if (!$accessToken) {
                $result['message'] = '获取access_token失败';
                return $result;
            }

            // 批量获取已发布消息列表
            $publishedItems = $this->wechatApiService->getAllPublishedArticles($accessToken, 20, 0, $beginDate, $endDate);

            if (empty($publishedItems)) {
                $result['message'] = '没有获取到已发布消息';
                $result['success'] = true;
                return $result;
            }

            $result['stats']['total'] = count($publishedItems);
            $this->logger->info(sprintf(
                '获取到已发布消息：%d 篇',
                count($publishedItems)
            ));

            // 提取文章数据
            $articles = $this->wechatApiService->extractAllPublishedArticles($publishedItems);

            // 处理每篇文章
            foreach ($articles as $articleData) {
                $processResult = $this->processArticle($account, $articleData, $forceSync);

                switch ($processResult['status']) {
                    case 'created':
                        $result['stats']['created']++;
                        break;
                    case 'updated':
                        $result['stats']['updated']++;
                        break;
                    case 'skipped':
                        $result['stats']['skipped']++;
                        break;
                    case 'failed':
                        $result['stats']['failed']++;
                        $result['errors'][] = $processResult['error'];
                        break;
                }
            }

            $result['success'] = true;
            $result['message'] = sprintf(
                '已发布消息同步完成：总计%d篇，新增%d篇，更新%d篇，跳过%d篇，失败%d篇',
                $result['stats']['total'],
                $result['stats']['created'],
                $result['stats']['updated'],
                $result['stats']['skipped'],
                $result['stats']['failed']
            );

        } catch (\Exception $e) {
            $result['message'] = '已发布消息同步过程中发生异常: ' . $e->getMessage();
            $result['errors'][] = $e->getMessage();
            $this->logger->error('已发布消息同步异常: ' . $e->getMessage());
        } finally {
            // 释放锁
            $this->distributedLockService->releaseLock($lockKey);
        }

        return $result;
    }

    /**
     * 获取同步统计
     */
    public function getSyncStats(string $accountId): array
    {
        // 这里可以统计该公众号下的文章数量等
        // 由于Official实体没有直接关联公众号ID，暂时返回0
        $articleCount = 0;

        return [
            'account_id' => $accountId,
            'total_articles' => $articleCount,
            'last_sync_info' => null, // 可以扩展记录同步历史
        ];
    }
}
