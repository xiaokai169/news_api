<?php

namespace App\Repository;

use App\Entity\TaskExecutionLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskExecutionLog>
 */
class TaskExecutionLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskExecutionLog::class);
    }

    /**
     * 根据任务ID查找执行日志
     */
    public function findByTaskId(string $taskId, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('log')
            ->where('log.taskId = :taskId')
            ->setParameter('taskId', $taskId)
            ->orderBy('log.startedAt', 'DESC');

        if ($status) {
            $qb->andWhere('log.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 根据执行ID查找日志
     */
    public function findByExecutionId(string $executionId): ?TaskExecutionLog
    {
        return $this->createQueryBuilder('log')
            ->where('log.executionId = :executionId')
            ->setParameter('executionId', $executionId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 获取任务的最新执行日志
     */
    public function findLatestByTaskId(string $taskId): ?TaskExecutionLog
    {
        return $this->createQueryBuilder('log')
            ->where('log.taskId = :taskId')
            ->setParameter('taskId', $taskId)
            ->orderBy('log.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 获取任务的失败日志
     */
    public function findFailedLogsByTaskId(string $taskId): array
    {
        return $this->createQueryBuilder('log')
            ->where('log.taskId = :taskId')
            ->andWhere('log.status = :status')
            ->setParameter('taskId', $taskId)
            ->setParameter('status', TaskExecutionLog::STATUS_FAILED)
            ->orderBy('log.startedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 分页查询执行日志
     */
    public function findPaginated(
        ?string $taskId = null,
        ?string $status = null,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $qb = $this->createQueryBuilder('log')
            ->orderBy('log.startedAt', 'DESC');

        if ($taskId) {
            $qb->andWhere('log.taskId = :taskId')
               ->setParameter('taskId', $taskId);
        }

        if ($status) {
            $qb->andWhere('log.status = :status')
               ->setParameter('status', $status);
        }

        if ($startDate) {
            $qb->andWhere('log.startedAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('log.startedAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        if ($offset) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 统计执行日志数量
     */
    public function countByFilters(
        ?string $taskId = null,
        ?string $status = null,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): int {
        $qb = $this->createQueryBuilder('log')
            ->select('COUNT(log.id)');

        if ($taskId) {
            $qb->andWhere('log.taskId = :taskId')
               ->setParameter('taskId', $taskId);
        }

        if ($status) {
            $qb->andWhere('log.status = :status')
               ->setParameter('status', $status);
        }

        if ($startDate) {
            $qb->andWhere('log.startedAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('log.startedAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    /**
     * 获取执行统计信息
     */
    public function getExecutionStatistics(
        ?string $taskId = null,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): array {
        $qb = $this->createQueryBuilder('log')
            ->select('log.status', 'COUNT(log.id) as count')
            ->addSelect('AVG(log.durationMs) as avgDuration')
            ->addSelect('MAX(log.durationMs) as maxDuration')
            ->addSelect('AVG(log.memoryUsage) as avgMemory')
            ->addSelect('SUM(log.processedItems) as totalProcessed')
            ->groupBy('log.status');

        if ($taskId) {
            $qb->andWhere('log.taskId = :taskId')
               ->setParameter('taskId', $taskId);
        }

        if ($startDate) {
            $qb->andWhere('log.startedAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('log.startedAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        $results = $qb->getQuery()->getResult();

        $statistics = [
            'by_status' => [],
            'total_executions' => 0,
            'success_rate' => 0,
            'failure_rate' => 0,
            'avg_duration_ms' => null,
            'max_duration_ms' => null,
            'avg_memory_usage' => null,
            'total_processed_items' => 0,
        ];

        $totalExecutions = 0;
        $totalSuccessful = 0;
        $totalFailed = 0;

        foreach ($results as $result) {
            $status = $result['status'];
            $count = (int)$result['count'];

            $statistics['by_status'][$status] = [
                'count' => $count,
                'avg_duration_ms' => $result['avgDuration'] ? round((float)$result['avgDuration']) : null,
                'max_duration_ms' => $result['maxDuration'] ? (int)$result['maxDuration'] : null,
                'avg_memory_usage' => $result['avgMemory'] ? round((float)$result['avgMemory']) : null,
                'total_processed_items' => $result['totalProcessed'] ? (int)$result['totalProcessed'] : 0,
            ];

            $totalExecutions += $count;

            if ($status === TaskExecutionLog::STATUS_COMPLETED) {
                $totalSuccessful = $count;
            } elseif ($status === TaskExecutionLog::STATUS_FAILED) {
                $totalFailed = $count;
            }
        }

        $statistics['total_executions'] = $totalExecutions;

        if ($totalExecutions > 0) {
            $statistics['success_rate'] = round(($totalSuccessful / $totalExecutions) * 100, 2);
            $statistics['failure_rate'] = round(($totalFailed / $totalExecutions) * 100, 2);
        }

        return $statistics;
    }

    /**
     * 获取执行时长统计
     */
    public function getDurationStatistics(
        ?string $taskId = null,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): array {
        $qb = $this->createQueryBuilder('log')
            ->select('AVG(log.durationMs) as avgDuration')
            ->addSelect('MIN(log.durationMs) as minDuration')
            ->addSelect('MAX(log.durationMs) as maxDuration')
            ->addSelect('COUNT(log.id) as totalExecutions')
            ->where('log.durationMs IS NOT NULL');

        if ($taskId) {
            $qb->andWhere('log.taskId = :taskId')
               ->setParameter('taskId', $taskId);
        }

        if ($startDate) {
            $qb->andWhere('log.startedAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('log.startedAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        $result = $qb->getQuery()->getSingleResult();

        return [
            'avg_duration_ms' => $result['avgDuration'] ? round((float)$result['avgDuration']) : null,
            'min_duration_ms' => $result['minDuration'] ? (int)$result['minDuration'] : null,
            'max_duration_ms' => $result['maxDuration'] ? (int)$result['maxDuration'] : null,
            'total_executions' => (int)$result['totalExecutions'],
        ];
    }

    /**
     * 获取内存使用统计
     */
    public function getMemoryUsageStatistics(
        ?string $taskId = null,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): array {
        $qb = $this->createQueryBuilder('log')
            ->select('AVG(log.memoryUsage) as avgMemory')
            ->addSelect('MIN(log.memoryUsage) as minMemory')
            ->addSelect('MAX(log.memoryUsage) as maxMemory')
            ->addSelect('COUNT(log.id) as totalExecutions')
            ->where('log.memoryUsage IS NOT NULL');

        if ($taskId) {
            $qb->andWhere('log.taskId = :taskId')
               ->setParameter('taskId', $taskId);
        }

        if ($startDate) {
            $qb->andWhere('log.startedAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('log.startedAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        $result = $qb->getQuery()->getSingleResult();

        return [
            'avg_memory_bytes' => $result['avgMemory'] ? round((float)$result['avgMemory']) : null,
            'min_memory_bytes' => $result['minMemory'] ? (int)$result['minMemory'] : null,
            'max_memory_bytes' => $result['maxMemory'] ? (int)$result['maxMemory'] : null,
            'total_executions' => (int)$result['totalExecutions'],
        ];
    }

    /**
     * 查找长时间运行的日志
     */
    public function findLongRunningExecutions(int $thresholdMinutes = 60): array
    {
        $threshold = new \DateTime();
        $threshold->modify("-{$thresholdMinutes} minutes");

        return $this->createQueryBuilder('log')
            ->where('log.status IN (:statuses)')
            ->andWhere('log.startedAt < :threshold')
            ->setParameter('statuses', [TaskExecutionLog::STATUS_STARTED, TaskExecutionLog::STATUS_RUNNING])
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }

    /**
     * 清理旧的执行日志
     */
    public function cleanupOldLogs(\DateTimeInterface $beforeDate, int $limit = 1000): int
    {
        $qb = $this->createQueryBuilder('log')
            ->delete()
            ->where('log.startedAt < :beforeDate')
            ->andWhere('log.status IN (:statuses)')
            ->setParameter('beforeDate', $beforeDate)
            ->setParameter('statuses', [
                TaskExecutionLog::STATUS_COMPLETED,
                TaskExecutionLog::STATUS_FAILED,
                TaskExecutionLog::STATUS_CANCELLED
            ])
            ->setMaxResults($limit);

        return $qb->getQuery()->execute();
    }

    /**
     * 获取按日期分组的执行统计
     */
    public function getDailyStatistics(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $qb = $this->createQueryBuilder('log')
            ->select('DATE(log.startedAt) as date')
            ->addSelect('log.status')
            ->addSelect('COUNT(log.id) as count')
            ->addSelect('AVG(log.durationMs) as avgDuration')
            ->where('log.startedAt >= :startDate')
            ->andWhere('log.startedAt <= :endDate')
            ->groupBy('DATE(log.startedAt)', 'log.status')
            ->orderBy('date', 'ASC')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        $results = $qb->getQuery()->getResult();

        $dailyStats = [];
        foreach ($results as $result) {
            $date = $result['date'];
            $status = $result['status'];

            if (!isset($dailyStats[$date])) {
                $dailyStats[$date] = [
                    'date' => $date,
                    'by_status' => [],
                    'total_count' => 0,
                    'avg_duration_ms' => 0,
                ];
            }

            $dailyStats[$date]['by_status'][$status] = [
                'count' => (int)$result['count'],
                'avg_duration_ms' => $result['avgDuration'] ? round((float)$result['avgDuration']) : null,
            ];

            $dailyStats[$date]['total_count'] += (int)$result['count'];
        }

        return array_values($dailyStats);
    }

    /**
     * 保存执行日志
     */
    public function save(TaskExecutionLog $log, bool $flush = true): void
    {
        $this->getEntityManager()->persist($log);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除执行日志
     */
    public function remove(TaskExecutionLog $log, bool $flush = true): void
    {
        $this->getEntityManager()->remove($log);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
