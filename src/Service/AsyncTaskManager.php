<?php

namespace App\Service;

use App\Entity\AsyncTask;
use App\Entity\TaskExecutionLog;
use App\Entity\TaskDependency;
use App\Repository\AsyncTaskRepository;
use App\Repository\TaskExecutionLogRepository;
use App\Repository\TaskDependencyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * 异步任务管理器
 * 负责任务的生命周期管理
 */
class AsyncTaskManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AsyncTaskRepository $asyncTaskRepository,
        private TaskExecutionLogRepository $taskExecutionLogRepository,
        private TaskDependencyRepository $taskDependencyRepository,
        private LoggerInterface $logger,
        #[Autowire('%env(default:3600)%env(int:ASYNC_TASK_DEFAULT_TTL)%')]
        private int $defaultTtl = 3600
    ) {
    }

    /**
     * 创建异步任务
     */
    public function createTask(
        string $type,
        array $payload,
        ?string $queueName = null,
        int $priority = 5,
        ?string $createdBy = null,
        ?\DateTimeInterface $expiresAt = null,
        int $maxRetries = 3
    ): AsyncTask {
        // 验证任务类型
        if (!in_array($type, AsyncTask::getAvailableTypes())) {
            throw new \InvalidArgumentException("Invalid task type: {$type}");
        }

        // 验证优先级
        if ($priority < 1 || $priority > 10) {
            throw new \InvalidArgumentException("Priority must be between 1 and 10");
        }

        $task = new AsyncTask();
        $task->setType($type);
        $task->setPayload($payload);
        $task->setPriority($priority);
        $task->setQueueName($queueName);
        $task->setCreatedBy($createdBy);
        $task->setMaxRetries($maxRetries);

        // 设置过期时间
        if (!$expiresAt) {
            $expiresAt = (new \DateTime())->add(new \DateInterval("PT{$this->defaultTtl}S"));
        }
        $task->setExpiresAt($expiresAt);

        try {
            $this->asyncTaskRepository->save($task, true);

            $this->logger->info('Async task created', [
                'task_id' => $task->getId(),
                'type' => $type,
                'priority' => $priority,
                'queue_name' => $queueName,
                'created_by' => $createdBy,
            ]);

            return $task;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create async task', [
                'type' => $type,
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            throw new \RuntimeException('Failed to create async task: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 获取任务状态
     */
    public function getTaskStatus(string $taskId): ?AsyncTask
    {
        $task = $this->asyncTaskRepository->find($taskId);

        if (!$task) {
            $this->logger->warning('Task not found', ['task_id' => $taskId]);
            return null;
        }

        // 检查任务是否过期
        if ($task->isExpired() && !$task->isFinished()) {
            $this->markTaskAsExpired($task);
        }

        return $task;
    }

    /**
     * 取消任务
     */
    public function cancelTask(string $taskId, ?string $reason = null): bool
    {
        $task = $this->asyncTaskRepository->find($taskId);

        if (!$task) {
            $this->logger->warning('Task not found for cancellation', ['task_id' => $taskId]);
            return false;
        }

        if ($task->isFinished()) {
            $this->logger->warning('Cannot cancel finished task', [
                'task_id' => $taskId,
                'status' => $task->getStatus(),
            ]);
            return false;
        }

        try {
            $task->setStatus(AsyncTask::STATUS_CANCELLED);
            $task->setCompletedAt(new \DateTime());

            if ($reason) {
                $task->setErrorMessage("Cancelled: {$reason}");
            }

            $this->asyncTaskRepository->save($task, true);

            // 创建执行日志
            $executionLog = new TaskExecutionLog();
            $executionLog->setTaskId($taskId);
            $executionLog->markAsCancelled();
            $this->taskExecutionLogRepository->save($executionLog, true);

            // 处理依赖关系
            $this->handleTaskCompletion($task);

            $this->logger->info('Task cancelled', [
                'task_id' => $taskId,
                'reason' => $reason,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to cancel task', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 重试任务
     */
    public function retryTask(string $taskId): bool
    {
        $task = $this->asyncTaskRepository->find($taskId);

        if (!$task) {
            $this->logger->warning('Task not found for retry', ['task_id' => $taskId]);
            return false;
        }

        if (!$task->canRetry()) {
            $this->logger->warning('Task cannot be retried', [
                'task_id' => $taskId,
                'status' => $task->getStatus(),
                'retry_count' => $task->getRetryCount(),
                'max_retries' => $task->getMaxRetries(),
            ]);
            return false;
        }

        try {
            // 增加重试次数
            $task->setRetryCount($task->getRetryCount() + 1);
            $task->setStatus(AsyncTask::STATUS_RETRYING);
            $task->setStartedAt(null);
            $task->setCompletedAt(null);
            $task->setErrorMessage(null);

            // 重置执行日志
            $executionLog = new TaskExecutionLog();
            $executionLog->setTaskId($taskId);
            $executionLog->markAsStarted();
            $this->taskExecutionLogRepository->save($executionLog, true);

            $this->asyncTaskRepository->save($task, true);

            $this->logger->info('Task retried', [
                'task_id' => $taskId,
                'retry_count' => $task->getRetryCount(),
                'max_retries' => $task->getMaxRetries(),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to retry task', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 获取任务列表
     */
    public function listTasks(
        ?string $status = null,
        ?string $type = null,
        ?string $queueName = null,
        ?int $limit = 20,
        ?int $offset = 0
    ): array {
        try {
            $tasks = $this->asyncTaskRepository->findPaginated(
                $status,
                $type,
                $queueName,
                $limit,
                $offset
            );

            $total = $this->asyncTaskRepository->countByFilters(
                $status,
                $type,
                $queueName
            );

            return [
                'items' => $tasks,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => $total > ($offset + $limit),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to list tasks', [
                'error' => $e->getMessage(),
                'filters' => [
                    'status' => $status,
                    'type' => $type,
                    'queue_name' => $queueName,
                ],
            ]);

            throw new \RuntimeException('Failed to list tasks: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 标记任务为运行中
     */
    public function markTaskAsRunning(string $taskId): bool
    {
        $task = $this->asyncTaskRepository->find($taskId);

        if (!$task) {
            return false;
        }

        try {
            $task->setStatus(AsyncTask::STATUS_RUNNING);
            $task->setStartedAt(new \DateTime());
            $this->asyncTaskRepository->save($task, true);

            // 创建执行日志
            $executionLog = new TaskExecutionLog();
            $executionLog->setTaskId($taskId);
            $executionLog->markAsRunning();
            $this->taskExecutionLogRepository->save($executionLog, true);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to mark task as running', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 标记任务为完成
     */
    public function markTaskAsCompleted(string $taskId, array $result = [], ?int $processedItems = null): bool
    {
        $task = $this->asyncTaskRepository->find($taskId);

        if (!$task) {
            return false;
        }

        try {
            $task->setStatus(AsyncTask::STATUS_COMPLETED);
            $task->setCompletedAt(new \DateTime());
            $task->setResult($result);

            $this->asyncTaskRepository->save($task, true);

            // 更新执行日志
            $latestLog = $this->taskExecutionLogRepository->findLatestByTaskId($taskId);
            if ($latestLog && !$latestLog->isFinished()) {
                $latestLog->markAsCompleted($processedItems);
                $this->taskExecutionLogRepository->save($latestLog, true);
            }

            // 处理依赖关系
            $this->handleTaskCompletion($task);

            $this->logger->info('Task completed', [
                'task_id' => $taskId,
                'processed_items' => $processedItems,
                'result_count' => count($result),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to mark task as completed', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 标记任务为失败
     */
    public function markTaskAsFailed(string $taskId, \Throwable $exception, ?int $processedItems = null): bool
    {
        $task = $this->asyncTaskRepository->find($taskId);

        if (!$task) {
            return false;
        }

        try {
            $task->setStatus(AsyncTask::STATUS_FAILED);
            $task->setCompletedAt(new \DateTime());
            $task->setErrorMessage($exception->getMessage());

            $this->asyncTaskRepository->save($task, true);

            // 更新执行日志
            $latestLog = $this->taskExecutionLogRepository->findLatestByTaskId($taskId);
            if ($latestLog && !$latestLog->isFinished()) {
                $latestLog->markAsFailed($exception, $processedItems);
                $this->taskExecutionLogRepository->save($latestLog, true);
            }

            // 处理依赖关系
            $this->handleTaskCompletion($task);

            $this->logger->error('Task failed', [
                'task_id' => $taskId,
                'error' => $exception->getMessage(),
                'processed_items' => $processedItems,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to mark task as failed', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 标记任务为过期
     */
    private function markTaskAsExpired(AsyncTask $task): void
    {
        if ($task->isFinished()) {
            return;
        }

        $task->setStatus(AsyncTask::STATUS_FAILED);
        $task->setCompletedAt(new \DateTime());
        $task->setErrorMessage('Task expired');

        $this->asyncTaskRepository->save($task, true);

        $this->logger->warning('Task expired', [
            'task_id' => $task->getId(),
            'expires_at' => $task->getExpiresAt()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 处理任务完成后的依赖关系
     */
    private function handleTaskCompletion(AsyncTask $task): void
    {
        $taskId = $task->getId();
        $status = $task->getStatus();

        // 查找依赖于此任务的其他任务
        $dependentTasks = $this->taskDependencyRepository->findSatisfiedDependencies($taskId, $status);

        foreach ($dependentTasks as $dependency) {
            $dependentTaskId = $dependency->getTaskId();
            $dependentTask = $this->asyncTaskRepository->find($dependentTaskId);

            if ($dependentTask && $dependentTask->getStatus() === AsyncTask::STATUS_PENDING) {
                // 检查是否所有依赖都已满足
                if ($this->areAllDependenciesSatisfied($dependentTaskId)) {
                    // 可以在这里触发依赖任务的执行
                    $this->logger->info('Dependencies satisfied for task', [
                        'task_id' => $dependentTaskId,
                        'triggered_by' => $taskId,
                    ]);
                }
            }
        }
    }

    /**
     * 检查任务的所有依赖是否都已满足
     */
    private function areAllDependenciesSatisfied(string $taskId): bool
    {
        $dependencies = $this->taskDependencyRepository->findByTaskId($taskId);

        foreach ($dependencies as $dependency) {
            $dependsOnTaskId = $dependency->getDependsOnTaskId();
            $dependsOnTask = $this->asyncTaskRepository->find($dependsOnTaskId);

            if (!$dependsOnTask || !$dependency->isSatisfiedBy($dependsOnTask->getStatus())) {
                return false;
            }
        }

        return true;
    }

    /**
     * 创建任务依赖关系
     */
    public function createTaskDependency(string $taskId, string $dependsOnTaskId, string $dependencyType): TaskDependency
    {
        try {
            $dependency = $this->taskDependencyRepository->createDependency(
                $taskId,
                $dependsOnTaskId,
                $dependencyType
            );

            $this->logger->info('Task dependency created', [
                'task_id' => $taskId,
                'depends_on_task_id' => $dependsOnTaskId,
                'dependency_type' => $dependencyType,
            ]);

            return $dependency;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create task dependency', [
                'task_id' => $taskId,
                'depends_on_task_id' => $dependsOnTaskId,
                'dependency_type' => $dependencyType,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to create task dependency: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 获取任务统计信息
     */
    public function getTaskStatistics(?string $queueName = null): array
    {
        return $this->asyncTaskRepository->getQueueStatistics($queueName);
    }
}
