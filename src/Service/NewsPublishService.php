<?php

namespace App\Service;

use App\Entity\SysNewsArticle;
use App\Repository\SysNewsArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class NewsPublishService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SysNewsArticleRepository $sysNewsArticleRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * 执行定时发布任务
     * 扫描所有满足发布条件的文章并自动发布
     */
    public function executePublishTask(): array
    {
        $result = [
            'success' => 0,
            'failed' => 0,
            'total' => 0,
            'published_articles' => [],
            'errors' => []
        ];

        try {
            // 获取需要发布的文章列表
            $articlesToPublish = $this->sysNewsArticleRepository->findArticlesToPublish();
            $result['total'] = count($articlesToPublish);

            if (empty($articlesToPublish)) {
                $this->logger->info('定时发布任务：没有需要发布的文章');
                return $result;
            }

            $this->logger->info(sprintf('定时发布任务：发现 %d 篇文章需要发布', $result['total']));

            // 批量更新状态
            $articleIds = array_map(function($article) {
                return $article->getId();
            }, $articlesToPublish);

            $successCount = $this->sysNewsArticleRepository->batchUpdateStatus(
                $articleIds,
                SysNewsArticle::STATUS_ACTIVE
            );

            $result['success'] = $successCount;
            $result['failed'] = $result['total'] - $successCount;

            // 记录发布的文章信息
            foreach ($articlesToPublish as $article) {
                $result['published_articles'][] = [
                    'id' => $article->getId(),
                    'name' => $article->getName(),
                    'release_time' => $article->getReleaseTime()->format('Y-m-d H:i:s')
                ];
            }

            $this->logger->info(sprintf(
                '定时发布任务完成：成功发布 %d 篇，失败 %d 篇',
                $result['success'],
                $result['failed']
            ));

            // 记录发布日志
            $this->logPublishResult($result);

        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
            $this->logger->error('定时发布任务执行失败：' . $e->getMessage());
        }

        return $result;
    }

    /**
     * 检查是否有延迟发布的文章
     */
    public function checkDelayedPublish(): array
    {
        $result = [
            'delayed_count' => 0,
            'delayed_articles' => []
        ];

        try {
            // 查找超过预约时间但仍未发布的文章
            $delayedArticles = $this->sysNewsArticleRepository->findDelayedArticles();
            $result['delayed_count'] = count($delayedArticles);

            foreach ($delayedArticles as $article) {
                $result['delayed_articles'][] = [
                    'id' => $article->getId(),
                    'name' => $article->getName(),
                    'scheduled_time' => $article->getReleaseTime()->format('Y-m-d H:i:s'),
                    'current_status' => $article->getStatus(),
                    'delay_minutes' => $this->calculateDelayMinutes($article->getReleaseTime())
                ];
            }

            if ($result['delayed_count'] > 0) {
                $this->logger->warning(sprintf(
                    '发现 %d 篇延迟发布的文章',
                    $result['delayed_count']
                ));
            }

        } catch (\Exception $e) {
            $this->logger->error('检查延迟发布文章失败：' . $e->getMessage());
        }

        return $result;
    }

    /**
     * 获取发布统计信息
     */
    public function getPublishStats(): array
    {
        $stats = [];

        try {
            // 今日发布统计
            $todayStart = new \DateTime('today');
            $todayEnd = new \DateTime('tomorrow');

            $stats['today_published'] = $this->sysNewsArticleRepository->countPublishedBetween(
                $todayStart,
                $todayEnd
            );

            // 本周发布统计
            $weekStart = new \DateTime('monday this week');
            $weekEnd = new \DateTime('sunday this week 23:59:59');

            $stats['week_published'] = $this->sysNewsArticleRepository->countPublishedBetween(
                $weekStart,
                $weekEnd
            );

            // 待发布文章数量
            $stats['scheduled_count'] = $this->sysNewsArticleRepository->countScheduledArticles();

            // 延迟发布文章数量
            $delayedStats = $this->checkDelayedPublish();
            $stats['delayed_count'] = $delayedStats['delayed_count'];

            // 发布成功率（基于最近100次发布）
            $recentPublishStats = $this->sysNewsArticleRepository->getRecentPublishStats(100);
            $stats['success_rate'] = $recentPublishStats['success_rate'] ?? 100;

        } catch (\Exception $e) {
            $this->logger->error('获取发布统计信息失败：' . $e->getMessage());
            $stats['error'] = $e->getMessage();
        }

        return $stats;
    }

    /**
     * 手动强制发布文章
     */
    public function forcePublishArticle(int $articleId): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'article' => null
        ];

        try {
            $article = $this->sysNewsArticleRepository->find($articleId);

            if (!$article) {
                $result['message'] = '文章不存在';
                return $result;
            }

            if ($article->isDeleted()) {
                $result['message'] = '文章已被删除';
                return $result;
            }

            // 强制设置为激活状态
            $article->setStatus(SysNewsArticle::STATUS_ACTIVE);

            // 如果发布时间在未来，更新为当前时间
            $currentTime = new \DateTime();
            if ($article->getReleaseTime() > $currentTime) {
                $article->setReleaseTime($currentTime);
            }

            $this->entityManager->flush();

            $result['success'] = true;
            $result['message'] = '强制发布成功';
            $result['article'] = [
                'id' => $article->getId(),
                'name' => $article->getName(),
                'status' => $article->getStatus(),
                'release_time' => $article->getReleaseTime()->format('Y-m-d H:i:s')
            ];

            $this->logger->info(sprintf(
                '手动强制发布文章：ID=%d, 名称=%s',
                $article->getId(),
                $article->getName()
            ));

        } catch (\Exception $e) {
            $result['message'] = '强制发布失败：' . $e->getMessage();
            $this->logger->error('强制发布文章失败：' . $e->getMessage());
        }

        return $result;
    }

    /**
     * 记录发布结果日志
     */
    private function logPublishResult(array $result): void
    {
        $logMessage = sprintf(
            "定时发布任务结果 - 总数: %d, 成功: %d, 失败: %d",
            $result['total'],
            $result['success'],
            $result['failed']
        );

        if (!empty($result['published_articles'])) {
            $articleNames = array_map(function($article) {
                return $article['name'];
            }, $result['published_articles']);
            $logMessage .= ", 发布的文章: " . implode(', ', $articleNames);
        }

        if (!empty($result['errors'])) {
            $logMessage .= ", 错误: " . implode('; ', $result['errors']);
        }

        $this->logger->info($logMessage);
    }

    /**
     * 计算延迟分钟数
     */
    private function calculateDelayMinutes(\DateTimeInterface $scheduledTime): int
    {
        $currentTime = new \DateTime();
        $interval = $currentTime->diff($scheduledTime);

        return (int)($interval->days * 24 * 60 + $interval->h * 60 + $interval->i);
    }
}
