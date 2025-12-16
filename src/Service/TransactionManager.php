<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;

/**
 * 事务管理器
 *
 * 负责管理数据库事务，确保数据操作的原子性和一致性：
 * - 分布式事务管理
 * - 事务嵌套支持
 * - 自动回滚机制
 * - 事务超时控制
 * - 死锁检测和处理
 */
class TransactionManager
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private LockFactory $lockFactory;
    private array $activeTransactions = [];
    private int $transactionTimeout = 300; // 5分钟默认超时

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;

        // 初始化Redis锁工厂用于分布式事务
        $redisStore = new RedisStore($_ENV['REDIS_URL'] ?? 'redis://localhost:6379');
        $this->lockFactory = new LockFactory($redisStore);
    }

    /**
     * 执行事务
     */
    public function executeTransaction(callable $operation, string $transactionId = null): mixed
    {
        $transactionId = $transactionId ?? $this->generateTransactionId();
        $lockKey = "transaction_{$transactionId}";

        $lock = $this->lockFactory->createLock($lockKey, $this->transactionTimeout);

        try {
            if (!$lock->acquire()) {
                throw new \RuntimeException("无法获取事务锁: {$transactionId}");
            }

            $this->logger->info('开始事务', ['transaction_id' => $transactionId]);

            // 记录事务开始
            $this->activeTransactions[$transactionId] = [
                'start_time' => microtime(true),
                'status' => 'running'
            ];

            // 开始数据库事务
            $this->entityManager->beginTransaction();

            try {
                // 执行操作
                $result = $operation($this, $transactionId);

                // 提交事务
                $this->entityManager->commit();

                // 记录事务成功
                $this->activeTransactions[$transactionId]['status'] = 'committed';
                $this->activeTransactions[$transactionId]['end_time'] = microtime(true);

                $this->logger->info('事务提交成功', [
                    'transaction_id' => $transactionId,
                    'duration' => $this->getTransactionDuration($transactionId)
                ]);

                return $result;

            } catch (\Exception $e) {
                // 回滚事务
                $this->entityManager->rollback();

                // 记录事务失败
                $this->activeTransactions[$transactionId]['status'] = 'rolled_back';
                $this->activeTransactions[$transactionId]['error'] = $e->getMessage();
                $this->activeTransactions[$transactionId]['end_time'] = microtime(true);

                $this->logger->error('事务回滚', [
                    'transaction_id' => $transactionId,
                    'error' => $e->getMessage(),
                    'duration' => $this->getTransactionDuration($transactionId)
                ]);

                throw $e;
            }

        } finally {
            $lock->release();
            // 清理事务记录
            unset($this->activeTransactions[$transactionId]);
        }
    }

    /**
     * 执行分布式事务
     */
    public function executeDistributedTransaction(array $operations, string $transactionId = null): array
    {
        $transactionId = $transactionId ?? $this->generateTransactionId();
        $results = [];
        $rollbackOperations = [];

        $this->logger->info('开始分布式事务', [
            'transaction_id' => $transactionId,
            'operations_count' => count($operations)
        ]);

        try {
            foreach ($operations as $index => $operation) {
                $operationId = "{$transactionId}_{$index}";

                try {
                    // 执行单个操作
                    $result = $this->executeOperation($operation, $operationId);
                    $results[$index] = $result;

                    // 记录回滚操作
                    if (isset($operation['rollback'])) {
                        $rollbackOperations[$index] = $operation['rollback'];
                    }

                } catch (\Exception $e) {
                    $this->logger->error('分布式事务操作失败', [
                        'transaction_id' => $transactionId,
                        'operation_index' => $index,
                        'error' => $e->getMessage()
                    ]);

                    // 执行回滚
                    $this->executeRollback($rollbackOperations, $transactionId);
                    throw $e;
                }
            }

            $this->logger->info('分布式事务成功', [
                'transaction_id' => $transactionId,
                'operations_completed' => count($results)
            ]);

            return [
                'success' => true,
                'results' => $results,
                'transaction_id' => $transactionId
            ];

        } catch (\Exception $e) {
            $this->logger->error('分布式事务失败', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId
            ];
        }
    }

    /**
     * 执行单个操作
     */
    private function executeOperation(array $operation, string $operationId): mixed
    {
        if (!isset($operation['execute']) || !is_callable($operation['execute'])) {
            throw new \InvalidArgumentException("操作必须包含可执行的execute回调");
        }

        return $this->executeTransaction(
            $operation['execute'],
            $operationId
        );
    }

    /**
     * 执行回滚操作
     */
    private function executeRollback(array $rollbackOperations, string $transactionId): void
    {
        $this->logger->warning('开始执行回滚操作', [
            'transaction_id' => $transactionId,
            'rollback_operations_count' => count($rollbackOperations)
        ]);

        // 按相反顺序执行回滚
        $rollbackOperations = array_reverse($rollbackOperations, true);

        foreach ($rollbackOperations as $index => $rollback) {
            if (is_callable($rollback)) {
                try {
                    $rollback($this);
                    $this->logger->info('回滚操作成功', [
                        'transaction_id' => $transactionId,
                        'operation_index' => $index
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error('回滚操作失败', [
                        'transaction_id' => $transactionId,
                        'operation_index' => $index,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * 批量数据库操作
     */
    public function executeBatchOperation(array $entities, string $operation, string $transactionId = null): array
    {
        $transactionId = $transactionId ?? $this->generateTransactionId();
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        return $this->executeTransaction(function($transactionManager) use ($entities, $operation, &$results) {
            foreach ($entities as $index => $entity) {
                try {
                    switch ($operation) {
                        case 'persist':
                            $this->entityManager->persist($entity);
                            break;
                        case 'remove':
                            $this->entityManager->remove($entity);
                            break;
                        case 'update':
                            $this->entityManager->merge($entity);
                            break;
                        default:
                            throw new \InvalidArgumentException("不支持的操作类型: {$operation}");
                    }

                    $results['success']++;

                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][$index] = $e->getMessage();

                    $this->logger->error('批量操作失败', [
                        'entity_index' => $index,
                        'operation' => $operation,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // 执行批量操作
            if ($results['success'] > 0) {
                $this->entityManager->flush();
            }

            return $results;

        }, $transactionId);
    }

    /**
     * 检查死锁
     */
    public function detectDeadlock(\Exception $exception): bool
    {
        $errorMessage = $exception->getMessage();

        // MySQL死锁检测
        if (strpos($errorMessage, 'Deadlock') !== false ||
            strpos($errorMessage, 'deadlock') !== false ||
            strpos($errorMessage, 'lock wait timeout') !== false) {
            return true;
        }

        // PostgreSQL死锁检测
        if (strpos($errorMessage, 'deadlock detected') !== false) {
            return true;
        }

        return false;
    }

    /**
     * 处理死锁重试
     */
    public function handleDeadlockRetry(callable $operation, int $maxRetries = 3): mixed
    {
        $retryCount = 0;

        while ($retryCount < $maxRetries) {
            try {
                return $operation();
            } catch (\Exception $e) {
                if ($this->detectDeadlock($e) && $retryCount < $maxRetries - 1) {
                    $retryCount++;
                    $waitTime = mt_rand(100, 1000) * 1000; // 100ms-1000ms

                    $this->logger->warning('检测到死锁，准备重试', [
                        'retry_count' => $retryCount,
                        'max_retries' => $maxRetries,
                        'wait_time_ms' => $waitTime / 1000,
                        'error' => $e->getMessage()
                    ]);

                    usleep($waitTime);
                    continue;
                }

                throw $e;
            }
        }

        throw new \RuntimeException("死锁重试失败，已达到最大重试次数: {$maxRetries}");
    }

    /**
     * 创建保存点
     */
    public function createSavepoint(string $savepointName): void
    {
        try {
            $this->entityManager->getConnection()->createSavepoint($savepointName);
            $this->logger->debug('创建保存点', ['savepoint_name' => $savepointName]);
        } catch (\Exception $e) {
            $this->logger->error('创建保存点失败', [
                'savepoint_name' => $savepointName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 回滚到保存点
     */
    public function rollbackToSavepoint(string $savepointName): void
    {
        try {
            $this->entityManager->getConnection()->rollbackToSavepoint($savepointName);
            $this->logger->debug('回滚到保存点', ['savepoint_name' => $savepointName]);
        } catch (\Exception $e) {
            $this->logger->error('回滚到保存点失败', [
                'savepoint_name' => $savepointName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 释放保存点
     */
    public function releaseSavepoint(string $savepointName): void
    {
        try {
            $this->entityManager->getConnection()->releaseSavepoint($savepointName);
            $this->logger->debug('释放保存点', ['savepoint_name' => $savepointName]);
        } catch (\Exception $e) {
            $this->logger->error('释放保存点失败', [
                'savepoint_name' => $savepointName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 获取事务状态
     */
    public function getTransactionStatus(): string
    {
        try {
            return $this->entityManager->getConnection()->getTransactionNestingLevel() > 0
                ? 'active'
                : 'inactive';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }

    /**
     * 获取活动事务信息
     */
    public function getActiveTransactions(): array
    {
        return $this->activeTransactions;
    }

    /**
     * 设置事务超时时间
     */
    public function setTransactionTimeout(int $timeout): void
    {
        $this->transactionTimeout = $timeout;
    }

    /**
     * 获取事务超时时间
     */
    public function getTransactionTimeout(): int
    {
        return $this->transactionTimeout;
    }

    /**
     * 生成事务ID
     */
    private function generateTransactionId(): string
    {
        return 'txn_' . uniqid() . '_' . mt_rand(1000, 9999);
    }

    /**
     * 获取事务执行时间
     */
    private function getTransactionDuration(string $transactionId): float
    {
        if (!isset($this->activeTransactions[$transactionId])) {
            return 0;
        }

        $startTime = $this->activeTransactions[$transactionId]['start_time'];
        $endTime = $this->activeTransactions[$transactionId]['end_time'] ?? microtime(true);

        return round($endTime - $startTime, 4);
    }

    /**
     * 清理超时事务
     */
    public function cleanupTimeoutTransactions(): int
    {
        $cleanedCount = 0;
        $currentTime = microtime(true);

        foreach ($this->activeTransactions as $transactionId => $transaction) {
            if ($transaction['status'] === 'running') {
                $duration = $currentTime - $transaction['start_time'];
                if ($duration > $this->transactionTimeout) {
                    $this->logger->warning('发现超时事务', [
                        'transaction_id' => $transactionId,
                        'duration' => $duration,
                        'timeout' => $this->transactionTimeout
                    ]);

                    unset($this->activeTransactions[$transactionId]);
                    $cleanedCount++;
                }
            }
        }

        return $cleanedCount;
    }

    /**
     * 获取事务统计信息
     */
    public function getTransactionStatistics(): array
    {
        $stats = [
            'total_transactions' => 0,
            'active_transactions' => 0,
            'committed_transactions' => 0,
            'rolled_back_transactions' => 0,
            'average_duration' => 0,
            'timeout_transactions' => 0
        ];

        // 这里可以集成实际的统计逻辑
        // 例如从日志或数据库中获取历史统计信息

        return $stats;
    }
}
