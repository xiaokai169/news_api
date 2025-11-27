<?php

namespace App\Service;

use App\Entity\ArticleReadLog;
use App\Entity\ArticleReadStatistics;
use App\Entity\SysNewsArticle;
use App\Repository\ArticleReadLogRepository;
use App\Repository\ArticleReadStatisticsRepository;
use App\Repository\SysNewsArticleRepository;
use App\DTO\Request\ArticleReadLogDto;
use App\DTO\Filter\ArticleReadFilterDto;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * 文章阅读统计服务
 *
 * 提供文章阅读记录、统计和分析功能
 */
class ArticleReadService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ArticleReadLogRepository $readLogRepository,
        private readonly ArticleReadStatisticsRepository $statisticsRepository,
        private readonly SysNewsArticleRepository $articleRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * 记录文章阅读
     *
     * @param ArticleReadLogDto $readLogDto 阅读记录DTO
     * @param bool $checkDuplicate 是否检查重复阅读
     * @return array 记录结果
     */
    public function logArticleRead(ArticleReadLogDto $readLogDto, bool $checkDuplicate = true): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'readLog' => null,
            'isNewRead' => false,
            'articleUpdated' => false
        ];

        try {
            // 验证文章是否存在
            $article = $this->articleRepository->find($readLogDto->articleId);
            if (!$article) {
                $result['message'] = '文章不存在';
                return $result;
            }

            if ($article->isDeleted()) {
                $result['message'] = '文章已被删除';
                return $result;
            }

            // 检查重复阅读
            if ($checkDuplicate && $this->isDuplicateRead($readLogDto)) {
                $result['message'] = '重复阅读记录';
                $result['success'] = true; // 重复阅读不算错误
                return $result;
            }

            // 准备额外数据
            $additionalData = [
                'ip_address' => $readLogDto->ipAddress,
                'user_agent' => $readLogDto->userAgent,
                'referer' => $readLogDto->referer,
                'duration_seconds' => $readLogDto->durationSeconds,
                'is_completed' => $readLogDto->isCompleted
            ];

            // 记录阅读日志
            $readLog = $this->readLogRepository->logArticleRead(
                $readLogDto->articleId,
                $readLogDto->userId,
                $readLogDto->sessionId,
                $additionalData
            );

            $result['readLog'] = $readLog;
            $result['isNewRead'] = true;

            // 更新文章的阅读数量
            $this->updateArticleViewCount($readLogDto->articleId);
            $result['articleUpdated'] = true;

            // 异步更新统计数据
            $this->updateReadStatisticsAsync($readLogDto->articleId);

            $result['success'] = true;
            $result['message'] = '阅读记录成功';

            $this->logger->info('文章阅读记录成功', [
                'articleId' => $readLogDto->articleId,
                'userId' => $readLogDto->userId,
                'sessionId' => $readLogDto->sessionId,
                'duration' => $readLogDto->durationSeconds,
                'isCompleted' => $readLogDto->isCompleted
            ]);

        } catch (\Exception $e) {
            $result['message'] = '阅读记录失败：' . $e->getMessage();
            $this->logger->error('文章阅读记录失败', [
                'articleId' => $readLogDto->articleId,
                'userId' => $readLogDto->userId,
                'error' => $e->getMessage()
            ]);
        }

        return $result;
    }

    /**
     * 批量记录文章阅读
     *
     * @param array $readLogDtos 阅读记录DTO数组
     * @return array 批量记录结果
     */
    public function batchLogArticleReads(array $readLogDtos): array
    {
        $result = [
            'success' => 0,
            'failed' => 0,
            'total' => count($readLogDtos),
            'errors' => [],
            'readLogs' => []
        ];

        foreach ($readLogDtos as $index => $readLogDto) {
            if (!$readLogDto instanceof ArticleReadLogDto) {
                $result['failed']++;
                $result['errors'][] = "索引 {$index}: 无效的阅读记录DTO";
                continue;
            }

            $logResult = $this->logArticleRead($readLogDto, false); // 批量时不检查重复

            if ($logResult['success']) {
                $result['success']++;
                if ($logResult['isNewRead']) {
                    $result['readLogs'][] = $logResult['readLog'];
                }
            } else {
                $result['failed']++;
                $result['errors'][] = "索引 {$index}: " . $logResult['message'];
            }
        }

        return $result;
    }

    /**
     * 获取文章阅读统计
     *
     * @param ArticleReadFilterDto $filterDto 过滤条件
     * @return array 统计结果
     */
    public function getArticleReadStatistics(ArticleReadFilterDto $filterDto): array
    {
        try {
            $statistics = [];

            if ($filterDto->articleId) {
                // 单篇文章统计
                if ($filterDto->statType === 'overall') {
                    $statistics = $this->statisticsRepository->getArticleOverallStats($filterDto->articleId);
                } else {
                    $startDate = $filterDto->getReadTimeFromDateTime() ?: (new \DateTime())->sub(new \DateInterval('P30D'));
                    $endDate = $filterDto->getReadTimeToDateTime() ?: new \DateTime();
                    $statistics = $this->statisticsRepository->getArticleStatsTrend(
                        $filterDto->articleId,
                        $startDate,
                        $endDate
                    );
                }
            } else {
                // 总体统计
                $startDate = $filterDto->getReadTimeFromDateTime() ?: (new \DateTime())->sub(new \DateInterval('P30D'));
                $endDate = $filterDto->getReadTimeToDateTime() ?: new \DateTime();

                if ($filterDto->statType === 'daily') {
                    $statistics = $this->statisticsRepository->getDailyTrendStats($startDate, $endDate);
                } else {
                    $statistics = $this->statisticsRepository->getOverallStats($startDate, $endDate);
                }
            }

            return [
                'success' => true,
                'data' => $statistics,
                'filter' => $filterDto->toArray()
            ];

        } catch (\Exception $e) {
            $this->logger->error('获取文章阅读统计失败', [
                'filter' => $filterDto->toArray(),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '获取统计失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取热门文章排行
     *
     * @param \DateTimeInterface|null $startDate 开始日期
     * @param \DateTimeInterface|null $endDate 结束日期
     * @param int $limit 限制数量
     * @return array 热门文章列表
     */
    public function getPopularArticles(?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null, int $limit = 10): array
    {
        try {
            $startDate = $startDate ?: (new \DateTime())->sub(new \DateInterval('P7D'));
            $endDate = $endDate ?: new \DateTime();

            $popularArticles = $this->statisticsRepository->getPopularArticlesStats($startDate, $endDate, $limit);

            // 补充文章详细信息
            $result = [];
            foreach ($popularArticles as $articleStat) {
                $article = $this->articleRepository->find($articleStat['articleId']);
                if ($article) {
                    $result[] = [
                        'article' => $article,
                        'stats' => $articleStat
                    ];
                }
            }

            return [
                'success' => true,
                'data' => $result
            ];

        } catch (\Exception $e) {
            $this->logger->error('获取热门文章失败', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '获取热门文章失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取用户阅读历史
     *
     * @param int $userId 用户ID
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array 阅读历史
     */
    public function getUserReadingHistory(int $userId, int $limit = 20, int $offset = 0): array
    {
        try {
            $readLogs = $this->readLogRepository->findByUser($userId, $limit, $offset);

            // 补充文章信息
            $result = [];
            foreach ($readLogs as $readLog) {
                $article = $this->articleRepository->find($readLog->getArticleId());
                if ($article) {
                    $result[] = [
                        'readLog' => $readLog,
                        'article' => $article
                    ];
                }
            }

            return [
                'success' => true,
                'data' => $result
            ];

        } catch (\Exception $e) {
            $this->logger->error('获取用户阅读历史失败', [
                'userId' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '获取阅读历史失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 更新文章阅读数量
     *
     * @param int $articleId 文章ID
     * @return bool 更新结果
     */
    private function updateArticleViewCount(int $articleId): bool
    {
        try {
            $article = $this->articleRepository->find($articleId);
            if ($article) {
                // 使用原子操作更新阅读数量
                $this->entityManager->getConnection()->executeStatement(
                    'UPDATE sys_news_article SET view_count = view_count + 1 WHERE id = :id',
                    ['id' => $articleId]
                );
                return true;
            }
        } catch (\Exception $e) {
            $this->logger->error('更新文章阅读数量失败', [
                'articleId' => $articleId,
                'error' => $e->getMessage()
            ]);
        }

        return false;
    }

    /**
     * 异步更新阅读统计数据
     *
     * @param int $articleId 文章ID
     */
    private function updateReadStatisticsAsync(int $articleId): void
    {
        try {
            $today = new \DateTime();
            $today->setTime(0, 0, 0);

            // 获取今日的阅读数据
            $dailyStats = $this->readLogRepository->getDailyStatsByArticle(
                $articleId,
                $today,
                (clone $today)->add(new \DateInterval('P1D'))->sub(new \DateInterval('PT1S'))
            );

            if (!empty($dailyStats)) {
                $stats = $dailyStats[0];

                // 更新或创建今日统计
                $this->statisticsRepository->updateDailyStats($articleId, $today, [
                    'total_reads' => (int) $stats['totalReads'],
                    'unique_users' => (int) $stats['uniqueUsers'],
                    'anonymous_reads' => (int) $stats['anonymousReads'],
                    'registered_reads' => (int) $stats['registeredReads'],
                    'avg_duration_seconds' => number_format($stats['avgDuration'], 2, '.', ''),
                    'completion_rate' => $stats['totalReads'] > 0
                        ? number_format(($stats['completedReads'] / $stats['totalReads']) * 100, 2, '.', '')
                        : '0.00'
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('异步更新阅读统计失败', [
                'articleId' => $articleId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 检查是否为重复阅读
     *
     * @param ArticleReadLogDto $readLogDto 阅读记录DTO
     * @return bool 是否重复
     */
    private function isDuplicateRead(ArticleReadLogDto $readLogDto): bool
    {
        // 如果没有用户ID和会话ID，则不检查重复
        if ($readLogDto->userId === 0 && empty($readLogDto->sessionId)) {
            return false;
        }

        return $this->readLogRepository->hasReadToday(
            $readLogDto->articleId,
            $readLogDto->userId,
            $readLogDto->sessionId
        );
    }

    /**
     * 批量更新所有文章的阅读数量
     *
     * @return array 更新结果
     */
    public function batchUpdateAllArticleViewCounts(): array
    {
        try {
            $updatedCount = $this->readLogRepository->batchUpdateArticleViewCounts();

            $this->logger->info('批量更新文章阅读数量完成', [
                'updatedCount' => $updatedCount
            ]);

            return [
                'success' => true,
                'updatedCount' => $updatedCount,
                'message' => "成功更新 {$updatedCount} 篇文章的阅读数量"
            ];

        } catch (\Exception $e) {
            $this->logger->error('批量更新文章阅读数量失败', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '批量更新失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 清理旧的阅读记录
     *
     * @param \DateTimeInterface $beforeDate 清理日期之前的记录
     * @return array 清理结果
     */
    public function cleanupOldReadLogs(\DateTimeInterface $beforeDate): array
    {
        try {
            $deletedCount = $this->readLogRepository->cleanupOldLogs($beforeDate);

            $this->logger->info('清理旧阅读记录完成', [
                'beforeDate' => $beforeDate->format('Y-m-d H:i:s'),
                'deletedCount' => $deletedCount
            ]);

            return [
                'success' => true,
                'deletedCount' => $deletedCount,
                'message' => "成功清理 {$deletedCount} 条旧阅读记录"
            ];

        } catch (\Exception $e) {
            $this->logger->error('清理旧阅读记录失败', [
                'beforeDate' => $beforeDate->format('Y-m-d H:i:s'),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '清理失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取阅读分析报告
     *
     * @param \DateTimeInterface $startDate 开始日期
     * @param \DateTimeInterface $endDate 结束日期
     * @return array 分析报告
     */
    public function getReadAnalysisReport(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        try {
            $overallStats = $this->statisticsRepository->getOverallStats($startDate, $endDate);
            $dailyTrend = $this->statisticsRepository->getDailyTrendStats($startDate, $endDate);
            $popularArticles = $this->statisticsRepository->getPopularArticlesStats($startDate, $endDate, 10);
            $topCompletion = $this->statisticsRepository->getTopCompletionRateArticles($startDate, $endDate, 10);
            $topDuration = $this->statisticsRepository->getTopAvgDurationArticles($startDate, $endDate, 10);

            return [
                'success' => true,
                'data' => [
                    'overallStats' => $overallStats,
                    'dailyTrend' => $dailyTrend,
                    'popularArticles' => $popularArticles,
                    'topCompletionRate' => $topCompletion,
                    'topAvgDuration' => $topDuration,
                    'period' => [
                        'startDate' => $startDate->format('Y-m-d H:i:s'),
                        'endDate' => $endDate->format('Y-m-d H:i:s'),
                        'days' => $startDate->diff($endDate)->days
                    ]
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('获取阅读分析报告失败', [
                'startDate' => $startDate->format('Y-m-d H:i:s'),
                'endDate' => $endDate->format('Y-m-d H:i:s'),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '获取分析报告失败：' . $e->getMessage()
            ];
        }
    }
}
