<?php

namespace App\Repository;

use App\Entity\QueueStatistics;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QueueStatistics>
 */
class QueueStatisticsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QueueStatistics::class);
    }

    /**
     * 根据队列名称查找统计数据
     */
    public function findByQueueName(string $queueName, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('stats')
            ->where('stats.queueName = :queueName')
            ->setParameter('queueName', $queueName)
            ->orderBy('stats.statDate', 'DESC')
            ->addOrderBy('stats.statHour', 'DESC');

        if ($startDate) {
            $qb->andWhere('stats.statDate >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('stats.statDate <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 根据日期范围查找统计数据
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate, ?string $queueName = null): array
    {
        $qb = $this->createQueryBuilder('stats')
            ->where('stats.statDate >= :startDate')
            ->andWhere('stats.statDate <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('stats.statDate', 'DESC')
            ->addOrderBy('stats.statHour', 'DESC');

        if ($queueName) {
            $qb->andWhere('stats.queueName = :queueName')
               ->setParameter('queueName', $queueName);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 获取或创建当前小时的统计记录
     */
    public function findOrCreateCurrentHour(string $queueName): QueueStatistics
    {
        $now = new \DateTime();
        $stat = $this->createQueryBuilder('stats')
            ->where('stats.queueName = :queueName')
            ->andWhere('stats.statDate = :statDate')
            ->andWhere('stats.statHour = :statHour')
            ->setParameter('queueName', $queueName)
            ->setParameter('statDate', $now->format('Y-m-d'))
            ->setParameter('statHour', (int)$now->format('H'))
            ->getQuery()
            ->getOneOrNullResult();

        if (!$stat) {
            $stat = QueueStatistics::createForCurrentHour($queueName);
            $this->save($stat);
        }

        return $stat;
    }

    /**
     * 获取指定日期和小时的统计记录
     */
    public function findByDateAndHour(\DateTimeInterface $date, int $hour, string $queueName): ?QueueStatistics
    {
        return $this->createQueryBuilder('stats')
            ->where('stats.queueName = :queueName')
            ->andWhere('stats.statDate = :statDate')
            ->andWhere('stats.statHour = :statHour')
            ->setParameter('queueName', $queueName)
            ->setParameter('statDate', $date->format('Y-m-d'))
            ->setParameter('statHour', $hour)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 获取队列的汇总统计
     */
    public function getQueueSummary(string $queueName, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('stats')
            ->select('SUM(stats.enqueuedCount) as totalEnqueued')
            ->addSelect('SUM(stats.dequeuedCount) as totalDequeued')
            ->addSelect('SUM(stats.completedCount) as totalCompleted')
            ->addSelect('SUM(stats.failedCount) as totalFailed')
            ->addSelect('AVG(stats.avgDurationMs) as avgDurationMs')
            ->addSelect('MAX(stats.maxDurationMs) as maxDurationMs')
            ->where('stats.queueName = :queueName')
            ->setParameter('queueName', $queueName);

        if ($startDate) {
            $qb->andWhere('stats.statDate >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('stats.statDate <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        $result = $qb->getQuery()->getSingleResult();

        $totalEnqueued = (int)$result['totalEnqueued'];
        $totalCompleted = (int)$result['totalCompleted'];
        $totalFailed = (int)$result['totalFailed'];
        $totalProcessed = $totalCompleted + $totalFailed;

        return [
            'queue_name' => $queueName,
            'total_enqueued' => $totalEnqueued,
            'total_dequeued' => (int)$result['totalDequeued'],
            'total_completed' => $totalCompleted,
            'total_failed' => $totalFailed,
            'total_processed' => $totalProcessed,
            'success_rate' => $totalProcessed > 0 ? round(($totalCompleted / $totalProcessed) * 100, 2) : 0,
            'failure_rate' => $totalProcessed > 0 ? round(($totalFailed / $totalProcessed) * 100, 2) : 0,
            'avg_duration_ms' => $result['avgDurationMs'] ? round((float)$result['avgDurationMs']) : null,
            'max_duration_ms' => $result['maxDurationMs'] ? (int)$result['maxDurationMs'] : null,
        ];
    }

    /**
     * 获取所有队列的统计概览
     */
    public function getAllQueuesOverview(?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('stats')
            ->select('stats.queueName')
            ->addSelect('SUM(stats.enqueuedCount) as totalEnqueued')
            ->addSelect('SUM(stats.dequeuedCount) as totalDequeued')
            ->addSelect('SUM(stats.completedCount) as totalCompleted')
            ->addSelect('SUM(stats.failedCount) as totalFailed')
            ->addSelect('AVG(stats.avgDurationMs) as avgDurationMs')
            ->addSelect('MAX(stats.maxDurationMs) as maxDurationMs')
            ->groupBy('stats.queueName')
            ->orderBy('stats.queueName', 'ASC');

        if ($startDate) {
            $qb->andWhere('stats.statDate >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('stats.statDate <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        $results = $qb->getQuery()->getResult();

        $overview = [];
        foreach ($results as $result) {
            $queueName = $result['queueName'];
            $totalEnqueued = (int)$result['totalEnqueued'];
            $totalCompleted = (int)$result['totalCompleted'];
            $totalFailed = (int)$result['totalFailed'];
            $totalProcessed = $totalCompleted + $totalFailed;

            $overview[$queueName] = [
                'queue_name' => $queueName,
                'total_enqueued' => $totalEnqueued,
                'total_dequeued' => (int)$result['totalDequeued'],
                'total_completed' => $totalCompleted,
                'total_failed' => $totalFailed,
                'total_processed' => $totalProcessed,
                'success_rate' => $totalProcessed > 0 ? round(($totalCompleted / $totalProcessed) * 100, 2) : 0,
                'failure_rate' => $totalProcessed > 0 ? round(($totalFailed / $totalProcessed) * 100, 2) : 0,
                'avg_duration_ms' => $result['avgDurationMs'] ? round((float)$result['avgDurationMs']) : null,
                'max_duration_ms' => $result['maxDurationMs'] ? (int)$result['maxDurationMs'] : null,
            ];
        }

        return array_values($overview);
    }

    /**
     * 获取按日期分组的统计
     */
    public function getDailyStatistics(string $queueName, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $qb = $this->createQueryBuilder('stats')
            ->select('stats.statDate as date')
            ->addSelect('SUM(stats.enqueuedCount) as totalEnqueued')
            ->addSelect('SUM(stats.dequeuedCount) as totalDequeued')
            ->addSelect('SUM(stats.completedCount) as totalCompleted')
            ->addSelect('SUM(stats.failedCount) as totalFailed')
            ->addSelect('AVG(stats.avgDurationMs) as avgDurationMs')
            ->addSelect('MAX(stats.maxDurationMs) as maxDurationMs')
            ->where('stats.queueName = :queueName')
            ->andWhere('stats.statDate >= :startDate')
            ->andWhere('stats.statDate <= :endDate')
            ->groupBy('stats.statDate')
            ->orderBy('stats.statDate', 'ASC')
            ->setParameter('queueName', $queueName)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        $results = $qb->getQuery()->getResult();

        $dailyStats = [];
        foreach ($results as $result) {
            $date = $result['date'];
            $totalEnqueued = (int)$result['totalEnqueued'];
            $totalCompleted = (int)$result['totalCompleted'];
            $totalFailed = (int)$result['totalFailed'];
            $totalProcessed = $totalCompleted + $totalFailed;

            $dailyStats[] = [
                'date' => $date,
                'total_enqueued' => $totalEnqueued,
                'total_dequeued' => (int)$result['totalDequeued'],
                'total_completed' => $totalCompleted,
                'total_failed' => $totalFailed,
                'total_processed' => $totalProcessed,
                'success_rate' => $totalProcessed > 0 ? round(($totalCompleted / $totalProcessed) * 100, 2) : 0,
                'failure_rate' => $totalProcessed > 0 ? round(($totalFailed / $totalProcessed) * 100, 2) : 0,
                'avg_duration_ms' => $result['avgDurationMs'] ? round((float)$result['avgDurationMs']) : null,
                'max_duration_ms' => $result['maxDurationMs'] ? (int)$result['maxDurationMs'] : null,
            ];
        }

        return $dailyStats;
    }

    /**
     * 获取按小时分组的统计（指定日期）
     */
    public function getHourlyStatistics(string $queueName, \DateTimeInterface $date): array
    {
        $qb = $this->createQueryBuilder('stats')
            ->select('stats.statHour as hour')
            ->addSelect('stats.enqueuedCount as enqueuedCount')
            ->addSelect('stats.dequeuedCount as dequeuedCount')
            ->addSelect('stats.completedCount as completedCount')
            ->addSelect('stats.failedCount as failedCount')
            ->addSelect('stats.avgDurationMs as avgDurationMs')
            ->addSelect('stats.maxDurationMs as maxDurationMs')
            ->where('stats.queueName = :queueName')
            ->andWhere('stats.statDate = :statDate')
            ->orderBy('stats.statHour', 'ASC')
            ->setParameter('queueName', $queueName)
            ->setParameter('statDate', $date->format('Y-m-d'));

        $results = $qb->getQuery()->getResult();

        $hourlyStats = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hourlyStats[$hour] = [
                'hour' => $hour,
                'enqueued_count' => 0,
                'dequeued_count' => 0,
                'completed_count' => 0,
                'failed_count' => 0,
                'total_processed' => 0,
                'success_rate' => 0,
                'failure_rate' => 0,
                'avg_duration_ms' => null,
                'max_duration_ms' => null,
            ];
        }

        foreach ($results as $result) {
            $hour = (int)$result['hour'];
            $completedCount = (int)$result['completedCount'];
            $failedCount = (int)$result['failedCount'];
            $totalProcessed = $completedCount + $failedCount;

            $hourlyStats[$hour] = [
                'hour' => $hour,
                'enqueued_count' => (int)$result['enqueuedCount'],
                'dequeued_count' => (int)$result['dequeuedCount'],
                'completed_count' => $completedCount,
                'failed_count' => $failedCount,
                'total_processed' => $totalProcessed,
                'success_rate' => $totalProcessed > 0 ? round(($completedCount / $totalProcessed) * 100, 2) : 0,
                'failure_rate' => $totalProcessed > 0 ? round(($failedCount / $totalProcessed) * 100, 2) : 0,
                'avg_duration_ms' => $result['avgDurationMs'] ? round((float)$result['avgDurationMs']) : null,
                'max_duration_ms' => $result['maxDurationMs'] ? (int)$result['maxDurationMs'] : null,
            ];
        }

        return array_values($hourlyStats);
    }

    /**
     * 获取性能指标
     */
    public function getPerformanceMetrics(string $queueName, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('stats')
            ->select('AVG(stats.avgDurationMs) as avgDurationMs')
            ->addSelect('MAX(stats.maxDurationMs) as maxDurationMs')
            ->addSelect('MIN(stats.avgDurationMs) as minAvgDurationMs')
            ->addSelect('SUM(stats.completedCount) as totalCompleted')
            ->addSelect('SUM(stats.failedCount) as totalFailed')
            ->addSelect('SUM(stats.enqueuedCount) as totalEnqueued')
            ->where('stats.queueName = :queueName')
            ->andWhere('stats.avgDurationMs IS NOT NULL')
            ->setParameter('queueName', $queueName);

        if ($startDate) {
            $qb->andWhere('stats.statDate >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('stats.statDate <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        $result = $qb->getQuery()->getSingleResult();

        $totalCompleted = (int)$result['totalCompleted'];
        $totalFailed = (int)$result['totalFailed'];
        $totalProcessed = $totalCompleted + $totalFailed;

        return [
            'queue_name' => $queueName,
            'avg_duration_ms' => $result['avgDurationMs'] ? round((float)$result['avgDurationMs']) : null,
            'max_duration_ms' => $result['maxDurationMs'] ? (int)$result['maxDurationMs'] : null,
            'min_avg_duration_ms' => $result['minAvgDurationMs'] ? round((float)$result['minAvgDurationMs']) : null,
            'total_completed' => $totalCompleted,
            'total_failed' => $totalFailed,
            'total_processed' => $totalProcessed,
            'success_rate' => $totalProcessed > 0 ? round(($totalCompleted / $totalProcessed) * 100, 2) : 0,
            'failure_rate' => $totalProcessed > 0 ? round(($totalFailed / $totalProcessed) * 100, 2) : 0,
            'throughput' => $totalProcessed > 0 ? round($totalProcessed / max(1, $result['avgDurationMs'] / 1000), 2) : 0,
        ];
    }

    /**
     * 清理旧的统计数据
     */
    public function cleanupOldStatistics(\DateTimeInterface $beforeDate, int $limit = 1000): int
    {
        $qb = $this->createQueryBuilder('stats')
            ->delete()
            ->where('stats.statDate < :beforeDate')
            ->setParameter('beforeDate', $beforeDate)
            ->setMaxResults($limit);

        return $qb->getQuery()->execute();
    }

    /**
     * 保存统计数据
     */
    public function save(QueueStatistics $statistics, bool $flush = true): void
    {
        $this->getEntityManager()->persist($statistics);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除统计数据
     */
    public function remove(QueueStatistics $statistics, bool $flush = true): void
    {
        $this->getEntityManager()->remove($statistics);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
