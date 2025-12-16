<?php

namespace App\Service;

use App\Entity\AsyncTask;
use App\Entity\TaskExecutionLog;
use App\Repository\AsyncTaskRepository;
use App\Repository\TaskExecutionLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * 任务状态服务
 * 负责任务状态管理和查询
 */
class TaskStatusService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AsyncTaskRepository $asyncTaskRepository,
        private TaskExecutionLogRepository $taskExecutionLogRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * 更新任务状态
     */
    public function updateStatus(string $taskId, string $status, ?array $result = null, ?string $errorMessage = null): bool
    {
        try {
            $task = $this->asyncTaskRepository->find($taskId);

            if (!$task) {
                $this->logger->warning('Task not found for status update', [
                    'task_id' => $taskId,
                    'status' => $status,
                ]);
                return false;
            }

            // 验证状态
            if (!in_array($status, AsyncTask::getAvailableStatuses())) {
                $this->logger->warning('Invalid task status', [
                    'task_id' => $taskId,
                    'status' => $status,
                ]);
                return false;
            }

            $oldStatus = $task->getStatus();
            $task->setStatus($status);

            if ($result !== null) {
                $task->setResult($result);
            }

            if ($errorMessage !== null) {
                $task->setErrorMessage($errorMessage);
            }

            // 根据状态设置时间戳
            if ($status === AsyncTask::STATUS_RUNNING && !$task->getStartedAt()) {
                $task->setStartedAt(new \DateTime());
            }

            if (in_array($status, [AsyncTask::STATUS_COMPLETED, AsyncTask::STATUS_FAILED, AsyncTask::STATUS_CANCELLED])) {
                $task->setCompletedAt(new \DateTime());
            }

            $this->entityManager->persist($task);
            $this->entityManager->flush();

            // 记录状态变更
            $this->logStatusChange($taskId, $oldStatus, $status, $result, $errorMessage);

            $this->logger->info('Task status updated', [
                'task_id' => $taskId,
                'old_status' => $oldStatus,
                'new_status' => $status,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to update task status', [
                'task_id' => $taskId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 获取状态历史
     */
    public function getStatusHistory(string $taskId, ?int $limit = 50): array
    {
        try {
            $logs = $this->taskExecutionLogRepository->findByTaskId($taskId, null, $limit);

            $history = [];
            foreach ($logs as $log) {
                $history[] = [
                    'execution_id' => $log->getExecutionId(),
                    'status' => $log->getStatus(),
                    'started_at' => $log->getStartedAt()->format('Y-m-d H:i:s'),
                    'completed_at' => $log->getCompletedAt()?->format('Y-m-d H:i:s'),
                    'duration_ms' => $log->getDurationMs(),
                    'memory_usage' => $log->getMemoryUsage(),
                    'processed_items' => $log->getProcessedItems(),
                    'error_message' => $log->getErrorMessage(),
                    'metadata' => $log->getMetadata(),
                ];
            }

            return $history;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get status history', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * 获取任务进度
     */
    public function getProgress(string $taskId): ?array
    {
        try {
            $task = $this->asyncTaskRepository->find($taskId);

            if (!$task) {
                return null;
            }

            $progress = [
                'task_id' => $taskId,
                'status' => $task->getStatus(),
                'created_at' => $task->getCreatedAt()->format('Y-m-d H:i:s'),
                'started_at' => $task->getStartedAt()?->format('Y-m-d H:i:s'),
                'completed_at' => $task->getCompletedAt()?->format('Y-m-d H:i:s'),
                'retry_count' => $task->getRetryCount(),
                'max_retries' => $task->getMaxRetries(),
                'error_message' => $task->getErrorMessage(),
                'percentage' => 0,
                'current_step' => 'pending',
                'estimated_completion' => null,
            ];

            // 根据状态计算进度
            switch ($task->getStatus()) {
                case AsyncTask::STATUS_PENDING:
                    $progress['percentage'] = 0;
                    $progress['current_step'] = '等待执行';
                    break;

                case AsyncTask::STATUS_RETRYING:
                    $progress['percentage'] = 10;
                    $progress['current_step'] = '准备重试';
                    break;

                case AsyncTask::STATUS_RUNNING:
                    $progress['percentage'] = $this->calculateRunningProgress($task);
                    $progress['current_step'] = $this->getCurrentStep($task);
                    $progress['estimated_completion'] = $this->estimateCompletion($task);
                    break;

                case AsyncTask::STATUS_COMPLETED:
                    $progress['percentage'] = 100;
                    $progress['current_step'] = '已完成';
                    break;

                case AsyncTask::STATUS_FAILED:
                    $progress['percentage'] = 100;
                    $progress['current_step'] = '执行失败';
                    break;

                case AsyncTask::STATUS_CANCELLED:
                    $progress['percentage'] = 100;
                    $progress['current_step'] = '已取消';
                    break;
            }

            return $progress;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get task progress', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 获取任务详细状态
     */
    public function getDetailedStatus(string $taskId): ?array
    {
        try {
            $task = $this->asyncTaskRepository->find($taskId);

            if (!$task) {
                return null;
            }

            $detailedStatus = [
                'task_id' => $taskId,
                'type' => $task->getType(),
                'status' => $task->getStatus(),
                'priority' => $task->getPriority(),
                'queue_name' => $task->getQueueName(),
                'created_by' => $task->getCreatedBy(),
                'payload' => $task->getPayload(),
                'result' => $task->getResult(),
                'error_message' => $task->getErrorMessage(),
                'retry_count' => $task->getRetryCount(),
                'max_retries' => $task->getMaxRetries(),
                'created_at' => $task->getCreatedAt()->format('Y-m-d H:i:s'),
                'started_at' => $task->getStartedAt()?->format('Y-m-d H:i:s'),
                'completed_at' => $task->getCompletedAt()?->format('Y-m-d H:i:s'),
                'expires_at' => $task->getExpiresAt()?->format('Y-m-d H:i:s'),
                'execution_duration' => $task->getExecutionDuration(),
                'is_expired' => $task->isExpired(),
                'can_retry' => $task->canRetry(),
                'is_finished' => $task->isFinished(),
                'is_running' => $task->isRunning(),
            ];

            // 获取执行日志
            $latestLog = $this->taskExecutionLogRepository->findLatestByTaskId($taskId);
            if ($latestLog) {
                $detailedStatus['execution_log'] = [
                    'execution_id' => $latestLog->getExecutionId(),
                    'status' => $latestLog->getStatus(),
                    'started_at' => $latestLog->getStartedAt()->format('Y-m-d H:i:s'),
                    'completed_at' => $latestLog->getCompletedAt()?->format('Y-m-d H:i:s'),
                    'duration_ms' => $latestLog->getDurationMs(),
                    'memory_usage' => $latestLog->getMemoryUsage(),
                    'processed_items' => $latestLog->getProcessedItems(),
                    'error_message' => $latestLog->getErrorMessage(),
                    'metadata' => $latestLog->getMetadata(),
                ];
            }

            return $detailedStatus;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get detailed status', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 批量获取任务状态
     */
    public function getBatchStatus(array $taskIds): array
    {
        $results = [];

        foreach ($taskIds as $taskId) {
            $results[$taskId] = $this->getDetailedStatus($taskId);
        }

        return $results;
    }

    /**
     * 获取状态统计
     */
    public function getStatusStatistics(?string $queueName = null): array
    {
        try {
            $statistics = $this->asyncTaskRepository->getQueueStatistics($queueName);

            $total = $statistics['total'] ?? 0;
            $statusStats = [];

            foreach (AsyncTask::getAvailableStatuses() as $status) {
                $count = $statistics[$status] ?? 0;
                $percentage = $total > 0 ? round(($count / $total) * 100, 2) : 0;

                $statusStats[$status] = [
                    'count' => $count,
                    'percentage' => $percentage,
                ];
            }

            return [
                'total' => $total,
                'by_status' => $statusStats,
                'queue_name' => $queueName,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get status statistics', [
                'queue_name' => $queueName,
                'error' => $e->getMessage(),
            ]);

            return [
                'total' => 0,
                'by_status' => [],
                'queue_name' => $queueName,
            ];
        }
    }

    /**
     * 计算运行中的任务进度
     */
    private function calculateRunningProgress(AsyncTask $task): int
    {
        $payload = $task->getPayload();
        $type = $task->getType();

        // 根据任务类型计算进度
        return match ($type) {
            AsyncTask::TYPE_WECHAT_SYNC => $this->calculateWechatSyncProgress($payload),
            AsyncTask::TYPE_MEDIA_PROCESS => $this->calculateMediaProcessProgress($payload),
            AsyncTask::TYPE_BATCH_PROCESS => $this->calculateBatchProcessProgress($payload),
            default => 50, // 默认进度
        };
    }

    /**
     * 计算微信同步进度
     */
    private function calculateWechatSyncProgress(array $payload): int
    {
        $totalArticles = $payload['article_limit'] ?? 100;
        $processedArticles = $payload['processed_articles'] ?? 0;

        if ($totalArticles <= 0) {
            return 0;
        }

        $progress = min(90, (int)(($processedArticles / $totalArticles) * 100));
        return max(10, $progress); // 至少10%表示已开始
    }

    /**
     * 计算媒体处理进度
     */
    private function calculateMediaProcessProgress(array $payload): int
    {
        $mediaUrls = $payload['media_urls'] ?? [];
        $totalMedia = count($mediaUrls);
        $processedMedia = $payload['processed_media'] ?? 0;

        if ($totalMedia <= 0) {
            return 0;
        }

        $progress = min(90, (int)(($processedMedia / $totalMedia) * 100));
        return max(10, $progress);
    }

    /**
     * 计算批量处理进度
     */
    private function calculateBatchProcessProgress(array $payload): int
    {
        $totalItems = $payload['item_count'] ?? 100;
        $processedItems = $payload['processed_items'] ?? 0;

        if ($totalItems <= 0) {
            return 0;
        }

        $progress = min(90, (int)(($processedItems / $totalItems) * 100));
        return max(10, $progress);
    }

    /**
     * 获取当前执行步骤
     */
    private function getCurrentStep(AsyncTask $task): string
    {
        $payload = $task->getPayload();
        $type = $task->getType();

        return match ($type) {
            AsyncTask::TYPE_WECHAT_SYNC => $this->getWechatSyncStep($payload),
            AsyncTask::TYPE_MEDIA_PROCESS => $this->getMediaProcessStep($payload),
            AsyncTask::TYPE_BATCH_PROCESS => $this->getBatchProcessStep($payload),
            default => '处理中',
        };
    }

    /**
     * 获取微信同步当前步骤
     */
    private function getWechatSyncStep(array $payload): string
    {
        $step = $payload['current_step'] ?? 'fetching_articles';

        return match ($step) {
            'fetching_articles' => '获取文章列表',
            'processing_articles' => '处理文章内容',
            'downloading_media' => '下载媒体资源',
            'saving_data' => '保存数据',
            'finalizing' => '完成处理',
            default => '处理中',
        };
    }

    /**
     * 获取媒体处理当前步骤
     */
    private function getMediaProcessStep(array $payload): string
    {
        $step = $payload['current_step'] ?? 'downloading';

        return match ($step) {
            'downloading' => '下载媒体文件',
            'processing' => '处理媒体文件',
            'uploading' => '上传到存储',
            'optimizing' => '优化文件',
            default => '处理中',
        };
    }

    /**
     * 获取批量处理当前步骤
     */
    private function getBatchProcessStep(array $payload): string
    {
        $step = $payload['current_step'] ?? 'processing';

        return match ($step) {
            'initializing' => '初始化处理',
            'processing' => '批量处理中',
            'validating' => '数据验证',
            'saving' => '保存结果',
            default => '处理中',
        };
    }

    /**
     * 估算完成时间
     */
    private function estimateCompletion(AsyncTask $task): ?string
    {
        if (!$task->getStartedAt()) {
            return null;
        }

        $payload = $task->getPayload();
        $type = $task->getType();

        $elapsedTime = time() - $task->getStartedAt()->getTimestamp();
        $estimatedTotalTime = match ($type) {
            AsyncTask::TYPE_WECHAT_SYNC => $this->estimateWechatSyncTime($payload, $elapsedTime),
            AsyncTask::TYPE_MEDIA_PROCESS => $this->estimateMediaProcessTime($payload, $elapsedTime),
            AsyncTask::TYPE_BATCH_PROCESS => $this->estimateBatchProcessTime($payload, $elapsedTime),
            default => null,
        };

        if ($estimatedTotalTime && $estimatedTotalTime > $elapsedTime) {
            $remainingTime = $estimatedTotalTime - $elapsedTime;
            $completionTime = time() + $remainingTime;
            return date('Y-m-d H:i:s', $completionTime);
        }

        return null;
    }

    /**
     * 估算微信同步时间
     */
    private function estimateWechatSyncTime(array $payload, int $elapsedTime): ?int
    {
        $totalArticles = $payload['article_limit'] ?? 100;
        $processedArticles = $payload['processed_articles'] ?? 0;

        if ($processedArticles <= 0) {
            return null;
        }

        $avgTimePerArticle = $elapsedTime / $processedArticles;
        return (int)($totalArticles * $avgTimePerArticle);
    }

    /**
     * 估算媒体处理时间
     */
    private function estimateMediaProcessTime(array $payload, int $elapsedTime): ?int
    {
        $mediaUrls = $payload['media_urls'] ?? [];
        $totalMedia = count($mediaUrls);
        $processedMedia = $payload['processed_media'] ?? 0;

        if ($processedMedia <= 0) {
            return null;
        }

        $avgTimePerMedia = $elapsedTime / $processedMedia;
        return (int)($totalMedia * $avgTimePerMedia);
    }

    /**
     * 估算批量处理时间
     */
    private function estimateBatchProcessTime(array $payload, int $elapsedTime): ?int
    {
        $totalItems = $payload['item_count'] ?? 100;
        $processedItems = $payload['processed_items'] ?? 0;

        if ($processedItems <= 0) {
            return null;
        }

        $avgTimePerItem = $elapsedTime / $processedItems;
        return (int)($totalItems * $avgTimePerItem);
    }

    /**
     * 记录状态变更
     */
    private function logStatusChange(string $taskId, string $oldStatus, string $newStatus, ?array $result = null, ?string $errorMessage = null): void
    {
        $log = new TaskExecutionLog();
        $log->setTaskId($taskId);

        // 根据状态设置日志状态
        match ($newStatus) {
            AsyncTask::STATUS_RUNNING => $log->markAsRunning(),
            AsyncTask::STATUS_COMPLETED => $log->markAsCompleted($result['processed_items'] ?? null),
            AsyncTask::STATUS_FAILED => $log->markAsFailed(new \Exception($errorMessage ?? 'Unknown error')),
            AsyncTask::STATUS_CANCELLED => $log->markAsCancelled(),
            default => $log->markAsStarted(),
        };

        if ($result !== null) {
            $log->addMetadata('result', $result);
        }

        $this->taskExecutionLogRepository->save($log, true);
    }
}
