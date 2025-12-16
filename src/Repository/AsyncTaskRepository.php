<?php

namespace App\Repository;

use App\Entity\AsyncTask;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AsyncTask>
 */
class AsyncTaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AsyncTask::class);
    }

    /**
     * 查找可运行的任务（按优先级排序）
     */
    public function findRunnableTasks(?string $queueName = null, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('(t.expiresAt IS NULL OR t.expiresAt > :now)')
            ->setParameter('status', AsyncTask::STATUS_PENDING)
            ->setParameter('now', new \DateTime())
            ->orderBy('t.priority', 'DESC')
            ->addOrderBy('t.createdAt', 'ASC')
            ->setMaxResults($limit);

        if ($queueName) {
            $qb->andWhere('t.queueName = :queueName')
               ->setParameter('queueName', $queueName);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 根据状态查找任务
     */
    public function findByStatus(string $status, ?string $queueName = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->setParameter('status', $status)
            ->orderBy('t.createdAt', 'DESC');

        if ($queueName) {
            $qb->andWhere('t.queueName = :queueName')
               ->setParameter('queueName', $queueName);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 根据类型查找任务
     */
    public function findByType(string $type, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.type = :type')
            ->setParameter('type', $type)
            ->orderBy('t.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 分页查询任务
     */
    public function findPaginated(
        ?string $status = null,
        ?string $type = null,
        ?string $queueName = null,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $status);
        }

        if ($type) {
            $qb->andWhere('t.type = :type')
               ->setParameter('type', $type);
        }

        if ($queueName) {
            $qb->andWhere('t.queueName = :queueName')
               ->setParameter('queueName', $queueName);
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
     * 统计任务数量
     */
    public function countByFilters(
        ?string $status = null,
        ?string $type = null,
        ?string $queueName = null
    ): int {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)');

        if ($status) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $status);
        }

        if ($type) {
            $qb->andWhere('t.type = :type')
               ->setParameter('type', $type);
        }

        if ($queueName) {
            $qb->andWhere('t.queueName = :queueName')
               ->setParameter('queueName', $queueName);
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    /**
     * 查找过期的任务
     */
    public function findExpiredTasks(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.expiresAt IS NOT NULL')
            ->andWhere('t.expiresAt <= :now')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('now', new \DateTime())
            ->setParameter('statuses', [AsyncTask::STATUS_PENDING, AsyncTask::STATUS_RUNNING])
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找可以重试的失败任务
     */
    public function findRetryableTasks(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('t.retryCount < t.maxRetries')
            ->setParameter('status', AsyncTask::STATUS_FAILED)
            ->orderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 批量更新任务状态
     */
    public function batchUpdateStatus(array $taskIds, string $status): int
    {
        if (empty($taskIds)) {
            return 0;
        }

        $qb = $this->createQueryBuilder('t')
            ->update()
            ->set('t.status', ':status')
            ->where('t.id IN (:taskIds)')
            ->setParameter('status', $status)
            ->setParameter('taskIds', $taskIds);

        if ($status === AsyncTask::STATUS_RUNNING) {
            $qb->set('t.startedAt', ':startedAt')
               ->setParameter('startedAt', new \DateTime());
        } elseif (in_array($status, [AsyncTask::STATUS_COMPLETED, AsyncTask::STATUS_FAILED, AsyncTask::STATUS_CANCELLED])) {
            $qb->set('t.completedAt', ':completedAt')
               ->setParameter('completedAt', new \DateTime());
        }

        return $qb->getQuery()->execute();
    }

    /**
     * 获取队列统计信息
     */
    public function getQueueStatistics(?string $queueName = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('t.status', 'COUNT(t.id) as count')
            ->groupBy('t.status');

        if ($queueName) {
            $qb->andWhere('t.queueName = :queueName')
               ->setParameter('queueName', $queueName);
        }

        $results = $qb->getQuery()->getResult();

        $statistics = [
            AsyncTask::STATUS_PENDING => 0,
            AsyncTask::STATUS_RUNNING => 0,
            AsyncTask::STATUS_COMPLETED => 0,
            AsyncTask::STATUS_FAILED => 0,
            AsyncTask::STATUS_CANCELLED => 0,
            AsyncTask::STATUS_RETRYING => 0,
            'total' => 0,
        ];

        foreach ($results as $result) {
            $statistics[$result['status']] = (int)$result['count'];
            $statistics['total'] += (int)$result['count'];
        }

        return $statistics;
    }

    /**
     * 获取任务执行时长统计
     */
    public function getExecutionDurationStats(?string $queueName = null, ?\DateTimeInterface $since = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('AVG(TIMESTAMPDIFF(SECOND, t.startedAt, t.completedAt)) as avgDuration')
            ->addSelect('MIN(TIMESTAMPDIFF(SECOND, t.startedAt, t.completedAt)) as minDuration')
            ->addSelect('MAX(TIMESTAMPDIFF(SECOND, t.startedAt, t.completedAt)) as maxDuration')
            ->addSelect('COUNT(t.id) as totalTasks')
            ->where('t.status IN (:statuses)')
            ->andWhere('t.startedAt IS NOT NULL')
            ->andWhere('t.completedAt IS NOT NULL')
            ->setParameter('statuses', [AsyncTask::STATUS_COMPLETED, AsyncTask::STATUS_FAILED]);

        if ($queueName) {
            $qb->andWhere('t.queueName = :queueName')
               ->setParameter('queueName', $queueName);
        }

        if ($since) {
            $qb->andWhere('t.createdAt >= :since')
               ->setParameter('since', $since);
        }

        $result = $qb->getQuery()->getSingleResult();

        return [
            'avg_duration' => $result['avgDuration'] ? round((float)$result['avgDuration'], 2) : null,
            'min_duration' => $result['minDuration'] ? (int)$result['minDuration'] : null,
            'max_duration' => $result['maxDuration'] ? (int)$result['maxDuration'] : null,
            'total_tasks' => (int)$result['totalTasks'],
        ];
    }

    /**
     * 清理旧任务记录
     */
    public function cleanupOldTasks(\DateTimeInterface $beforeDate, int $limit = 1000): int
    {
        $qb = $this->createQueryBuilder('t')
            ->delete()
            ->where('t.createdAt < :beforeDate')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('beforeDate', $beforeDate)
            ->setParameter('statuses', [AsyncTask::STATUS_COMPLETED, AsyncTask::STATUS_FAILED, AsyncTask::STATUS_CANCELLED])
            ->setMaxResults($limit);

        return $qb->getQuery()->execute();
    }

    /**
     * 查找长时间运行的任务
     */
    public function findLongRunningTasks(int $thresholdMinutes = 60): array
    {
        $threshold = new \DateTime();
        $threshold->modify("-{$thresholdMinutes} minutes");

        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('t.startedAt < :threshold')
            ->setParameter('status', AsyncTask::STATUS_RUNNING)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }

    /**
     * 保存任务
     */
    public function save(AsyncTask $task, bool $flush = true): void
    {
        $this->getEntityManager()->persist($task);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除任务
     */
    public function remove(AsyncTask $task, bool $flush = true): void
    {
        $this->getEntityManager()->remove($task);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
