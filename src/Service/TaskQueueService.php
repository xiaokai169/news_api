<?php

namespace App\Service;

use App\Entity\AsyncTask;
use App\Entity\QueueStatistics;
use App\Repository\AsyncTaskRepository;
use App\Repository\QueueStatisticsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * 任务队列服务
 * 负责队列管理和任务调度
 */
class TaskQueueService
{
    public const DEFAULT_QUEUE_NAME = 'default';
    public const HIGH_PRIORITY_QUEUE = 'high_priority';
    public const LOW_PRIORITY_QUEUE = 'low_priority';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AsyncTaskRepository $asyncTaskRepository,
        private QueueStatisticsRepository $queueStatisticsRepository,
        private LoggerInterface $logger,
        #[Autowire('%env(default:10)%env(int:ASYNC_TASK_BATCH_SIZE)%')]
        private int $batchSize = 10,
        #[Autowire('%env(default:300)%env(int:ASYNC_TASK_TIMEOUT)%')]
        private int $taskTimeout = 300
    ) {
    }

    /**
     * 任务入队
     */
    public function enqueue(AsyncTask $task, ?string $queueName = null): bool
    {
        try {
            // 设置队列名称
            if (!$queueName) {
                $queueName = $this->determineQueueName($task);
            }

            $task->setQueueName($queueName);
            $task->setStatus(AsyncTask::STATUS_PENDING);

            $this->entityManager->persist($task);
            $this->entityManager->flush();

            // 更新统计信息
            $this->updateQueueStatistics($queueName, 'enqueued', 1);

            $this->logger->info('Task enqueued', [
                'task_id' => $task->getId(),
                'type' => $task->getType(),
                'queue_name' => $queueName,
                'priority' => $task->getPriority(),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to enqueue task', [
                'task_id' => $task->getId(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 任务出队
     */
    public function dequeue(?string $queueName = null, int $limit = null): array
    {
        try {
            $limit = $limit ?: $this->batchSize;
            $tasks = $this->asyncTaskRepository->findRunnableTasks($queueName, $limit);

            $dequeuedTasks = [];
            foreach ($tasks as $task) {
                // 标记为运行中
                if ($this->markTaskAsRunning($task)) {
                    $dequeuedTasks[] = $task;

                    // 更新统计信息
                    $this->updateQueueStatistics($task->getQueueName(), 'dequeued', 1);
                }
            }

            if (!empty($dequeuedTasks)) {
                $this->logger->info('Tasks dequeued', [
                    'queue_name' => $queueName,
                    'count' => count($dequeuedTasks),
                    'task_ids' => array_map(fn($task) => $task->getId(), $dequeuedTasks),
                ]);
            }

            return $dequeuedTasks;
        } catch (\Exception $e) {
            $this->logger->error('Failed to dequeue tasks', [
                'queue_name' => $queueName,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * 处理队列
     */
    public function processQueue(?string $queueName = null): array
    {
        $results = [
            'processed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        try {
            // 获取可运行的任务
            $tasks = $this->dequeue($queueName);

            if (empty($tasks)) {
                $this->logger->info('No tasks to process', ['queue_name' => $queueName]);
                return $results;
            }

            foreach ($tasks as $task) {
                try {
                    $this->processTask($task);
                    $results['processed']++;
                } catch (\Exception $e) {
                    $this->markTaskAsFailed($task, $e);
                    $results['failed']++;
                    $results['errors'][] = [
                        'task_id' => $task->getId(),
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $this->logger->info('Queue processing completed', [
                'queue_name' => $queueName,
                'processed' => $results['processed'],
                'failed' => $results['failed'],
                'skipped' => $results['skipped'],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Queue processing failed', [
                'queue_name' => $queueName,
                'error' => $e->getMessage(),
            ]);

            $results['errors'][] = [
                'error' => $e->getMessage(),
            ];
        }

        return $results;
    }

    /**
     * 获取队列统计信息
     */
    public function getQueueStats(?string $queueName = null): array
    {
        try {
            $statistics = $this->asyncTaskRepository->getQueueStatistics($queueName);

            // 获取队列统计详情
            if ($queueName) {
                $queueDetails = $this->queueStatisticsRepository->getQueueSummary($queueName);
                $statistics['details'] = $queueDetails;
            } else {
                $statistics['all_queues'] = $this->queueStatisticsRepository->getAllQueuesOverview();
            }

            return $statistics;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get queue statistics', [
                'queue_name' => $queueName,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * 获取队列大小
     */
    public function getQueueSize(?string $queueName = null): int
    {
        try {
            $statistics = $this->asyncTaskRepository->getQueueStatistics($queueName);
            return $statistics[AsyncTask::STATUS_PENDING] ?? 0;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get queue size', [
                'queue_name' => $queueName,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * 清理过期任务
     */
    public function cleanupExpiredTasks(): array
    {
        $results = [
            'cleaned' => 0,
            'errors' => [],
        ];

        try {
            $expiredTasks = $this->asyncTaskRepository->findExpiredTasks();

            foreach ($expiredTasks as $task) {
                try {
                    $task->setStatus(AsyncTask::STATUS_FAILED);
                    $task->setCompletedAt(new \DateTime());
                    $task->setErrorMessage('Task expired');

                    $this->entityManager->persist($task);
                    $results['cleaned']++;
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'task_id' => $task->getId(),
                        'error' => $e->getMessage(),
                    ];
                }
            }

            if ($results['cleaned'] > 0) {
                $this->entityManager->flush();
            }

            $this->logger->info('Expired tasks cleaned up', [
                'cleaned' => $results['cleaned'],
                'errors' => count($results['errors']),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to cleanup expired tasks', [
                'error' => $e->getMessage(),
            ]);

            $results['errors'][] = ['error' => $e->getMessage()];
        }

        return $results;
    }

    /**
     * 重试失败的任务
     */
    public function retryFailedTasks(?string $queueName = null): array
    {
        $results = [
            'retried' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        try {
            $retryableTasks = $this->asyncTaskRepository->findRetryableTasks();

            foreach ($retryableTasks as $task) {
                if ($queueName && $task->getQueueName() !== $queueName) {
                    continue;
                }

                try {
                    if ($this->retryTask($task)) {
                        $results['retried']++;
                    } else {
                        $results['skipped']++;
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'task_id' => $task->getId(),
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $this->logger->info('Failed tasks retried', [
                'retried' => $results['retried'],
                'skipped' => $results['skipped'],
                'errors' => count($results['errors']),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to retry tasks', [
                'queue_name' => $queueName,
                'error' => $e->getMessage(),
            ]);

            $results['errors'][] = ['error' => $e->getMessage()];
        }

        return $results;
    }

    /**
     * 获取队列健康状态
     */
    public function getQueueHealth(?string $queueName = null): array
    {
        try {
            $statistics = $this->asyncTaskRepository->getQueueStatistics($queueName);
            $longRunningTasks = $this->asyncTaskRepository->findLongRunningTasks();

            $health = [
                'status' => 'healthy',
                'pending_tasks' => $statistics[AsyncTask::STATUS_PENDING] ?? 0,
                'running_tasks' => $statistics[AsyncTask::STATUS_RUNNING] ?? 0,
                'failed_tasks' => $statistics[AsyncTask::STATUS_FAILED] ?? 0,
                'long_running_tasks' => count($longRunningTasks),
                'total_tasks' => $statistics['total'] ?? 0,
            ];

            // 判断健康状态
            if ($health['failed_tasks'] > $health['total_tasks'] * 0.1) {
                $health['status'] = 'warning';
            }

            if ($health['long_running_tasks'] > 0) {
                $health['status'] = 'warning';
            }

            if ($health['failed_tasks'] > $health['total_tasks'] * 0.3) {
                $health['status'] = 'critical';
            }

            return $health;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get queue health', [
                'queue_name' => $queueName,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 确定任务应该进入哪个队列
     */
    private function determineQueueName(AsyncTask $task): string
    {
        $priority = $task->getPriority();
        $type = $task->getType();

        // 根据优先级确定队列
        if ($priority >= 8) {
            return self::HIGH_PRIORITY_QUEUE;
        } elseif ($priority <= 3) {
            return self::LOW_PRIORITY_QUEUE;
        }

        // 根据任务类型确定队列
        return match ($type) {
            AsyncTask::TYPE_WECHAT_SYNC => 'wechat_sync',
            AsyncTask::TYPE_MEDIA_PROCESS => 'media_process',
            AsyncTask::TYPE_BATCH_PROCESS => 'batch_process',
            default => self::DEFAULT_QUEUE_NAME,
        };
    }

    /**
     * 标记任务为运行中
     */
    private function markTaskAsRunning(AsyncTask $task): bool
    {
        try {
            $task->setStatus(AsyncTask::STATUS_RUNNING);
            $task->setStartedAt(new \DateTime());

            $this->entityManager->persist($task);
            $this->entityManager->flush();

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to mark task as running', [
                'task_id' => $task->getId(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 处理单个任务
     */
    private function processTask(AsyncTask $task): void
    {
        $startTime = microtime(true);

        try {
            // 这里可以集成具体的任务处理器
            // 目前只是模拟处理
            $this->simulateTaskProcessing($task);

            $duration = (microtime(true) - $startTime) * 1000; // 转换为毫秒

            $task->setStatus(AsyncTask::STATUS_COMPLETED);
            $task->setCompletedAt(new \DateTime());
            $task->setResult(['duration_ms' => round($duration)]);

            $this->entityManager->persist($task);

            // 更新统计信息
            $this->updateQueueStatistics($task->getQueueName(), 'completed', 1, (int)$duration);

        } catch (\Exception $e) {
            $this->markTaskAsFailed($task, $e);
            throw $e;
        }
    }

    /**
     * 标记任务为失败
     */
    private function markTaskAsFailed(AsyncTask $task, \Exception $exception): void
    {
        $task->setStatus(AsyncTask::STATUS_FAILED);
        $task->setCompletedAt(new \DateTime());
        $task->setErrorMessage($exception->getMessage());

        $this->entityManager->persist($task);

        // 更新统计信息
        $this->updateQueueStatistics($task->getQueueName(), 'failed', 1);
    }

    /**
     * 重试任务
     */
    private function retryTask(AsyncTask $task): bool
    {
        if (!$task->canRetry()) {
            return false;
        }

        try {
            $task->setRetryCount($task->getRetryCount() + 1);
            $task->setStatus(AsyncTask::STATUS_RETRYING);
            $task->setStartedAt(null);
            $task->setCompletedAt(null);
            $task->setErrorMessage(null);

            $this->entityManager->persist($task);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to retry task', [
                'task_id' => $task->getId(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 模拟任务处理
     */
    private function simulateTaskProcessing(AsyncTask $task): void
    {
        // 根据任务类型进行不同的模拟处理
        match ($task->getType()) {
            AsyncTask::TYPE_WECHAT_SYNC => $this->simulateWechatSync($task),
            AsyncTask::TYPE_MEDIA_PROCESS => $this->simulateMediaProcess($task),
            AsyncTask::TYPE_BATCH_PROCESS => $this->simulateBatchProcess($task),
            default => usleep(100000), // 默认等待0.1秒
        };
    }

    /**
     * 模拟微信同步任务
     */
    private function simulateWechatSync(AsyncTask $task): void
    {
        $payload = $task->getPayload();
        $articleCount = $payload['article_limit'] ?? 10;

        // 模拟处理时间：每篇文章0.1秒
        $sleepTime = $articleCount * 100000;
        usleep($sleepTime);
    }

    /**
     * 模拟媒体处理任务
     */
    private function simulateMediaProcess(AsyncTask $task): void
    {
        $payload = $task->getPayload();
        $mediaCount = count($payload['media_urls'] ?? []);

        // 模拟处理时间：每个媒体文件0.5秒
        $sleepTime = $mediaCount * 500000;
        usleep($sleepTime);
    }

    /**
     * 模拟批量处理任务
     */
    private function simulateBatchProcess(AsyncTask $task): void
    {
        $payload = $task->getPayload();
        $itemCount = $payload['item_count'] ?? 50;

        // 模拟处理时间：每个项目0.02秒
        $sleepTime = $itemCount * 20000;
        usleep($sleepTime);
    }

    /**
     * 更新队列统计信息
     */
    private function updateQueueStatistics(string $queueName, string $action, int $count, ?int $durationMs = null): void
    {
        try {
            $statistics = $this->queueStatisticsRepository->findOrCreateCurrentHour($queueName);

            match ($action) {
                'enqueued' => $statistics->incrementEnqueuedCount($count),
                'dequeued' => $statistics->incrementDequeuedCount($count),
                'completed' => $statistics->incrementCompletedCount($count),
                'failed' => $statistics->incrementFailedCount($count),
            };

            if ($durationMs && $action === 'completed') {
                $statistics->updateDurationStats($durationMs);
            }

            $this->queueStatisticsRepository->save($statistics, true);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update queue statistics', [
                'queue_name' => $queueName,
                'action' => $action,
                'count' => $count,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
