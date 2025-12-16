<?php

namespace App\Service;

use App\Entity\AsyncTask;
use App\Entity\TaskExecutionLog;
use App\Repository\AsyncTaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * 错误处理器
 *
 * 负责统一处理异步任务执行过程中的各种错误，包括：
 * - 错误分类和识别
 * - 智能重试策略
 * - 错误恢复机制
 * - 死信队列处理
 */
class ErrorHandler
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private AsyncTaskRepository $taskRepository;
    private ParameterBagInterface $parameterBag;
    private array $errorCategories;
    private array $retryStrategies;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        AsyncTaskRepository $taskRepository,
        ParameterBagInterface $parameterBag
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->taskRepository = $taskRepository;
        $this->parameterBag = $parameterBag;

        $this->initializeErrorCategories();
        $this->initializeRetryStrategies();
    }

    /**
     * 处理任务执行错误
     */
    public function handleTaskError(AsyncTask $task, \Exception $exception): array
    {
        $errorInfo = $this->analyzeError($exception);
        $retryStrategy = $this->determineRetryStrategy($errorInfo, $task);

        $this->logger->error('任务执行错误', [
            'task_id' => $task->getId(),
            'task_type' => $task->getTaskType(),
            'error_type' => $errorInfo['type'],
            'error_category' => $errorInfo['category'],
            'should_retry' => $retryStrategy['should_retry'],
            'retry_count' => $task->getRetryCount(),
            'max_retries' => $retryStrategy['max_retries'],
            'next_retry_at' => $retryStrategy['next_retry_at']?->format('Y-m-d H:i:s'),
            'error_message' => $exception->getMessage()
        ]);

        // 记录错误日志
        $this->logError($task, $exception, $errorInfo);

        // 更新任务状态
        if ($retryStrategy['should_retry']) {
            return $this->scheduleRetry($task, $retryStrategy, $errorInfo);
        } else {
            return $this->markTaskFailed($task, $errorInfo, $exception);
        }
    }

    /**
     * 分析错误
     */
    public function analyzeError(\Exception $exception): array
    {
        $errorType = $this->getErrorType($exception);
        $errorCategory = $this->getErrorCategory($errorType, $exception);
        $severity = $this->getErrorSeverity($errorCategory, $exception);
        $isRecoverable = $this->isRecoverableError($errorCategory, $exception);

        return [
            'type' => $errorType,
            'category' => $errorCategory,
            'severity' => $severity,
            'is_recoverable' => $isRecoverable,
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'previous' => $exception->getPrevious() ? $this->analyzeError($exception->getPrevious()) : null
        ];
    }

    /**
     * 确定重试策略
     */
    public function determineRetryStrategy(array $errorInfo, AsyncTask $task): array
    {
        $category = $errorInfo['category'];
        $currentRetryCount = $task->getRetryCount();

        if (!$errorInfo['is_recoverable']) {
            return [
                'should_retry' => false,
                'reason' => 'Error is not recoverable',
                'max_retries' => 0
            ];
        }

        $strategy = $this->retryStrategies[$category] ?? $this->retryStrategies['default'];
        $maxRetries = $strategy['max_retries'] ?? 3;

        if ($currentRetryCount >= $maxRetries) {
            return [
                'should_retry' => false,
                'reason' => 'Maximum retry attempts exceeded',
                'max_retries' => $maxRetries
            ];
        }

        $delay = $this->calculateRetryDelay($strategy, $currentRetryCount);
        $nextRetryAt = new \DateTime();
        $nextRetryAt->add(new \DateInterval("PT{$delay}S"));

        return [
            'should_retry' => true,
            'strategy' => $strategy['type'] ?? 'exponential_backoff',
            'delay' => $delay,
            'next_retry_at' => $nextRetryAt,
            'max_retries' => $maxRetries,
            'current_retry' => $currentRetryCount
        ];
    }

    /**
     * 执行重试
     */
    public function executeRetry(AsyncTask $task): bool
    {
        try {
            $this->logger->info('开始执行任务重试', [
                'task_id' => $task->getId(),
                'retry_count' => $task->getRetryCount() + 1
            ]);

            // 更新任务状态为重试中
            $task->setStatus(AsyncTask::STATUS_RETRYING);
            $task->setRetryCount($task->getRetryCount() + 1);
            $task->setUpdatedAt(new \DateTime());

            $this->entityManager->flush();

            // 重新发送消息到队列
            $this->resendTaskToQueue($task);

            $this->logger->info('任务重试已安排', [
                'task_id' => $task->getId(),
                'new_retry_count' => $task->getRetryCount()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('任务重试安排失败', [
                'task_id' => $task->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * 处理死信队列
     */
    public function handleDeadLetterQueue(): array
    {
        $deadLetterTasks = $this->taskRepository->findDeadLetterTasks();
        $processed = [];

        foreach ($deadLetterTasks as $task) {
            try {
                $result = $this->processDeadLetterTask($task);
                $processed[] = [
                    'task_id' => $task->getId(),
                    'result' => $result,
                    'processed_at' => date('Y-m-d H:i:s')
                ];
            } catch (\Exception $e) {
                $processed[] = [
                    'task_id' => $task->getId(),
                    'result' => 'failed',
                    'error' => $e->getMessage(),
                    'processed_at' => date('Y-m-d H:i:s')
                ];
            }
        }

        return $processed;
    }

    /**
     * 获取错误统计信息
     */
    public function getErrorStatistics(array $filters = []): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(l.id) as error_count, l.logLevel as error_type, l.logMessage as error_message')
           ->from(TaskExecutionLog::class, 'l')
           ->where('l.logLevel LIKE :errorPattern')
           ->setParameter('errorPattern', 'error_%');

        // 应用过滤器
        if (isset($filters['date_from'])) {
            $qb->andWhere('l.createdAt >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime($filters['date_from']));
        }

        if (isset($filters['date_to'])) {
            $qb->andWhere('l.createdAt <= :dateTo')
               ->setParameter('dateTo', new \DateTime($filters['date_to']));
        }

        if (isset($filters['task_type'])) {
            $qb->join('App\Entity\AsyncTask', 't', 'WITH', 'l.taskId = t.id')
               ->andWhere('t.taskType = :taskType')
               ->setParameter('taskType', $filters['task_type']);
        }

        $qb->groupBy('l.logLevel, l.logMessage')
           ->orderBy('error_count', 'DESC');

        return $qb->getQuery()->getResult();
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
                    MAX(t.retryCount) as max_retries')
           ->from(AsyncTask::class, 't')
           ->where('t.retryCount > 0');

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

        $stats = $qb->getQuery()->getSingleResult();

        // 获取重试分布
        $retryDistribution = $this->getRetryDistribution($filters);

        return [
            'summary' => $stats,
            'distribution' => $retryDistribution
        ];
    }

    /**
     * 初始化错误分类
     */
    private function initializeErrorCategories(): void
    {
        $this->errorCategories = [
            'network' => [
                'patterns' => ['timeout', 'connection', 'network', 'curl', 'http'],
                'recoverable' => true,
                'severity' => 'medium'
            ],
            'database' => [
                'patterns' => ['sql', 'database', 'connection', 'deadlock', 'lock'],
                'recoverable' => true,
                'severity' => 'high'
            ],
            'validation' => [
                'patterns' => ['validation', 'invalid', 'format', 'required'],
                'recoverable' => false,
                'severity' => 'medium'
            ],
            'authentication' => [
                'patterns' => ['auth', 'token', 'unauthorized', 'forbidden'],
                'recoverable' => true,
                'severity' => 'high'
            ],
            'rate_limit' => [
                'patterns' => ['rate limit', 'quota', 'throttle', 'too many'],
                'recoverable' => true,
                'severity' => 'medium'
            ],
            'business_logic' => [
                'patterns' => ['business', 'logic', 'rule'],
                'recoverable' => false,
                'severity' => 'high'
            ],
            'system' => [
                'patterns' => ['memory', 'disk', 'permission', 'file'],
                'recoverable' => true,
                'severity' => 'high'
            ]
        ];
    }

    /**
     * 初始化重试策略
     */
    private function initializeRetryStrategies(): void
    {
        $this->retryStrategies = [
            'network' => [
                'type' => 'exponential_backoff',
                'max_retries' => 5,
                'base_delay' => 5,
                'max_delay' => 300
            ],
            'database' => [
                'type' => 'linear_backoff',
                'max_retries' => 3,
                'base_delay' => 10,
                'max_delay' => 60
            ],
            'authentication' => [
                'type' => 'fixed_delay',
                'max_retries' => 2,
                'delay' => 60
            ],
            'rate_limit' => [
                'type' => 'exponential_backoff',
                'max_retries' => 3,
                'base_delay' => 60,
                'max_delay' => 600
            ],
            'system' => [
                'type' => 'linear_backoff',
                'max_retries' => 2,
                'base_delay' => 30,
                'max_delay' => 120
            ],
            'default' => [
                'type' => 'exponential_backoff',
                'max_retries' => 3,
                'base_delay' => 10,
                'max_delay' => 300
            ]
        ];
    }

    /**
     * 获取错误类型
     */
    private function getErrorType(\Exception $exception): string
    {
        $class = get_class($exception);

        if ($exception instanceof \RuntimeException) {
            return 'runtime';
        } elseif ($exception instanceof \InvalidArgumentException) {
            return 'invalid_argument';
        } elseif ($exception instanceof \PDOException) {
            return 'database';
        } elseif ($exception instanceof \LogicException) {
            return 'logic';
        } else {
            return 'unknown';
        }
    }

    /**
     * 获取错误分类
     */
    private function getErrorCategory(string $errorType, \Exception $exception): string
    {
        $message = strtolower($exception->getMessage());

        foreach ($this->errorCategories as $category => $config) {
            foreach ($config['patterns'] as $pattern) {
                if (strpos($message, $pattern) !== false) {
                    return $category;
                }
            }
        }

        return 'unknown';
    }

    /**
     * 获取错误严重程度
     */
    private function getErrorSeverity(string $category, \Exception $exception): string
    {
        $baseSeverity = $this->errorCategories[$category]['severity'] ?? 'medium';

        // 根据异常类型调整严重程度
        if ($exception instanceof \PDOException) {
            return 'high';
        }

        return $baseSeverity;
    }

    /**
     * 判断错误是否可恢复
     */
    private function isRecoverableError(string $category, \Exception $exception): bool
    {
        return $this->errorCategories[$category]['recoverable'] ?? false;
    }

    /**
     * 计算重试延迟
     */
    private function calculateRetryDelay(array $strategy, int $retryCount): int
    {
        $type = $strategy['type'] ?? 'exponential_backoff';

        switch ($type) {
            case 'exponential_backoff':
                $delay = $strategy['base_delay'] * pow(2, $retryCount);
                break;
            case 'linear_backoff':
                $delay = $strategy['base_delay'] * ($retryCount + 1);
                break;
            case 'fixed_delay':
                $delay = $strategy['delay'];
                break;
            default:
                $delay = $strategy['base_delay'] ?? 10;
        }

        return min($delay, $strategy['max_delay'] ?? 300);
    }

    /**
     * 记录错误日志
     */
    private function logError(AsyncTask $task, \Exception $exception, array $errorInfo): void
    {
        $log = new TaskExecutionLog();
        $log->setTaskId($task->getId());
        $log->setLogLevel('error_' . $errorInfo['category']);
        $log->setLogMessage(json_encode([
            'error_type' => $errorInfo['type'],
            'error_category' => $errorInfo['category'],
            'error_severity' => $errorInfo['severity'],
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]));
        $log->setCreatedAt(new \DateTime());

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    /**
     * 安排重试
     */
    private function scheduleRetry(AsyncTask $task, array $retryStrategy, array $errorInfo): array
    {
        $task->setStatus(AsyncTask::STATUS_PENDING);
        $task->setScheduledAt($retryStrategy['next_retry_at']);
        $task->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        return [
            'success' => true,
            'action' => 'retry_scheduled',
            'next_retry_at' => $retryStrategy['next_retry_at']->format('Y-m-d H:i:s'),
            'retry_count' => $task->getRetryCount()
        ];
    }

    /**
     * 标记任务失败
     */
    private function markTaskFailed(AsyncTask $task, array $errorInfo, \Exception $exception): array
    {
        $task->setStatus(AsyncTask::STATUS_FAILED);
        $task->setResultData([
            'error' => $errorInfo,
            'final_error_message' => $exception->getMessage(),
            'failed_at' => date('Y-m-d H:i:s')
        ]);
        $task->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        return [
            'success' => false,
            'action' => 'marked_failed',
            'error' => $errorInfo,
            'final_error_message' => $exception->getMessage()
        ];
    }

    /**
     * 重新发送任务到队列
     */
    private function resendTaskToQueue(AsyncTask $task): void
    {
        // 这里需要根据实际的消息队列实现来发送消息
        // 例如使用 Symfony Messenger
        $this->logger->info('重新发送任务到队列', ['task_id' => $task->getId()]);
    }

    /**
     * 处理死信任务
     */
    private function processDeadLetterTask(AsyncTask $task): string
    {
        $this->logger->info('处理死信任务', ['task_id' => $task->getId()]);

        // 实现死信任务处理逻辑
        // 例如：发送通知、记录到特殊日志、尝试修复等

        return 'processed';
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

        // 应用相同的过滤器
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
}
