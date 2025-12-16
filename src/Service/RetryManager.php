<?php

namespace App\Service;

use App\Entity\AsyncTask;
use App\Entity\TaskExecutionLog;
use App\Repository\AsyncTaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * 重试管理器
 *
 * 负责管理异步任务的重试逻辑，包括：
 * - 重试策略配置
 * - 重试队列管理
 * - 重试执行控制
 * - 重试结果跟踪
 */
class RetryManager
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private AsyncTaskRepository $taskRepository;
    private MessageBusInterface $messageBus;
    private ErrorHandler $errorHandler;
    private array $retryConfig;
    private array $retryQueues;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        AsyncTaskRepository $taskRepository,
        MessageBusInterface $messageBus,
        ErrorHandler $errorHandler
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->taskRepository = $taskRepository;
        $this->messageBus = $messageBus;
        $this->errorHandler = $errorHandler;

        $this->initializeRetryConfig();
        $this->initializeRetryQueues();
    }

    /**
     * 安排任务重试
     */
    public function scheduleRetry(AsyncTask $task, array $retryStrategy): bool
    {
        try {
            // 更新任务状态
            $task->setStatus(AsyncTask::STATUS_PENDING);
            $task->setRetryCount($task->getRetryCount() + 1);
            $task->setScheduledAt($retryStrategy['next_retry_at']);
            $task->setUpdatedAt(new \DateTime());

            // 设置重试参数
            $parameters = $task->getParameters();
            $parameters['retry_info'] = [
                'retry_count' => $task->getRetryCount(),
                'last_error' => $retryStrategy['last_error'] ?? null,
                'retry_strategy' => $retryStrategy
            ];
            $task->setParameters($parameters);

            $this->entityManager->flush();

            // 添加到重试队列
            $this->addToRetryQueue($task, $retryStrategy);

            $this->logger->info('任务重试已安排', [
                'task_id' => $task->getId(),
                'retry_count' => $task->getRetryCount(),
                'scheduled_at' => $retryStrategy['next_retry_at']->format('Y-m-d H:i:s'),
                'strategy' => $retryStrategy['strategy'] ?? 'unknown'
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('安排任务重试失败', [
                'task_id' => $task->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * 执行重试任务
     */
    public function executeRetry(AsyncTask $task): array
    {
        $retryCount = $task->getRetryCount();
        $taskId = $task->getId();

        try {
            $this->logger->info('开始执行任务重试', [
                'task_id' => $taskId,
                'retry_count' => $retryCount
            ]);

            // 更新任务状态
            $task->setStatus(AsyncTask::STATUS_RUNNING);
            $task->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();

            // 记录重试开始日志
            $this->logRetryStart($task);

            // 重新发送消息到队列
            $this->resendTaskMessage($task);

            return [
                'success' => true,
                'task_id' => $taskId,
                'retry_count' => $retryCount,
                'executed_at' => date('Y-m-d H:i:s')
            ];

        } catch (\Exception $e) {
            $this->logger->error('任务重试执行失败', [
                'task_id' => $taskId,
                'retry_count' => $retryCount,
                'error' => $e->getMessage()
            ]);

            // 处理重试失败
            return $this->handleRetryFailure($task, $e);
        }
    }

    /**
     * 处理到期的重试任务
     */
    public function processDueRetries(): array
    {
        $dueRetries = $this->taskRepository->findDueRetryTasks();
        $processed = [];

        foreach ($dueRetries as $task) {
            try {
                $result = $this->executeRetry($task);
                $processed[] = $result;

                // 从重试队列中移除
                $this->removeFromRetryQueue($task->getId());

            } catch (\Exception $e) {
                $processed[] = [
                    'success' => false,
                    'task_id' => $task->getId(),
                    'error' => $e->getMessage(),
                    'processed_at' => date('Y-m-d H:i:s')
                ];
            }
        }

        $this->logger->info('到期重试任务处理完成', [
            'total_tasks' => count($dueRetries),
            'processed' => count($processed),
            'successful' => count(array_filter($processed, fn($r) => $r['success']))
        ]);

        return $processed;
    }

    /**
     * 获取重试队列状态
     */
    public function getRetryQueueStatus(): array
    {
        $status = [];

        foreach ($this->retryQueues as $queueName => $queueConfig) {
            $queueTasks = $this->taskRepository->findRetryTasksByQueue($queueName);

            $status[$queueName] = [
                'name' => $queueName,
                'description' => $queueConfig['description'],
                'total_tasks' => count($queueTasks),
                'pending_tasks' => count(array_filter($queueTasks, fn($t) => $t->getStatus() === AsyncTask::STATUS_PENDING)),
                'next_retry_at' => !empty($queueTasks) ? min(array_map(fn($t) => $t->getScheduledAt(), $queueTasks))->format('Y-m-d H:i:s') : null,
                'avg_retry_count' => !empty($queueTasks) ? array_sum(array_map(fn($t) => $t->getRetryCount(), $queueTasks)) / count($queueTasks) : 0
            ];
        }

        return $status;
    }

    /**
     * 获取重试统计信息
     */
    public function getRetryStatistics(array $filters = []): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(t.id) as total_tasks,
                    SUM(t.retryCount) as total_retries,
                    AVG(t.retryCount) as avg_retries,
                    MAX(t.retryCount) as max_retries,
                    COUNT(CASE WHEN t.retryCount > 0 THEN 1 END) as retried_tasks')
           ->from(AsyncTask::class, 't');

        // 应用过滤器
        if (isset($filters['date_from'])) {
            $qb->andWhere('t.createdAt >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime($filters['date_from']));
        }

        if (isset($filters['date_to'])) {
            $qb->andWhere('t.createdAt <= :dateTo')
               ->setParameter('dateTo', new \DateTime($filters['date_to']));
        }

        if (isset($filters['task_type'])) {
            $qb->andWhere('t.taskType = :taskType')
               ->setParameter('taskType', $filters['task_type']);
        }

        $summary = $qb->getQuery()->getSingleResult();

        // 获取重试成功率
        $successRate = $this->getRetrySuccessRate($filters);

        // 获取重试分布
        $distribution = $this->getRetryDistribution($filters);

        return [
            'summary' => $summary,
            'success_rate' => $successRate,
            'distribution' => $distribution
        ];
    }

    /**
     * 清理过期的重试任务
     */
    public function cleanupExpiredRetries(int $daysOld = 30): int
    {
        $cutoffDate = new \DateTime();
        $cutoffDate->modify("-{$daysOld} days");

        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(AsyncTask::class, 't')
           ->where('t.status = :status')
           ->andWhere('t.updatedAt < :cutoffDate')
           ->andWhere('t.retryCount > 0')
           ->setParameter('status', AsyncTask::STATUS_FAILED)
           ->setParameter('cutoffDate', $cutoffDate);

        $deletedCount = $qb->getQuery()->execute();

        $this->logger->info('过期重试任务清理完成', [
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
            'days_old' => $daysOld
        ]);

        return $deletedCount;
    }

    /**
     * 手动重试任务
     */
    public function manualRetry(string $taskId, array $options = []): array
    {
        try {
            $task = $this->taskRepository->find($taskId);
            if (!$task) {
                return [
                    'success' => false,
                    'error' => 'Task not found'
                ];
            }

            // 检查任务状态
            if (!in_array($task->getStatus(), [AsyncTask::STATUS_FAILED, AsyncTask::STATUS_CANCELLED])) {
                return [
                    'success' => false,
                    'error' => 'Task is not in a retryable state'
                ];
            }

            // 重置重试计数（如果指定）
            if (isset($options['reset_retry_count']) && $options['reset_retry_count']) {
                $task->setRetryCount(0);
            }

            // 创建重试策略
            $retryStrategy = [
                'should_retry' => true,
                'strategy' => $options['strategy'] ?? 'manual',
                'delay' => $options['delay'] ?? 0,
                'next_retry_at' => new \DateTime(),
                'max_retries' => $options['max_retries'] ?? ($task->getRetryCount() + 1),
                'manual_retry' => true
            ];

            // 安排重试
            $success = $this->scheduleRetry($task, $retryStrategy);

            return [
                'success' => $success,
                'task_id' => $taskId,
                'retry_strategy' => $retryStrategy,
                'new_retry_count' => $task->getRetryCount()
            ];

        } catch (\Exception $e) {
            $this->logger->error('手动重试任务失败', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 获取重试建议
     */
    public function getRetryRecommendations(AsyncTask $task): array
    {
        $recommendations = [];
        $errorInfo = $this->getLastTaskError($task);

        if (!$errorInfo) {
            return ['recommendations' => [], 'confidence' => 0];
        }

        // 基于错误类型提供建议
        switch ($errorInfo['category']) {
            case 'network':
                $recommendations[] = [
                    'type' => 'increase_delay',
                    'description' => '网络错误，建议增加重试延迟',
                    'suggested_delay' => 30
                ];
                break;

            case 'rate_limit':
                $recommendations[] = [
                    'type' => 'exponential_backoff',
                    'description' => '触发频率限制，建议使用指数退避策略',
                    'base_delay' => 60,
                    'max_delay' => 600
                ];
                break;

            case 'authentication':
                $recommendations[] = [
                    'type' => 'check_credentials',
                    'description' => '认证失败，建议检查配置信息',
                    'action_required' => true
                ];
                break;

            case 'database':
                $recommendations[] = [
                    'type' => 'check_connection',
                    'description' => '数据库错误，建议检查连接状态',
                    'action_required' => true
                ];
                break;
        }

        // 基于重试次数提供建议
        if ($task->getRetryCount() >= 3) {
            $recommendations[] = [
                'type' => 'manual_intervention',
                'description' => '重试次数过多，建议人工介入',
                'priority' => 'high'
            ];
        }

        // 计算建议置信度
        $confidence = $this->calculateRecommendationConfidence($task, $errorInfo, $recommendations);

        return [
            'recommendations' => $recommendations,
            'confidence' => $confidence,
            'error_info' => $errorInfo
        ];
    }

    /**
     * 初始化重试配置
     */
    private function initializeRetryConfig(): void
    {
        $this->retryConfig = [
            'default_max_retries' => 3,
            'default_base_delay' => 10,
            'default_max_delay' => 300,
            'retry_queue_size_limit' => 1000,
            'cleanup_interval' => 3600, // 1小时
            'retry_timeout' => 86400 // 24小时
        ];
    }

    /**
     * 初始化重试队列
     */
    private function initializeRetryQueues(): void
    {
        $this->retryQueues = [
            'high_priority' => [
                'description' => '高优先级重试队列',
                'max_retries' => 5,
                'base_delay' => 5,
                'max_delay' => 60
            ],
            'normal' => [
                'description' => '普通重试队列',
                'max_retries' => 3,
                'base_delay' => 10,
                'max_delay' => 300
            ],
            'low_priority' => [
                'description' => '低优先级重试队列',
                'max_retries' => 2,
                'base_delay' => 30,
                'max_delay' => 600
            ]
        ];
    }

    /**
     * 添加到重试队列
     */
    private function addToRetryQueue(AsyncTask $task, array $retryStrategy): void
    {
        $queueName = $this->determineRetryQueue($task, $retryStrategy);

        // 这里可以实现具体的队列添加逻辑
        // 例如使用 Redis、RabbitMQ 等
        $this->logger->debug('任务添加到重试队列', [
            'task_id' => $task->getId(),
            'queue' => $queueName,
            'scheduled_at' => $retryStrategy['next_retry_at']->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * 从重试队列移除
     */
    private function removeFromRetryQueue(string $taskId): void
    {
        // 这里可以实现具体的队列移除逻辑
        $this->logger->debug('任务从重试队列移除', ['task_id' => $taskId]);
    }

    /**
     * 确定重试队列
     */
    private function determineRetryQueue(AsyncTask $task, array $retryStrategy): string
    {
        $priority = $task->getPriority();

        if ($priority >= 8) {
            return 'high_priority';
        } elseif ($priority >= 5) {
            return 'normal';
        } else {
            return 'low_priority';
        }
    }

    /**
     * 重新发送任务消息
     */
    private function resendTaskMessage(AsyncTask $task): void
    {
        // 创建重试消息
        $message = new \App\Message\WechatSyncMessage([
            'task_id' => $task->getId(),
            'task_type' => $task->getTaskType(),
            'parameters' => $task->getParameters(),
            'retry_count' => $task->getRetryCount(),
            'is_retry' => true
        ]);

        // 发送到消息总线
        $this->messageBus->dispatch($message);
    }

    /**
     * 处理重试失败
     */
    private function handleRetryFailure(AsyncTask $task, \Exception $exception): array
    {
        // 使用错误处理器处理重试失败
        $errorResult = $this->errorHandler->handleTaskError($task, $exception);

        return [
            'success' => false,
            'task_id' => $task->getId(),
            'error' => $exception->getMessage(),
            'error_handling' => $errorResult,
            'failed_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 记录重试开始日志
     */
    private function logRetryStart(AsyncTask $task): void
    {
        $log = new TaskExecutionLog();
        $log->setTaskId($task->getId());
        $log->setLogLevel('retry_start');
        $log->setLogMessage(json_encode([
            'retry_count' => $task->getRetryCount(),
            'scheduled_at' => $task->getScheduledAt()?->format('Y-m-d H:i:s'),
            'parameters' => $task->getParameters()
        ]));
        $log->setCreatedAt(new \DateTime());

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    /**
     * 获取最后的任务错误
     */
    private function getLastTaskError(AsyncTask $task): ?array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('l.logMessage')
           ->from(TaskExecutionLog::class, 'l')
           ->where('l.taskId = :taskId')
           ->andWhere('l.logLevel LIKE :errorPattern')
           ->orderBy('l.createdAt', 'DESC')
           ->setMaxResults(1)
           ->setParameter('taskId', $task->getId())
           ->setParameter('errorPattern', 'error_%');

        $result = $qb->getQuery()->getOneOrNullResult();

        return $result ? json_decode($result['logMessage'], true) : null;
    }

    /**
     * 获取重试成功率
     */
    private function getRetrySuccessRate(array $filters): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(t.id) as total_retried,
                    SUM(CASE WHEN t.status = :completed THEN 1 ELSE 0 END) as successful_retries')
           ->from(AsyncTask::class, 't')
           ->where('t.retryCount > 0')
           ->setParameter('completed', AsyncTask::STATUS_COMPLETED);

        // 应用过滤器
        if (isset($filters['date_from'])) {
            $qb->andWhere('t.createdAt >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime($filters['date_from']));
        }

        if (isset($filters['date_to'])) {
            $qb->andWhere('t.createdAt <= :dateTo')
               ->setParameter('dateTo', new \DateTime($filters['date_to']));
        }

        if (isset($filters['task_type'])) {
            $qb->andWhere('t.taskType = :taskType')
               ->setParameter('taskType', $filters['task_type']);
        }

        $result = $qb->getQuery()->getSingleResult();
        $totalRetried = $result['total_retried'];
        $successfulRetries = $result['successful_retries'];

        $successRate = $totalRetried > 0 ? ($successfulRetries / $totalRetried) * 100 : 0;

        return [
            'total_retried' => $totalRetried,
            'successful_retries' => $successfulRetries,
            'success_rate' => round($successRate, 2)
        ];
    }

    /**
     * 获取重试分布
     */
    private function getRetryDistribution(array $filters): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t.retryCount as retry_count, COUNT(t.id) as task_count')
           ->from(AsyncTask::class, 't')
           ->where('t.retryCount > 0')
           ->groupBy('t.retryCount')
           ->orderBy('t.retryCount', 'ASC');

        // 应用过滤器
        if (isset($filters['date_from'])) {
            $qb->andWhere('t.createdAt >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime($filters['date_from']));
        }

        if (isset($filters['date_to'])) {
            $qb->andWhere('t.createdAt <= :dateTo')
               ->setParameter('dateTo', new \DateTime($filters['date_to']));
        }

        if (isset($filters['task_type'])) {
            $qb->andWhere('t.taskType = :taskType')
               ->setParameter('taskType', $filters['task_type']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 计算建议置信度
     */
    private function calculateRecommendationConfidence(AsyncTask $task, array $errorInfo, array $recommendations): float
    {
        $confidence = 50.0; // 基础置信度

        // 基于错误历史调整
        $errorHistory = $this->getErrorHistory($task->getId());
        if (count($errorHistory) > 2) {
            $confidence += 20.0;
        }

        // 基于重试次数调整
        if ($task->getRetryCount() >= 2) {
            $confidence += 15.0;
        }

        // 基于错误类型调整
        if (in_array($errorInfo['category'], ['network', 'rate_limit'])) {
            $confidence += 10.0; // 这类错误通常可以通过重试解决
        }

        return min($confidence, 100.0);
    }

    /**
     * 获取错误历史
     */
    private function getErrorHistory(string $taskId): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('l.logLevel, l.createdAt')
           ->from(TaskExecutionLog::class, 'l')
           ->where('l.taskId = :taskId')
           ->andWhere('l.logLevel LIKE :errorPattern')
           ->orderBy('l.createdAt', 'DESC')
           ->setMaxResults(5)
           ->setParameter('taskId', $taskId)
           ->setParameter('errorPattern', 'error_%');

        return $qb->getQuery()->getResult();
    }
}
