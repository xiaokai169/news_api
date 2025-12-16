<?php

namespace App\Service;

use App\Entity\AsyncTask;
use App\Entity\Official;
use App\Entity\TaskExecutionLog;
use App\Repository\AsyncTaskRepository;
use App\Repository\OfficialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;

/**
 * 数据一致性管理器
 *
 * 负责确保异步任务执行过程中的数据一致性，包括：
 * - 事务管理
 * - 数据校验
 * - 幂等性保证
 * - 数据完整性检查
 * - 回滚机制
 */
class DataConsistencyManager
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private AsyncTaskRepository $taskRepository;
    private OfficialRepository $officialRepository;
    private LockFactory $lockFactory;
    private DistributedLockService $distributedLockService;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        AsyncTaskRepository $taskRepository,
        OfficialRepository $officialRepository,
        DistributedLockService $distributedLockService
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->taskRepository = $taskRepository;
        $this->officialRepository = $officialRepository;
        $this->distributedLockService = $distributedLockService;

        // 初始化Redis锁工厂
        $redisStore = new RedisStore($_ENV['REDIS_URL'] ?? 'redis://localhost:6379');
        $this->lockFactory = new LockFactory($redisStore);
    }

    /**
     * 执行带一致性保证的操作
     */
    public function executeWithConsistency(string $taskId, callable $operation): array
    {
        $lockKey = "data_consistency_{$taskId}";
        $lock = $this->lockFactory->createLock($lockKey, 300); // 5分钟锁定

        try {
            if (!$lock->acquire()) {
                throw new \RuntimeException("无法获取数据一致性锁，任务可能正在执行中");
            }

            $this->logger->info('开始执行数据一致性操作', ['task_id' => $taskId]);

            // 开始事务
            $this->entityManager->beginTransaction();

            try {
                // 执行前数据状态检查
                $beforeState = $this->captureDataState($taskId);

                // 执行操作
                $result = $operation($this);

                // 执行后数据状态检查
                $afterState = $this->captureDataState($taskId);

                // 验证数据一致性
                $consistencyCheck = $this->verifyDataConsistency($beforeState, $afterState, $taskId);

                if (!$consistencyCheck['is_consistent']) {
                    throw new \RuntimeException('数据一致性检查失败: ' . implode(', ', $consistencyCheck['errors']));
                }

                // 提交事务
                $this->entityManager->commit();

                $this->logger->info('数据一致性操作执行成功', [
                    'task_id' => $taskId,
                    'before_state' => $beforeState,
                    'after_state' => $afterState
                ]);

                return [
                    'success' => true,
                    'result' => $result,
                    'consistency_check' => $consistencyCheck
                ];

            } catch (\Exception $e) {
                // 回滚事务
                $this->entityManager->rollback();

                // 执行数据恢复
                $this->rollbackDataState($taskId, $beforeState);

                throw $e;
            }

        } finally {
            $lock->release();
        }
    }

    /**
     * 捕获数据状态快照
     */
    public function captureDataState(string $taskId): array
    {
        $state = [];

        // 捕获任务状态
        $task = $this->taskRepository->find($taskId);
        if ($task) {
            $state['task'] = [
                'id' => $task->getId(),
                'status' => $task->getStatus(),
                'progress' => $task->getProgress(),
                'result_data' => $task->getResultData(),
                'updated_at' => $task->getUpdatedAt()->format('Y-m-d H:i:s')
            ];
        }

        // 捕获相关的Official数据
        if ($task && $task->getTaskType() === 'wechat_sync') {
            $parameters = $task->getParameters();
            if (isset($parameters['account_id'])) {
                $accountId = $parameters['account_id'];
                $officials = $this->officialRepository->findBy(['wechatAccountId' => $accountId]);

                $state['officials'] = array_map(function($official) {
                    return [
                        'id' => $official->getId(),
                        'article_id' => $official->getArticleId(),
                        'title' => $official->getTitle(),
                        'status' => $official->getStatus(),
                        'updated_at' => $official->getUpdatedAt()->format('Y-m-d H:i:s')
                    ];
                }, $officials);
            }
        }

        $state['timestamp'] = date('Y-m-d H:i:s');
        $state['checksum'] = $this->calculateStateChecksum($state);

        return $state;
    }

    /**
     * 验证数据一致性
     */
    public function verifyDataConsistency(array $beforeState, array $afterState, string $taskId): array
    {
        $errors = [];
        $warnings = [];

        // 检查任务状态一致性
        if (isset($beforeState['task']) && isset($afterState['task'])) {
            $beforeTask = $beforeState['task'];
            $afterTask = $afterState['task'];

            // 状态转换验证
            if (!$this->isValidStatusTransition($beforeTask['status'], $afterTask['status'])) {
                $errors[] = "无效的状态转换: {$beforeTask['status']} -> {$afterTask['status']}";
            }

            // 进度验证
            if ($afterTask['progress'] < $beforeTask['progress']) {
                $errors[] = "进度不能倒退: {$beforeTask['progress']} -> {$afterTask['progress']}";
            }

            // 时间戳验证
            if ($afterTask['updated_at'] <= $beforeTask['updated_at']) {
                $warnings[] = "更新时间戳异常: {$beforeTask['updated_at']} -> {$afterTask['updated_at']}";
            }
        }

        // 检查Official数据一致性
        if (isset($beforeState['officials']) && isset($afterState['officials'])) {
            $beforeOfficials = $beforeState['officials'];
            $afterOfficials = $afterState['officials'];

            // 检查数据完整性
            if (count($afterOfficials) < count($beforeOfficials)) {
                $warnings[] = "Official数据数量减少，可能存在数据丢失";
            }

            // 检查重复数据
            $articleIds = array_column($afterOfficials, 'article_id');
            $duplicateIds = array_diff_assoc($articleIds, array_unique($articleIds));
            if (!empty($duplicateIds)) {
                $errors[] = "存在重复的article_id: " . implode(', ', $duplicateIds);
            }
        }

        // 检查数据完整性
        $integrityCheck = $this->checkDataIntegrity($taskId);
        if (!$integrityCheck['is_integrity']) {
            $errors = array_merge($errors, $integrityCheck['errors']);
        }

        return [
            'is_consistent' => empty($errors),
            'warnings' => $warnings,
            'errors' => $errors,
            'integrity_check' => $integrityCheck
        ];
    }

    /**
     * 检查数据完整性
     */
    public function checkDataIntegrity(string $taskId): array
    {
        $errors = [];

        try {
            $task = $this->taskRepository->find($taskId);
            if (!$task) {
                $errors[] = "任务不存在: {$taskId}";
                return ['is_integrity' => false, 'errors' => $errors];
            }

            // 检查任务数据完整性
            if (empty($task->getTaskType())) {
                $errors[] = "任务类型为空";
            }

            if (empty($task->getParameters())) {
                $errors[] = "任务参数为空";
            }

            // 检查微信同步特定完整性
            if ($task->getTaskType() === 'wechat_sync') {
                $parameters = $task->getParameters();
                if (!isset($parameters['account_id'])) {
                    $errors[] = "微信同步缺少account_id参数";
                }

                // 检查关联的Official数据
                if (isset($parameters['account_id'])) {
                    $accountId = $parameters['account_id'];
                    $officials = $this->officialRepository->findBy(['wechatAccountId' => $accountId]);

                    // 检查必要字段
                    foreach ($officials as $official) {
                        if (empty($official->getArticleId())) {
                            $errors[] = "Official记录缺少article_id: {$official->getId()}";
                        }
                        if (empty($official->getTitle())) {
                            $errors[] = "Official记录缺少title: {$official->getId()}";
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            $errors[] = "数据完整性检查异常: " . $e->getMessage();
        }

        return [
            'is_integrity' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * 回滚数据状态
     */
    public function rollbackDataState(string $taskId, array $targetState): bool
    {
        try {
            $this->logger->warning('开始回滚数据状态', [
                'task_id' => $taskId,
                'target_state' => $targetState
            ]);

            $this->entityManager->beginTransaction();

            // 回滚任务状态
            if (isset($targetState['task'])) {
                $task = $this->taskRepository->find($taskId);
                if ($task) {
                    $task->setStatus($targetState['task']['status']);
                    $task->setProgress($targetState['task']['progress']);
                    $task->setResultData($targetState['task']['result_data']);
                    $this->entityManager->persist($task);
                }
            }

            // 回滚Official数据（这里简化处理，实际可能需要更复杂的逻辑）
            if (isset($targetState['officials'])) {
                // 实际实现中可能需要根据具体业务逻辑进行数据回滚
                $this->logger->info('Official数据回滚逻辑需要根据具体业务实现');
            }

            $this->entityManager->commit();

            $this->logger->info('数据状态回滚成功', ['task_id' => $taskId]);

            return true;

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('数据状态回滚失败', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * 确保幂等性
     */
    public function ensureIdempotency(string $taskId, string $operationKey, callable $operation): mixed
    {
        $idempotencyKey = "idempotency_{$taskId}_{$operationKey}";

        // 检查是否已经执行过
        $existingLog = $this->entityManager->getRepository(TaskExecutionLog::class)
            ->findOneBy(['taskId' => $taskId, 'logLevel' => $idempotencyKey]);

        if ($existingLog) {
            $this->logger->info('操作已执行，跳过以保证幂等性', [
                'task_id' => $taskId,
                'operation_key' => $operationKey
            ]);

            return json_decode($existingLog->getLogMessage(), true);
        }

        // 执行操作
        $result = $operation();

        // 记录幂等性日志
        $logEntry = new TaskExecutionLog();
        $logEntry->setTaskId($taskId);
        $logEntry->setLogLevel($idempotencyKey);
        $logEntry->setLogMessage(json_encode($result));
        $logEntry->setCreatedAt(new \DateTime());

        $this->entityManager->persist($logEntry);
        $this->entityManager->flush();

        return $result;
    }

    /**
     * 验证状态转换的有效性
     */
    private function isValidStatusTransition(string $fromStatus, string $toStatus): bool
    {
        $validTransitions = [
            AsyncTask::STATUS_PENDING => [AsyncTask::STATUS_RUNNING, AsyncTask::STATUS_CANCELLED],
            AsyncTask::STATUS_RUNNING => [AsyncTask::STATUS_COMPLETED, AsyncTask::STATUS_FAILED, AsyncTask::STATUS_CANCELLED],
            AsyncTask::STATUS_FAILED => [AsyncTask::STATUS_PENDING, AsyncTask::STATUS_CANCELLED],
            AsyncTask::STATUS_CANCELLED => [AsyncTask::STATUS_PENDING],
            AsyncTask::STATUS_COMPLETED => [] // 完成状态不能再转换
        ];

        return in_array($toStatus, $validTransitions[$fromStatus] ?? []);
    }

    /**
     * 计算状态校验和
     */
    private function calculateStateChecksum(array $state): string
    {
        return md5(json_encode($state, JSON_SORT_KEYS));
    }

    /**
     * 清理过期的幂等性记录
     */
    public function cleanupIdempotencyRecords(int $daysOld = 7): int
    {
        $cutoffDate = new \DateTime();
        $cutoffDate->modify("-{$daysOld} days");

        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(TaskExecutionLog::class, 'log')
           ->where('log.createdAt < :cutoffDate')
           ->andWhere('log.logLevel LIKE :idempotencyPattern')
           ->setParameter('cutoffDate', $cutoffDate)
           ->setParameter('idempotencyPattern', 'idempotency_%');

        return $qb->getQuery()->execute();
    }

    /**
     * 获取数据一致性统计信息
     */
    public function getConsistencyStatistics(): array
    {
        $stats = [];

        // 统计任务状态分布
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t.status, COUNT(t.id) as count')
           ->from(AsyncTask::class, 't')
           ->groupBy('t.status');

        $stats['task_status_distribution'] = $qb->getQuery()->getResult();

        // 统计数据完整性问题
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(o.id) as incomplete_count')
           ->from(Official::class, 'o')
           ->where('o.articleId IS NULL OR o.title IS NULL');

        $stats['incomplete_officials'] = $qb->getQuery()->getSingleScalarResult();

        return $stats;
    }
}
