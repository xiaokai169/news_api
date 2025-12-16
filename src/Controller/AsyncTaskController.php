<?php

namespace App\Controller;

use App\Http\ApiResponse;
use App\Message\WechatSyncMessage;
use App\Service\AsyncTaskManager;
use App\Service\TaskQueueService;
use App\Service\TaskStatusService;
use App\Service\WechatSyncStatusService;
use App\DTO\Request\SyncWechatDto;
use App\DTO\Response\WechatSyncStatusDto;
use App\DTO\Response\WechatSyncProgressDto;
use App\Entity\AsyncTask;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

/**
 * 异步任务控制器
 */
#[Route('/official-api')]
class AsyncTaskController extends AbstractController
{
    public function __construct(
        private AsyncTaskManager $asyncTaskManager,
        private TaskStatusService $taskStatusService,
        private TaskQueueService $taskQueueService,
        private WechatSyncStatusService $wechatSyncStatusService,
        private MessageBusInterface $messageBus,
        private ValidatorInterface $validator
    ) {
    }

    /**
     * 创建异步同步任务
     */
    #[Route('/wechat/sync-async', methods: ['POST'])]
    public function createAsyncSyncTask(Request $request, SyncWechatDto $dto): JsonResponse
    {
        try {
            // 验证请求数据
            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                return ApiResponse::error('Validation failed', 400, $errors);
            }

            // 创建异步任务
            $task = $this->asyncTaskManager->createTask(
                AsyncTask::TYPE_WECHAT_SYNC,
                $dto->toArray(),
                'wechat_sync',
                $dto->priority ?? 5,
                $dto->createdBy ?? 'api_user',
                $dto->expiresAt,
                $dto->maxRetries ?? 3
            );

            // 创建微信同步消息并发送到队列
            $message = WechatSyncMessage::create(
                $task->getId(),
                $dto->accountId,
                $dto->syncType ?? 'articles',
                $dto->syncScope ?? 'recent',
                $dto->articleLimit ?? 100,
                $dto->forceSync ?? false,
                $dto->customOptions ?? []
            );

            $this->messageBus->dispatch($message);

            return ApiResponse::success([
                'task_id' => $task->getId(),
                'status' => $task->getStatus(),
                'estimated_duration' => $this->estimateDuration($dto),
                'queue_position' => $this->getQueuePosition('wechat_sync'),
                'created_at' => $task->getCreatedAt()->format('Y-m-d H:i:s'),
                'message' => 'Async task created successfully',
            ], 201);

        } catch (\Exception $e) {
            $this->get('logger')->error('Failed to create async sync task', [
                'error' => $e->getMessage(),
                'dto' => $dto->toArray(),
            ]);

            return ApiResponse::error('Failed to create async task: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 查询任务状态
     */
    #[Route('/wechat/sync-status/{taskId}', methods: ['GET'])]
    public function getTaskStatus(string $taskId): JsonResponse
    {
        try {
            $progress = $this->taskStatusService->getProgress($taskId);

            if (!$progress) {
                return ApiResponse::error('Task not found', 404);
            }

            return ApiResponse::success($progress);

        } catch (\Exception $e) {
            $this->get('logger')->error('Failed to get task status', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to get task status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取任务详细状态
     */
    #[Route('/wechat/sync-detail/{taskId}', methods: ['GET'])]
    public function getTaskDetail(string $taskId): JsonResponse
    {
        try {
            $detail = $this->taskStatusService->getDetailedStatus($taskId);

            if (!$detail) {
                return ApiResponse::error('Task not found', 404);
            }

            return ApiResponse::success($detail);

        } catch (\Exception $e) {
            $this->get('logger')->error('Failed to get task detail', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to get task detail: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 取消任务
     */
    #[Route('/wechat/sync-task/{taskId}', methods: ['DELETE'])]
    public function cancelTask(string $taskId, Request $request): JsonResponse
    {
        try {
            $reason = $request->query->get('reason', 'User cancelled');

            $success = $this->asyncTaskManager->cancelTask($taskId, $reason);

            if (!$success) {
                return ApiResponse::error('Task not found or cannot be cancelled', 404);
            }

            return ApiResponse::success([
                'task_id' => $taskId,
                'status' => 'cancelled',
                'cancelled_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'reason' => $reason,
            ]);

        } catch (\Exception $e) {
            $this->get('logger')->error('Failed to cancel task', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to cancel task: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 重试任务
     */
    #[Route('/wechat/sync-task/{taskId}/retry', methods: ['POST'])]
    public function retryTask(string $taskId): JsonResponse
    {
        try {
            $success = $this->asyncTaskManager->retryTask($taskId);

            if (!$success) {
                return ApiResponse::error('Task not found or cannot be retried', 404);
            }

            $task = $this->asyncTaskManager->getTaskStatus($taskId);

            return ApiResponse::success([
                'task_id' => $taskId,
                'status' => $task->getStatus(),
                'retry_count' => $task->getRetryCount(),
                'max_retries' => $task->getMaxRetries(),
                'message' => 'Task retry scheduled',
            ]);

        } catch (\Exception $e) {
            $this->get('logger')->error('Failed to retry task', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to retry task: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取任务列表
     */
    #[Route('/wechat/sync-tasks', methods: ['GET'])]
    public function listTasks(Request $request): JsonResponse
    {
        try {
            $status = $request->query->get('status');
            $type = $request->query->get('type');
            $queueName = $request->query->get('queue_name');
            $page = max(1, (int)($request->query->get('page', 1)));
            $limit = min(100, max(1, (int)($request->query->get('limit', 20))));
            $offset = ($page - 1) * $limit;

            $result = $this->asyncTaskManager->listTasks(
                $status,
                $type,
                $queueName,
                $limit,
                $offset
            );

            // 格式化任务数据
            $items = array_map(function ($task) {
                return [
                    'task_id' => $task->getId(),
                    'type' => $task->getType(),
                    'status' => $task->getStatus(),
                    'priority' => $task->getPriority(),
                    'queue_name' => $task->getQueueName(),
                    'created_at' => $task->getCreatedAt()->format('Y-m-d H:i:s'),
                    'started_at' => $task->getStartedAt()?->format('Y-m-d H:i:s'),
                    'completed_at' => $task->getCompletedAt()?->format('Y-m-d H:i:s'),
                    'duration' => $task->getExecutionDuration() ? $this->formatDuration($task->getExecutionDuration()) : null,
                    'retry_count' => $task->getRetryCount(),
                    'max_retries' => $task->getMaxRetries(),
                    'error_message' => $task->getErrorMessage(),
                    'result' => $task->getResult(),
                ];
            }, $result['items']);

            return ApiResponse::success([
                'items' => $items,
                'total' => $result['total'],
                'page' => $page,
                'limit' => $limit,
                'has_more' => $result['has_more'],
                'total_pages' => ceil($result['total'] / $limit),
            ]);

        } catch (\Exception $e) {
            $this->get('logger')->error('Failed to list tasks', [
                'error' => $e->getMessage(),
                'query_params' => $request->query->all(),
            ]);

            return ApiResponse::error('Failed to list tasks: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取任务状态历史
     */
    #[Route('/wechat/sync-task/{taskId}/history', methods: ['GET'])]
    public function getTaskHistory(string $taskId, Request $request): JsonResponse
    {
        try {
            $limit = min(100, max(1, (int)($request->query->get('limit', 50))));

            $history = $this->taskStatusService->getStatusHistory($taskId, $limit);

            if (empty($history)) {
                return ApiResponse::error('Task not found or no history available', 404);
            }

            return ApiResponse::success([
                'task_id' => $taskId,
                'history' => $history,
                'total_entries' => count($history),
            ]);

        } catch (\Exception $e) {
            $this->get('logger')->error('Failed to get task history', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to get task history: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取队列统计信息
     */
    #[Route('/wechat/queue-stats', methods: ['GET'])]
    public function getQueueStats(Request $request): JsonResponse
    {
        try {
            $queueName = $request->query->get('queue_name');

            $stats = $this->taskQueueService->getQueueStats($queueName);

            return ApiResponse::success([
                'queue_name' => $queueName,
                'statistics' => $stats,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            $this->get('logger')->error('Failed to get queue stats', [
                'queue_name' => $queueName,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to get queue stats: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取队列健康状态
     */
    #[Route('/wechat/queue-health', methods: ['GET'])]
    public function getQueueHealth(Request $request): JsonResponse
    {
        try {
            $queueName = $request->query->get('queue_name');

            $health = $this->taskQueueService->getQueueHealth($queueName);

            $statusCode = match ($health['status']) {
                'healthy' => 200,
                'warning' => 200,
                'critical' => 503,
                'error' => 500,
                default => 200,
            };

            return ApiResponse::success($health, $statusCode);

        } catch (\Exception $e) {
            $this->get('logger')->error('Failed to get queue health', [
                'queue_name' => $queueName,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to get queue health: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 批量获取任务状态
     */
    #[Route('/wechat/sync-tasks/batch-status', methods: ['POST'])]
    public function getBatchTaskStatus(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $taskIds = $data['task_ids'] ?? [];

            if (empty($taskIds) || count($taskIds) > 50) {
                return ApiResponse::error('Invalid task IDs (max 50 tasks allowed)', 400);
            }

            $statuses = $this->taskStatusService->getBatchStatus($taskIds);

            return ApiResponse::success([
                'task_statuses' => $statuses,
                'total_tasks' => count($taskIds),
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            $this->get('logger')->error('Failed to get batch task status', [
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to get batch task status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 估算任务执行时长
     */
    private function estimateDuration(SyncWechatDto $dto): string
    {
        $articleLimit = $dto->articleLimit ?? 100;
        $baseTime = 5; // 基础时间5分钟
        $timePerArticle = 0.05; // 每篇文章0.05分钟

        $estimatedMinutes = $baseTime + ($articleLimit * $timePerArticle);

        if ($dto->shouldProcessMedia()) {
            $estimatedMinutes *= 1.5; // 处理媒体增加50%时间
        }

        if ($dto->forceSync) {
            $estimatedMinutes *= 1.2; // 强制同步增加20%时间
        }

        return sprintf('%d-%d minutes',
            (int)($estimatedMinutes * 0.8),
            (int)($estimatedMinutes * 1.2)
        );
    }

    /**
     * 获取队列位置
     */
    private function getQueuePosition(string $queueName): int
    {
        try {
            return $this->taskQueueService->getQueueSize($queueName) + 1;
        } catch (\Exception) {
            return 1;
        }
    }

    /**
     * 获取微信同步任务状态
     */
    #[Route('/wechat/sync-status/{taskId}', methods: ['GET'])]
    public function getWechatSyncStatus(string $taskId): JsonResponse
    {
        try {
            // 获取微信同步专用状态
            $status = $this->wechatSyncStatusService->getTaskStatusWithDetails($taskId);

            if (!$status) {
                return ApiResponse::error('微信同步任务不存在', 404);
            }

            return ApiResponse::success($status->toArray());

        } catch (\Exception $e) {
            $this->get('logger')->error('Failed to get wechat sync status', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('获取微信同步状态失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取微信同步进度详情
     */
    #[Route('/wechat/sync-progress/{taskId}', methods: ['GET'])]
    public function getWechatSyncProgress(string $taskId): JsonResponse
    {
        try {
            // 获取微信同步进度详情
            $progress = $this->wechatSyncStatusService->getSyncProgressDetail($taskId);

            if (!$progress) {
                return ApiResponse::error('微信同步任务不存在', 404);
            }

            return ApiResponse::success($progress->toArray());

        } catch (\Exception $e) {
            $this->get('logger')->error('Failed to get wechat sync progress', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('获取微信同步进度失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取账号同步历史
     */
    #[Route('/wechat/sync-history/{accountId}', methods: ['GET'])]
    public function getWechatSyncHistory(string $accountId, Request $request): JsonResponse
    {
        try {
            $page = max(1, (int)($request->query->get('page', 1)));
            $limit = min(50, max(1, (int)($request->query->get('limit', 20))));
            $status = $request->query->get('status');
            $startDate = $request->query->get('start_date');
            $endDate = $request->query->get('end_date');

            $history = $this->wechatSyncStatusService->getAccountSyncHistory(
                $accountId,
                $page,
                $limit,
                $status,
                $startDate,
                $endDate
            );

            return ApiResponse::success($history);

        } catch (\Exception $e) {
            $this->get('logger')->error('Failed to get wechat sync history', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('获取同步历史失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取同步任务列表
     */
    #[Route('/wechat/sync-list', methods: ['GET'])]
    public function getWechatSyncList(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int)($request->query->get('page', 1)));
            $limit = min(100, max(1, (int)($request->query->get('limit', 20))));
            $status = $request->query->get('status');
            $accountId = $request->query->get('account_id');
            $syncType = $request->query->get('sync_type');
            $sortBy = $request->query->get('sort_by', 'created_at');
            $sortOrder = $request->query->get('sort_order', 'desc');

            $syncList = $this->wechatSyncStatusService->getSyncTaskList(
                $page,
                $limit,
                $status,
                $accountId,
                $syncType,
                $sortBy,
                $sortOrder
            );

            return ApiResponse::success($syncList);

        } catch (\Exception $e) {
            $this->get('logger')->error('Failed to get wechat sync list', [
                'error' => $e->getMessage(),
                'query_params' => $request->query->all(),
            ]);

            return ApiResponse::error('获取同步任务列表失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取实时进度更新（WebSocket兼容的长轮询接口）
     */
    #[Route('/wechat/sync-progress-poll/{taskId}', methods: ['GET'])]
    public function pollWechatSyncProgress(string $taskId, Request $request): JsonResponse
    {
        try {
            $lastUpdate = $request->query->get('last_update');
            $timeout = min(30, max(5, (int)($request->query->get('timeout', 15))));

            // 使用长轮询获取实时进度
            $progress = $this->wechatSyncStatusService->pollProgressUpdate($taskId, $lastUpdate, $timeout);

            if (!$progress) {
                return ApiResponse::error('微信同步任务不存在', 404);
            }

            return ApiResponse::success($progress->toArray());

        } catch (\Exception $e) {
            $this->get('logger')->error('Failed to poll wechat sync progress', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('轮询同步进度失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取同步统计信息
     */
    #[Route('/wechat/sync-statistics', methods: ['GET'])]
    public function getWechatSyncStatistics(Request $request): JsonResponse
    {
        try {
            $accountId = $request->query->get('account_id');
            $period = $request->query->get('period', '7d'); // 1d, 7d, 30d
            $groupBy = $request->query->get('group_by', 'day'); // hour, day, week, month

            $statistics = $this->wechatSyncStatusService->getSyncStatistics($accountId, $period, $groupBy);

            return ApiResponse::success($statistics);

        } catch (\Exception $e) {
            $this->get('logger')->error('Failed to get wechat sync statistics', [
                'error' => $e->getMessage(),
                'query_params' => $request->query->all(),
            ]);

            return ApiResponse::error('获取同步统计失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取同步性能指标
     */
    #[Route('/wechat/sync-performance/{taskId}', methods: ['GET'])]
    public function getWechatSyncPerformance(string $taskId): JsonResponse
    {
        try {
            $performance = $this->wechatSyncStatusService->getSyncPerformanceMetrics($taskId);

            if (!$performance) {
                return ApiResponse::error('微信同步任务不存在', 404);
            }

            return ApiResponse::success($performance);

        } catch (\Exception $e) {
            $this->get('logger')->error('Failed to get wechat sync performance', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('获取同步性能指标失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取同步错误详情
     */
    #[Route('/wechat/sync-errors/{taskId}', methods: ['GET'])]
    public function getWechatSyncErrors(string $taskId, Request $request): JsonResponse
    {
        try {
            $page = max(1, (int)($request->query->get('page', 1)));
            $limit = min(100, max(1, (int)($request->query->get('limit', 20))));
            $severity = $request->query->get('severity'); // critical, warning, info
            $phase = $request->query->get('phase');

            $errors = $this->wechatSyncStatusService->getSyncErrorDetails($taskId, $page, $limit, $severity, $phase);

            if (!$errors) {
                return ApiResponse::error('微信同步任务不存在', 404);
            }

            return ApiResponse::success($errors);

        } catch (\Exception $e) {
            $this->get('logger')->error('Failed to get wechat sync errors', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('获取同步错误详情失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 重试同步任务（微信专用）
     */
    #[Route('/wechat/sync-retry/{taskId}', methods: ['POST'])]
    public function retryWechatSyncTask(string $taskId, Request $request): JsonResponse
    {
        try {
            $retryFailedOnly = $request->request->get('retry_failed_only', false);
            $resetProgress = $request->request->get('reset_progress', false);

            $result = $this->wechatSyncStatusService->retrySyncTask($taskId, $retryFailedOnly, $resetProgress);

            if (!$result) {
                return ApiResponse::error('微信同步任务不存在或无法重试', 404);
            }

            return ApiResponse::success([
                'task_id' => $taskId,
                'retry_initiated' => true,
                'retry_failed_only' => $retryFailedOnly,
                'reset_progress' => $resetProgress,
                'message' => '微信同步任务重试已启动'
            ]);

        } catch (\Exception $e) {
            $this->get('logger')->error('Failed to retry wechat sync task', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('重试同步任务失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 取消同步任务（微信专用）
     */
    #[Route('/wechat/sync-cancel/{taskId}', methods: ['POST'])]
    public function cancelWechatSyncTask(string $taskId, Request $request): JsonResponse
    {
        try {
            $reason = $request->request->get('reason', '用户取消');
            $force = $request->request->get('force', false);

            $result = $this->wechatSyncStatusService->cancelSyncTask($taskId, $reason, $force);

            if (!$result) {
                return ApiResponse::error('微信同步任务不存在或无法取消', 404);
            }

            return ApiResponse::success([
                'task_id' => $taskId,
                'cancelled' => true,
                'reason' => $reason,
                'force_cancelled' => $force,
                'cancelled_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'message' => '微信同步任务已取消'
            ]);

        } catch (\Exception $e) {
            $this->get('logger')->error('Failed to cancel wechat sync task', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('取消同步任务失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 格式化时长
     */
    private function formatDuration(float $duration): string
    {
        if ($duration < 60) {
            return sprintf('%ds', (int)$duration);
        } elseif ($duration < 3600) {
            $minutes = (int)($duration / 60);
            $seconds = (int)($duration % 60);
            return sprintf('%dm %ds', $minutes, $seconds);
        } else {
            $hours = (int)($duration / 3600);
            $minutes = (int)(($duration % 3600) / 60);
            return sprintf('%dh %dm', $hours, $minutes);
        }
    }
}
