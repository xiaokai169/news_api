<?php

namespace App\Service;

use App\DTO\Response\WechatSyncStatusDto;
use App\DTO\Response\WechatSyncProgressDto;
use App\Entity\AsyncTask;
use App\Repository\AsyncTaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * 微信同步状态专用管理服务
 */
class WechatSyncStatusService
{
    private const CACHE_TTL = 300; // 5分钟缓存
    private const LONG_POLLING_TIMEOUT = 30; // 长轮询超时时间

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AsyncTaskRepository $asyncTaskRepository,
        private LoggerInterface $logger,
        private CacheItemPoolInterface $cache,
        private TaskStatusService $taskStatusService
    ) {
    }

    /**
     * 获取带详情的任务状态
     */
    public function getTaskStatusWithDetails(string $taskId): ?WechatSyncStatusDto
    {
        try {
            // 检查缓存
            $cacheKey = "wechat_sync_status_{$taskId}";
            $cached = $this->cache->getItem($cacheKey);

            if ($cached->isHit()) {
                return $cached->get();
            }

            // 从数据库获取任务
            $task = $this->asyncTaskRepository->find($taskId);
            if (!$task || $task->getType() !== AsyncTask::TYPE_WECHAT_SYNC) {
                return null;
            }

            // 获取详细状态信息
            $statusDto = $this->buildStatusDto($task);

            // 缓存结果
            $cached->set($statusDto);
            $cached->expiresAfter(self::CACHE_TTL);
            $this->cache->save($cached);

            return $statusDto;

        } catch (\Exception $e) {
            $this->logger->error('Failed to get wechat sync status with details', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 获取同步进度详情
     */
    public function getSyncProgressDetail(string $taskId): ?WechatSyncProgressDto
    {
        try {
            $task = $this->asyncTaskRepository->find($taskId);
            if (!$task || $task->getType() !== AsyncTask::TYPE_WECHAT_SYNC) {
                return null;
            }

            // 构建进度详情
            $progressDto = $this->buildProgressDto($task);

            return $progressDto;

        } catch (\Exception $e) {
            $this->logger->error('Failed to get sync progress detail', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 获取账号同步历史
     */
    public function getAccountSyncHistory(
        string $accountId,
        int $page = 1,
        int $limit = 20,
        ?string $status = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        try {
            $offset = ($page - 1) * $limit;

            // 构建查询条件
            $criteria = [
                'type' => AsyncTask::TYPE_WECHAT_SYNC,
                'account_id' => $accountId,
            ];

            if ($status) {
                $criteria['status'] = $status;
            }

            // 查询任务列表
            $tasks = $this->asyncTaskRepository->findByCriteria($criteria, ['createdAt' => 'DESC'], $limit, $offset);
            $total = $this->asyncTaskRepository->countByCriteria($criteria);

            // 格式化历史记录
            $history = [];
            foreach ($tasks as $task) {
                $history[] = [
                    'task_id' => $task->getId(),
                    'status' => $task->getStatus(),
                    'created_at' => $task->getCreatedAt()->format('Y-m-d H:i:s'),
                    'started_at' => $task->getStartedAt()?->format('Y-m-d H:i:s'),
                    'completed_at' => $task->getCompletedAt()?->format('Y-m-d H:i:s'),
                    'duration' => $task->getExecutionDuration(),
                    'progress' => $task->getProgress(),
                    'result_summary' => $this->extractResultSummary($task->getResult()),
                    'error_message' => $task->getErrorMessage(),
                ];
            }

            return [
                'account_id' => $accountId,
                'history' => $history,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit),
                    'has_more' => ($page * $limit) < $total,
                ],
                'filters' => [
                    'status' => $status,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to get account sync history', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 获取同步任务列表
     */
    public function getSyncTaskList(
        int $page = 1,
        int $limit = 20,
        ?string $status = null,
        ?string $accountId = null,
        ?string $syncType = null,
        string $sortBy = 'created_at',
        string $sortOrder = 'desc'
    ): array {
        try {
            $offset = ($page - 1) * $limit;

            // 构建查询条件
            $criteria = ['type' => AsyncTask::TYPE_WECHAT_SYNC];

            if ($status) {
                $criteria['status'] = $status;
            }

            // 构建排序
            $orderBy = [$sortBy => $sortOrder];

            // 查询任务列表
            $tasks = $this->asyncTaskRepository->findByCriteria($criteria, $orderBy, $limit, $offset);
            $total = $this->asyncTaskRepository->countByCriteria($criteria);

            // 格式化任务列表
            $taskList = [];
            foreach ($tasks as $task) {
                $taskList[] = [
                    'task_id' => $task->getId(),
                    'status' => $task->getStatus(),
                    'priority' => $task->getPriority(),
                    'progress' => $task->getProgress(),
                    'created_at' => $task->getCreatedAt()->format('Y-m-d H:i:s'),
                    'started_at' => $task->getStartedAt()?->format('Y-m-d H:i:s'),
                    'completed_at' => $task->getCompletedAt()?->format('Y-m-d H:i:s'),
                    'duration' => $task->getExecutionDuration(),
                    'retry_count' => $task->getRetryCount(),
                    'account_id' => $this->extractAccountId($task->getParameters()),
                    'sync_type' => $this->extractSyncType($task->getParameters()),
                    'result_summary' => $this->extractResultSummary($task->getResult()),
                ];
            }

            return [
                'tasks' => $taskList,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit),
                    'has_more' => ($page * $limit) < $total,
                ],
                'filters' => [
                    'status' => $status,
                    'account_id' => $accountId,
                    'sync_type' => $syncType,
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                ],
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to get sync task list', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 长轮询进度更新
     */
    public function pollProgressUpdate(string $taskId, ?string $lastUpdate = null, int $timeout = 15): ?WechatSyncProgressDto
    {
        try {
            $startTime = time();
            $lastKnownUpdate = $lastUpdate;

            while (time() - $startTime < $timeout) {
                $progress = $this->getSyncProgressDetail($taskId);

                if (!$progress) {
                    return null;
                }

                $currentUpdate = $progress->getLastUpdate();

                // 如果有更新或者是第一次请求
                if (!$lastKnownUpdate || $currentUpdate > $lastKnownUpdate) {
                    return $progress;
                }

                // 等待一段时间再检查
                usleep(500000); // 0.5秒
            }

            // 超时返回当前进度
            return $this->getSyncProgressDetail($taskId);

        } catch (\Exception $e) {
            $this->logger->error('Failed to poll progress update', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 获取同步统计信息
     */
    public function getSyncStatistics(?string $accountId = null, string $period = '7d', string $groupBy = 'day'): array
    {
        try {
            // 计算时间范围
            $endDate = new \DateTime();
            $startDate = match ($period) {
                '1d' => (clone $endDate)->modify('-1 day'),
                '7d' => (clone $endDate)->modify('-7 days'),
                '30d' => (clone $endDate)->modify('-30 days'),
                default => (clone $endDate)->modify('-7 days'),
            };

            // 构建查询条件
            $criteria = [
                'type' => AsyncTask::TYPE_WECHAT_SYNC,
                'created_at >= ?' => $startDate,
                'created_at <= ?' => $endDate,
            ];

            if ($accountId) {
                $criteria['account_id'] = $accountId;
            }

            // 获取统计数据
            $statistics = $this->asyncTaskRepository->getStatisticsByPeriod($criteria, $groupBy);

            return [
                'period' => $period,
                'group_by' => $groupBy,
                'date_range' => [
                    'start_date' => $startDate->format('Y-m-d H:i:s'),
                    'end_date' => $endDate->format('Y-m-d H:i:s'),
                ],
                'statistics' => $statistics,
                'account_id' => $accountId,
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to get sync statistics', [
                'account_id' => $accountId,
                'period' => $period,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 获取同步性能指标
     */
    public function getSyncPerformanceMetrics(string $taskId): ?array
    {
        try {
            $task = $this->asyncTaskRepository->find($taskId);
            if (!$task || $task->getType() !== AsyncTask::TYPE_WECHAT_SYNC) {
                return null;
            }

            // 获取性能指标
            $metrics = [
                'task_id' => $taskId,
                'execution_time' => $task->getExecutionDuration(),
                'average_step_time' => $this->calculateAverageStepTime($task),
                'peak_memory_usage' => $this->extractMemoryUsage($task->getResult()),
                'database_operations' => $this->extractDatabaseOperations($task->getResult()),
                'api_calls' => $this->extractApiCalls($task->getResult()),
                'error_rate' => $this->calculateErrorRate($task),
                'throughput' => $this->calculateThroughput($task),
            ];

            return $metrics;

        } catch (\Exception $e) {
            $this->logger->error('Failed to get sync performance metrics', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 获取同步错误详情
     */
    public function getSyncErrorDetails(
        string $taskId,
        int $page = 1,
        int $limit = 20,
        ?string $severity = null,
        ?string $phase = null
    ): ?array {
        try {
            $task = $this->asyncTaskRepository->find($taskId);
            if (!$task || $task->getType() !== AsyncTask::TYPE_WECHAT_SYNC) {
                return null;
            }

            // 从任务结果中提取错误信息
            $errors = $this->extractErrorsFromResult($task->getResult());

            // 过滤错误
            if ($severity) {
                $errors = array_filter($errors, fn($error) => $error['severity'] === $severity);
            }

            if ($phase) {
                $errors = array_filter($errors, fn($error) => $error['phase'] === $phase);
            }

            // 分页
            $offset = ($page - 1) * $limit;
            $pagedErrors = array_slice($errors, $offset, $limit);
            $total = count($errors);

            return [
                'task_id' => $taskId,
                'errors' => $pagedErrors,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit),
                    'has_more' => ($page * $limit) < $total,
                ],
                'filters' => [
                    'severity' => $severity,
                    'phase' => $phase,
                ],
                'error_summary' => [
                    'total_errors' => $total,
                    'critical_errors' => count(array_filter($errors, fn($e) => $e['severity'] === 'critical')),
                    'warning_errors' => count(array_filter($errors, fn($e) => $e['severity'] === 'warning')),
                    'info_errors' => count(array_filter($errors, fn($e) => $e['severity'] === 'info')),
                ],
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to get sync error details', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 重试同步任务
     */
    public function retrySyncTask(string $taskId, bool $retryFailedOnly = false, bool $resetProgress = false): bool
    {
        try {
            $task = $this->asyncTaskRepository->find($taskId);
            if (!$task || $task->getType() !== AsyncTask::TYPE_WECHAT_SYNC) {
                return false;
            }

            // 检查任务状态是否允许重试
            if (!in_array($task->getStatus(), [AsyncTask::STATUS_FAILED, AsyncTask::STATUS_CANCELLED])) {
                return false;
            }

            // 重置任务状态
            if ($resetProgress) {
                $task->setProgress(0);
                $task->setResult(null);
                $task->setErrorMessage(null);
            }

            $task->setStatus(AsyncTask::STATUS_PENDING);
            $task->setRetryCount($task->getRetryCount() + 1);

            $this->entityManager->flush();

            // 清除缓存
            $this->invalidateTaskCache($taskId);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to retry sync task', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 取消同步任务
     */
    public function cancelSyncTask(string $taskId, string $reason = '用户取消', bool $force = false): bool
    {
        try {
            $task = $this->asyncTaskRepository->find($taskId);
            if (!$task || $task->getType() !== AsyncTask::TYPE_WECHAT_SYNC) {
                return false;
            }

            // 检查任务状态是否允许取消
            if (!in_array($task->getStatus(), [AsyncTask::STATUS_PENDING, AsyncTask::STATUS_RUNNING])) {
                if (!$force) {
                    return false;
                }
            }

            $task->setStatus(AsyncTask::STATUS_CANCELLED);
            $task->setErrorMessage($reason);
            $task->setCompletedAt(new \DateTime());

            $this->entityManager->flush();

            // 清除缓存
            $this->invalidateTaskCache($taskId);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to cancel sync task', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 构建状态DTO
     */
    private function buildStatusDto(AsyncTask $task): WechatSyncStatusDto
    {
        $parameters = $task->getParameters();
        $result = $task->getResult();

        return new WechatSyncStatusDto(
            taskId: $task->getId(),
            status: $task->getStatus(),
            progress: $task->getProgress(),
            createdAt: $task->getCreatedAt(),
            startedAt: $task->getStartedAt(),
            completedAt: $task->getCompletedAt(),
            accountId: $parameters['account_id'] ?? null,
            syncType: $parameters['sync_type'] ?? 'articles',
            syncScope: $parameters['sync_scope'] ?? 'recent',
            articleLimit: $parameters['article_limit'] ?? 100,
            forceSync: $parameters['force_sync'] ?? false,
            errorMessage: $task->getErrorMessage(),
            retryCount: $task->getRetryCount(),
            maxRetries: $task->getMaxRetries(),
            priority: $task->getPriority(),
            result: $result,
            estimatedDuration: $this->calculateEstimatedDuration($parameters),
            currentPhase: $this->extractCurrentPhase($result),
            processedCount: $this->extractProcessedCount($result),
            totalCount: $this->extractTotalCount($parameters, $result),
            successCount: $this->extractSuccessCount($result),
            failureCount: $this->extractFailureCount($result),
            skippedCount: $this->extractSkippedCount($result),
            errorDetails: $this->extractErrorDetails($result),
            performanceMetrics: $this->extractPerformanceMetrics($result),
            nextRetryAt: $this->calculateNextRetryAt($task),
            queuePosition: $this->getQueuePosition($task),
            resourceUsage: $this->extractResourceUsage($result)
        );
    }

    /**
     * 构建进度DTO
     */
    private function buildProgressDto(AsyncTask $task): WechatSyncProgressDto
    {
        $parameters = $task->getParameters();
        $result = $task->getResult();

        return new WechatSyncProgressDto(
            taskId: $task->getId(),
            status: $task->getStatus(),
            overallProgress: $task->getProgress(),
            currentPhase: $this->extractCurrentPhase($result),
            phaseProgress: $this->extractPhaseProgress($result),
            processedCount: $this->extractProcessedCount($result),
            totalCount: $this->extractTotalCount($parameters, $result),
            successCount: $this->extractSuccessCount($result),
            failureCount: $this->extractFailureCount($result),
            skippedCount: $this->extractSkippedCount($result),
            currentItemId: $this->extractCurrentItemId($result),
            currentItemName: $this->extractCurrentItemName($result),
            startTime: $task->getStartedAt(),
            lastUpdate: $this->extractLastUpdate($result),
            estimatedTimeRemaining: $this->calculateETA($task, $result),
            averageProcessingTime: $this->calculateAverageProcessingTime($result),
            processingRate: $this->calculateProcessingRate($task, $result),
            errorRate: $this->calculateErrorRate($task),
            memoryUsage: $this->extractMemoryUsage($result),
            cpuUsage: $this->extractCpuUsage($result),
            databaseConnections: $this->extractDatabaseConnections($result),
            networkRequests: $this->extractNetworkRequests($result),
            queueSize: $this->getQueueSize($task),
            workersActive: $this->getActiveWorkers(),
            cacheHitRate: $this->extractCacheHitRate($result),
            errorStatistics: $this->extractErrorStatistics($result),
            performanceMetrics: $this->extractPerformanceMetrics($result),
            resourceUsage: $this->extractResourceUsage($result),
            timeline: $this->extractTimeline($result),
            steps: $this->extractSteps($result)
        );
    }

    /**
     * 辅助方法：从结果中提取账号ID
     */
    private function extractAccountId(?array $parameters): ?string
    {
        return $parameters['account_id'] ?? null;
    }

    /**
     * 辅助方法：从结果中提取同步类型
     */
    private function extractSyncType(?array $parameters): ?string
    {
        return $parameters['sync_type'] ?? null;
    }

    /**
     * 辅助方法：从结果中提取摘要信息
     */
    private function extractResultSummary(?array $result): array
    {
        if (!$result) {
            return [];
        }

        return [
            'processed' => $result['processed_count'] ?? 0,
            'success' => $result['success_count'] ?? 0,
            'failure' => $result['failure_count'] ?? 0,
            'skipped' => $result['skipped_count'] ?? 0,
        ];
    }

    /**
     * 辅助方法：计算预计剩余时间
     */
    private function calculateETA(AsyncTask $task, ?array $result): ?float
    {
        if (!$task->getStartedAt() || !$result) {
            return null;
        }

        $processed = $result['processed_count'] ?? 0;
        $total = $result['total_count'] ?? 0;

        if ($processed <= 0 || $total <= 0) {
            return null;
        }

        $elapsed = time() - $task->getStartedAt()->getTimestamp();
        $rate = $processed / $elapsed;

        if ($rate <= 0) {
            return null;
        }

        $remaining = $total - $processed;
        return $remaining / $rate;
    }

    /**
     * 辅助方法：清除任务缓存
     */
    private function invalidateTaskCache(string $taskId): void
    {
        $cacheKeys = [
            "wechat_sync_status_{$taskId}",
            "wechat_sync_progress_{$taskId}",
        ];

        foreach ($cacheKeys as $key) {
            $this->cache->deleteItem($key);
        }
    }

    // 其他辅助方法的实现...
    private function extractCurrentPhase(?array $result): ?string
    {
        return $result['current_phase'] ?? null;
    }

    private function extractProcessedCount(?array $result): int
    {
        return $result['processed_count'] ?? 0;
    }

    private function extractTotalCount(?array $parameters, ?array $result): int
    {
        return $result['total_count'] ?? $parameters['article_limit'] ?? 0;
    }

    private function extractSuccessCount(?array $result): int
    {
        return $result['success_count'] ?? 0;
    }

    private function extractFailureCount(?array $result): int
    {
        return $result['failure_count'] ?? 0;
    }

    private function extractSkippedCount(?array $result): int
    {
        return $result['skipped_count'] ?? 0;
    }

    private function extractErrorDetails(?array $result): array
    {
        return $result['error_details'] ?? [];
    }

    private function extractPerformanceMetrics(?array $result): array
    {
        return $result['performance_metrics'] ?? [];
    }

    private function extractResourceUsage(?array $result): array
    {
        return $result['resource_usage'] ?? [];
    }

    private function calculateEstimatedDuration(?array $parameters): ?string
    {
        if (!$parameters) {
            return null;
        }

        $articleLimit = $parameters['article_limit'] ?? 100;
        $baseTime = 5; // 基础时间5分钟
        $timePerArticle = 0.05; // 每篇文章0.05分钟

        $estimatedMinutes = $baseTime + ($articleLimit * $timePerArticle);

        if ($parameters['process_media'] ?? false) {
            $estimatedMinutes *= 1.5;
        }

        if ($parameters['force_sync'] ?? false) {
            $estimatedMinutes *= 1.2;
        }

        return sprintf('%d-%d minutes',
            (int)($estimatedMinutes * 0.8),
            (int)($estimatedMinutes * 1.2)
        );
    }

    private function calculateNextRetryAt(AsyncTask $task): ?\DateTime
    {
        if ($task->getStatus() !== AsyncTask::STATUS_FAILED) {
            return null;
        }

        $retryDelay = min(300, pow(2, $task->getRetryCount()) * 30); // 指数退避，最大5分钟
        return (clone $task->getCompletedAt())->add(new \DateInterval("PT{$retryDelay}S"));
    }

    private function getQueuePosition(AsyncTask $task): int
    {
        // 这里需要实现队列位置计算逻辑
        return 1;
    }

    private function calculateAverageStepTime(AsyncTask $task): float
    {
        // 计算平均步骤时间
        return 0.0;
    }

    private function extractMemoryUsage(?array $result): array
    {
        return $result['memory_usage'] ?? [];
    }

    private function extractDatabaseOperations(?array $result): array
    {
        return $result['database_operations'] ?? [];
    }

    private function extractApiCalls(?array $result): array
    {
        return $result['api_calls'] ?? [];
    }

    private function calculateErrorRate(AsyncTask $task): float
    {
        $result = $task->getResult();
        if (!$result) {
            return 0.0;
        }

        $processed = $result['processed_count'] ?? 0;
        $failed = $result['failure_count'] ?? 0;

        if ($processed <= 0) {
            return 0.0;
        }

        return ($failed / $processed) * 100;
    }

    private function calculateThroughput(AsyncTask $task): float
    {
        // 计算吞吐量
        return 0.0;
    }

    private function extractErrorsFromResult(?array $result): array
    {
        return $result['errors'] ?? [];
    }

    private function extractPhaseProgress(?array $result): array
    {
        return $result['phase_progress'] ?? [];
    }

    private function extractCurrentItemId(?array $result): ?string
    {
        return $result['current_item_id'] ?? null;
    }

    private function extractCurrentItemName(?array $result): ?string
    {
        return $result['current_item_name'] ?? null;
    }

    private function extractLastUpdate(?array $result): ?\DateTime
    {
        $timestamp = $result['last_update'] ?? null;
        return $timestamp ? new \DateTime("@{$timestamp}") : null;
    }

    private function calculateAverageProcessingTime(?array $result): float
    {
        return $result['average_processing_time'] ?? 0.0;
    }

    private function calculateProcessingRate(AsyncTask $task, ?array $result): float
    {
        return $result['processing_rate'] ?? 0.0;
    }

    private function extractCpuUsage(?array $result): array
    {
        return $result['cpu_usage'] ?? [];
    }

    private function extractDatabaseConnections(?array $result): int
    {
        return $result['database_connections'] ?? 0;
    }

    private function extractNetworkRequests(?array $result): int
    {
        return $result['network_requests'] ?? 0;
    }

    private function getQueueSize(AsyncTask $task): int
    {
        // 获取队列大小
        return 0;
    }

    private function getActiveWorkers(): int
    {
        // 获取活跃工作进程数
        return 0;
    }

    private function extractCacheHitRate(?array $result): float
    {
        return $result['cache_hit_rate'] ?? 0.0;
    }

    private function extractErrorStatistics(?array $result): array
    {
        return $result['error_statistics'] ?? [];
    }

    private function extractTimeline(?array $result): array
    {
        return $result['timeline'] ?? [];
    }

    private function extractSteps(?array $result): array
    {
        return $result['steps'] ?? [];
    }
}
