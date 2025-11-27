<?php

namespace App\Repository;

use App\Entity\ArticleReadLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArticleReadLog>
 */
class ArticleReadLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleReadLog::class);
    }

    /**
     * 记录文章阅读
     */
    public function logArticleRead(int $articleId, int $userId = 0, ?string $sessionId = null, array $additionalData = []): ArticleReadLog
    {
        $readLog = new ArticleReadLog();
        $readLog->setArticleId($articleId);
        $readLog->setUserId($userId);
        $readLog->setSessionId($sessionId);

        // 设置额外数据
        if (isset($additionalData['ip_address'])) {
            $readLog->setIpAddress($additionalData['ip_address']);
        }
        if (isset($additionalData['user_agent'])) {
            $readLog->setUserAgent($additionalData['user_agent']);
            // 自动检测设备类型
            $readLog->setDeviceType($readLog->detectDeviceType());
        }
        if (isset($additionalData['referer'])) {
            $readLog->setReferer($additionalData['referer']);
        }
        if (isset($additionalData['duration_seconds'])) {
            $readLog->setDurationSeconds($additionalData['duration_seconds']);
        }
        if (isset($additionalData['is_completed'])) {
            $readLog->setCompleted($additionalData['is_completed']);
        }

        $this->getEntityManager()->persist($readLog);
        $this->getEntityManager()->flush();

        return $readLog;
    }

    /**
     * 检查用户是否在今天已经阅读过该文章
     */
    public function hasReadToday(int $articleId, int $userId = 0, ?string $sessionId = null): bool
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');

        $qb = $this->createQueryBuilder('log')
            ->select('COUNT(log.id)')
            ->where('log.articleId = :articleId')
            ->andWhere('log.readTime >= :today')
            ->andWhere('log.readTime < :tomorrow');

        if ($userId > 0) {
            $qb->andWhere('log.userId = :userId')
               ->setParameter('userId', $userId);
        } elseif ($sessionId) {
            $qb->andWhere('log.sessionId = :sessionId')
               ->setParameter('sessionId', $sessionId);
        } else {
            // 如果既没有用户ID也没有会话ID，则检查IP地址
            $qb->andWhere('log.ipAddress = :ipAddress')
               ->setParameter('ipAddress', $additionalData['ip_address'] ?? '');
        }

        $qb->setParameter('articleId', $articleId)
           ->setParameter('today', $today)
           ->setParameter('tomorrow', $tomorrow);

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * 获取文章的阅读记录列表
     */
    public function findByArticle(int $articleId, ?int $limit = null, ?int $offset = null): array
    {
        $qb = $this->createQueryBuilder('log')
            ->where('log.articleId = :articleId')
            ->orderBy('log.readTime', 'DESC')
            ->setParameter('articleId', $articleId);

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }
        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 获取用户的阅读记录列表
     */
    public function findByUser(int $userId, ?int $limit = null, ?int $offset = null): array
    {
        $qb = $this->createQueryBuilder('log')
            ->where('log.userId = :userId')
            ->orderBy('log.readTime', 'DESC')
            ->setParameter('userId', $userId);

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }
        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 获取文章的总阅读次数
     */
    public function getTotalReadsByArticle(int $articleId): int
    {
        return (int) $this->createQueryBuilder('log')
            ->select('COUNT(log.id)')
            ->where('log.articleId = :articleId')
            ->setParameter('articleId', $articleId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * 获取文章的独立用户数
     */
    public function getUniqueUsersByArticle(int $articleId): int
    {
        return (int) $this->createQueryBuilder('log')
            ->select('COUNT(DISTINCT CASE WHEN log.userId > 0 THEN log.userId ELSE log.ipAddress END)')
            ->where('log.articleId = :articleId')
            ->setParameter('articleId', $articleId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * 获取文章的阅读统计（按天）
     */
    public function getDailyStatsByArticle(int $articleId, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $sql = '
            SELECT
                DATE(log.read_time) as date,
                COUNT(log.id) as totalReads,
                COUNT(DISTINCT CASE WHEN log.user_id > 0 THEN log.user_id ELSE log.ip_address END) as uniqueUsers,
                SUM(CASE WHEN log.user_id = 0 THEN 1 ELSE 0 END) as anonymousReads,
                SUM(CASE WHEN log.user_id > 0 THEN 1 ELSE 0 END) as registeredReads,
                AVG(log.duration_seconds) as avgDuration,
                SUM(CASE WHEN log.is_completed = true THEN 1 ELSE 0 END) as completedReads
            FROM article_read_logs log
            WHERE log.article_id = :articleId
            AND log.read_time BETWEEN :startDate AND :endDate
            GROUP BY DATE(log.read_time)
            ORDER BY date ASC
        ';

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $result = $stmt->executeQuery([
            'articleId' => $articleId,
            'startDate' => $startDate->format('Y-m-d H:i:s'),
            'endDate' => $endDate->format('Y-m-d H:i:s')
        ]);

        return $result->fetchAllAssociative();
    }

    /**
     * 获取热门文章（按阅读量排序）
     */
    public function getPopularArticles(int $limit = 10, \DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('log')
            ->select([
                'log.articleId',
                'COUNT(log.id) as totalReads',
                'COUNT(DISTINCT CASE WHEN log.userId > 0 THEN log.userId ELSE log.ipAddress END) as uniqueUsers'
            ])
            ->groupBy('log.articleId')
            ->orderBy('totalReads', 'DESC')
            ->setMaxResults($limit);

        if ($startDate && $endDate) {
            $qb->andWhere('log.readTime BETWEEN :startDate AND :endDate')
               ->setParameter('startDate', $startDate)
               ->setParameter('endDate', $endDate);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 获取设备类型统计
     */
    public function getDeviceTypeStatsByArticle(int $articleId): array
    {
        return $this->createQueryBuilder('log')
            ->select([
                'log.deviceType',
                'COUNT(log.id) as count',
                'COUNT(log.id) * 100.0 / SUM(COUNT(log.id)) OVER() as percentage'
            ])
            ->where('log.articleId = :articleId')
            ->groupBy('log.deviceType')
            ->orderBy('count', 'DESC')
            ->setParameter('articleId', $articleId)
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取阅读时长统计
     */
    public function getDurationStatsByArticle(int $articleId): array
    {
        $qb = $this->createQueryBuilder('log')
            ->select([
                'AVG(log.durationSeconds) as avgDuration',
                'MIN(log.durationSeconds) as minDuration',
                'MAX(log.durationSeconds) as maxDuration',
                'COUNT(CASE WHEN log.durationSeconds < 30 THEN 1 END) as under30s',
                'COUNT(CASE WHEN log.durationSeconds BETWEEN 30 AND 60 THEN 1 END) as between30s60s',
                'COUNT(CASE WHEN log.durationSeconds BETWEEN 60 AND 300 THEN 1 END) as between1m5m',
                'COUNT(CASE WHEN log.durationSeconds > 300 THEN 1 END) as over5m'
            ])
            ->where('log.articleId = :articleId')
            ->setParameter('articleId', $articleId)
            ->getQuery()
            ->getSingleResult();

        return $qb;
    }

    /**
     * 清理旧的阅读记录
     */
    public function cleanupOldLogs(\DateTimeInterface $beforeDate): int
    {
        $qb = $this->createQueryBuilder('log')
            ->delete()
            ->where('log.readTime < :beforeDate')
            ->setParameter('beforeDate', $beforeDate);

        return $qb->getQuery()->execute();
    }

    /**
     * 批量更新文章阅读数量
     */
    public function batchUpdateArticleViewCounts(): int
    {
        $sql = '
            UPDATE sys_news_article article
            SET view_count = (
                SELECT COUNT(log.id)
                FROM article_read_logs log
                WHERE log.article_id = article.id
            )
            WHERE EXISTS (
                SELECT 1 FROM article_read_logs log
                WHERE log.article_id = article.id
            )
        ';

        return $this->getEntityManager()->getConnection()->executeStatement($sql);
    }

    /**
     * 获取用户的阅读历史统计
     */
    public function getUserReadingStats(int $userId, \DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('log')
            ->select([
                'COUNT(DISTINCT log.articleId) as articlesRead',
                'COUNT(log.id) as totalReads',
                'AVG(log.durationSeconds) as avgDuration',
                'SUM(CASE WHEN log.isCompleted = true THEN 1 ELSE 0 END) as completedReads'
            ])
            ->where('log.userId = :userId')
            ->setParameter('userId', $userId);

        if ($startDate && $endDate) {
            $qb->andWhere('log.readTime BETWEEN :startDate AND :endDate')
               ->setParameter('startDate', $startDate)
               ->setParameter('endDate', $endDate);
        }

        return $qb->getQuery()->getSingleResult();
    }
}
