<?php

namespace App\Repository;

use App\Entity\ArticleReadStatistics;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArticleReadStatistics>
 */
class ArticleReadStatisticsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleReadStatistics::class);
    }

    /**
     * 获取或创建文章的日统计数据
     */
    public function getOrCreateDailyStats(int $articleId, \DateTimeInterface $date): ArticleReadStatistics
    {
        $stats = $this->findOneBy([
            'articleId' => $articleId,
            'statDate' => $date
        ]);

        if (!$stats) {
            $stats = new ArticleReadStatistics();
            $stats->setArticleId($articleId);
            $stats->setStatDate($date);
            $this->getEntityManager()->persist($stats);
            $this->getEntityManager()->flush();
        }

        return $stats;
    }

    /**
     * 更新文章的日统计数据
     */
    public function updateDailyStats(int $articleId, \DateTimeInterface $date, array $readData): ArticleReadStatistics
    {
        $stats = $this->getOrCreateDailyStats($articleId, $date);

        // 更新各项统计数据
        if (isset($readData['total_reads'])) {
            $stats->setTotalReads($readData['total_reads']);
        }
        if (isset($readData['unique_users'])) {
            $stats->setUniqueUsers($readData['unique_users']);
        }
        if (isset($readData['anonymous_reads'])) {
            $stats->setAnonymousReads($readData['anonymous_reads']);
        }
        if (isset($readData['registered_reads'])) {
            $stats->setRegisteredReads($readData['registered_reads']);
        }
        if (isset($readData['avg_duration_seconds'])) {
            $stats->setAvgDurationSeconds($readData['avg_duration_seconds']);
        }
        if (isset($readData['completion_rate'])) {
            $stats->setCompletionRate($readData['completion_rate']);
        }

        $this->getEntityManager()->flush();

        return $stats;
    }

    /**
     * 批量更新文章统计数据
     */
    public function batchUpdateStats(array $statsData): array
    {
        $updatedStats = [];
        $errors = [];

        foreach ($statsData as $data) {
            try {
                $articleId = $data['article_id'];
                $date = new \DateTime($data['date']);

                $stats = $this->getOrCreateDailyStats($articleId, $date);

                // 更新统计数据
                if (isset($data['total_reads'])) {
                    $stats->setTotalReads($data['total_reads']);
                }
                if (isset($data['unique_users'])) {
                    $stats->setUniqueUsers($data['unique_users']);
                }
                if (isset($data['anonymous_reads'])) {
                    $stats->setAnonymousReads($data['anonymous_reads']);
                }
                if (isset($data['registered_reads'])) {
                    $stats->setRegisteredReads($data['registered_reads']);
                }
                if (isset($data['avg_duration_seconds'])) {
                    $stats->setAvgDurationSeconds($data['avg_duration_seconds']);
                }
                if (isset($data['completion_rate'])) {
                    $stats->setCompletionRate($data['completion_rate']);
                }

                $updatedStats[] = $stats;
            } catch (\Exception $e) {
                $errors[] = "更新文章ID {$data['article_id']} 在 {$data['date']} 的统计数据失败: " . $e->getMessage();
            }
        }

        if (!empty($updatedStats)) {
            $this->getEntityManager()->flush();
        }

        return [
            'updated_count' => count($updatedStats),
            'errors' => $errors
        ];
    }

    /**
     * 获取文章的统计趋势
     */
    public function getArticleStatsTrend(int $articleId, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('stats')
            ->select([
                'stats.statDate as date',
                'stats.totalReads',
                'stats.uniqueUsers',
                'stats.anonymousReads',
                'stats.registeredReads',
                'stats.avgDurationSeconds',
                'stats.completionRate'
            ])
            ->where('stats.articleId = :articleId')
            ->andWhere('stats.statDate BETWEEN :startDate AND :endDate')
            ->orderBy('stats.statDate', 'ASC')
            ->setParameter('articleId', $articleId)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取文章的总体统计
     */
    public function getArticleOverallStats(int $articleId): array
    {
        $qb = $this->createQueryBuilder('stats')
            ->select([
                'SUM(stats.totalReads) as totalReads',
                'SUM(stats.uniqueUsers) as totalUniqueUsers',
                'SUM(stats.anonymousReads) as totalAnonymousReads',
                'SUM(stats.registeredReads) as totalRegisteredReads',
                'AVG(stats.avgDurationSeconds) as avgDurationSeconds',
                'AVG(stats.completionRate) as avgCompletionRate',
                'MIN(stats.statDate) as firstReadDate',
                'MAX(stats.statDate) as lastReadDate',
                'COUNT(stats.id) as daysWithReads'
            ])
            ->where('stats.articleId = :articleId')
            ->setParameter('articleId', $articleId)
            ->getQuery()
            ->getSingleResult();

        return $qb;
    }

    /**
     * 获取热门文章统计
     */
    public function getPopularArticlesStats(\DateTimeInterface $startDate, \DateTimeInterface $endDate, int $limit = 10): array
    {
        return $this->createQueryBuilder('stats')
            ->select([
                'stats.articleId',
                'SUM(stats.totalReads) as totalReads',
                'SUM(stats.uniqueUsers) as totalUniqueUsers',
                'AVG(stats.avgDurationSeconds) as avgDurationSeconds',
                'AVG(stats.completionRate) as avgCompletionRate'
            ])
            ->where('stats.statDate BETWEEN :startDate AND :endDate')
            ->groupBy('stats.articleId')
            ->orderBy('totalReads', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取日期范围内的总体统计
     */
    public function getOverallStats(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $qb = $this->createQueryBuilder('stats')
            ->select([
                'SUM(stats.totalReads) as totalReads',
                'SUM(stats.uniqueUsers) as totalUniqueUsers',
                'SUM(stats.anonymousReads) as totalAnonymousReads',
                'SUM(stats.registeredReads) as totalRegisteredReads',
                'AVG(stats.avgDurationSeconds) as avgDurationSeconds',
                'AVG(stats.completionRate) as avgCompletionRate',
                'COUNT(DISTINCT stats.articleId) as uniqueArticles',
                'COUNT(stats.id) as totalDays'
            ])
            ->where('stats.statDate BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleResult();

        return $qb;
    }

    /**
     * 获取每日总体统计趋势
     */
    public function getDailyTrendStats(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('stats')
            ->select([
                'stats.statDate as date',
                'SUM(stats.totalReads) as totalReads',
                'SUM(stats.uniqueUsers) as totalUniqueUsers',
                'SUM(stats.anonymousReads) as totalAnonymousReads',
                'SUM(stats.registeredReads) as totalRegisteredReads',
                'AVG(stats.avgDurationSeconds) as avgDurationSeconds',
                'AVG(stats.completionRate) as avgCompletionRate',
                'COUNT(DISTINCT stats.articleId) as uniqueArticles'
            ])
            ->where('stats.statDate BETWEEN :startDate AND :endDate')
            ->groupBy('stats.statDate')
            ->orderBy('stats.statDate', 'ASC')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取文章的阅读完成率排行
     */
    public function getTopCompletionRateArticles(\DateTimeInterface $startDate, \DateTimeInterface $endDate, int $limit = 10, int $minReads = 10): array
    {
        return $this->createQueryBuilder('stats')
            ->select([
                'stats.articleId',
                'SUM(stats.totalReads) as totalReads',
                'AVG(stats.completionRate) as avgCompletionRate',
                'AVG(stats.avgDurationSeconds) as avgDurationSeconds'
            ])
            ->where('stats.statDate BETWEEN :startDate AND :endDate')
            ->groupBy('stats.articleId')
            ->having('totalReads >= :minReads')
            ->orderBy('avgCompletionRate', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('minReads', $minReads)
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取文章的平均阅读时长排行
     */
    public function getTopAvgDurationArticles(\DateTimeInterface $startDate, \DateTimeInterface $endDate, int $limit = 10, int $minReads = 10): array
    {
        return $this->createQueryBuilder('stats')
            ->select([
                'stats.articleId',
                'SUM(stats.totalReads) as totalReads',
                'AVG(stats.avgDurationSeconds) as avgDurationSeconds',
                'AVG(stats.completionRate) as avgCompletionRate'
            ])
            ->where('stats.statDate BETWEEN :startDate AND :endDate')
            ->groupBy('stats.articleId')
            ->having('totalReads >= :minReads')
            ->orderBy('avgDurationSeconds', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('minReads', $minReads)
            ->getQuery()
            ->getResult();
    }

    /**
     * 清理旧的统计数据
     */
    public function cleanupOldStats(\DateTimeInterface $beforeDate): int
    {
        $qb = $this->createQueryBuilder('stats')
            ->delete()
            ->where('stats.statDate < :beforeDate')
            ->setParameter('beforeDate', $beforeDate);

        return $qb->getQuery()->execute();
    }

    /**
     * 获取用户阅读习惯统计
     */
    public function getUserReadingPatternStats(int $userId, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        // 这里需要关联阅读记录表来获取更详细的用户行为数据
        // 暂时返回基础统计数据
        return [];
    }

    /**
     * 获取文章在特定时间段的阅读分布
     */
    public function getArticleReadDistribution(int $articleId, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('stats')
            ->select([
                'stats.statDate as date',
                'stats.totalReads',
                'stats.uniqueUsers',
                'stats.anonymousReads',
                'stats.registeredReads'
            ])
            ->where('stats.articleId = :articleId')
            ->andWhere('stats.statDate BETWEEN :startDate AND :endDate')
            ->orderBy('stats.statDate', 'ASC')
            ->setParameter('articleId', $articleId)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();
    }
}
