<?php

namespace App\Service;

use App\Entity\AsyncTask;
use App\Entity\TaskExecutionLog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * 批量处理器
 *
 * 负责异步任务队列系统的批量处理优化，包括：
 * - 智能批量大小调整
 * - 批量数据库操作
 * - 批量状态更新
 * - 批量通知发送
 */
class BatchProcessor
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private CacheItemPoolInterface $cache;
    private array $batchConfig;
    private array $batchStats;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        CacheItemPoolInterface $cache
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->cache = $cache;

        $this->initializeBatchConfig();
        $this->initializeBatchStats();
    }

    /**
     * 批量处理任务
     */
    public function processBatch(array $tasks, string $operation = 'update'): array
    {
        $startTime = microtime(true);
        $taskCount = count($tasks);

        $this->logger->info('开始批量处理任务', [
            'operation' => $operation,
            'task_count' => $taskCount
        ]);

        try {
            // 确定最优批量大小
            $optimalBatchSize = $this->calculateOptimalBatchSize($taskCount, $operation);

            // 分批处理
            $chunks = array_chunk($tasks, $optimalBatchSize);
            $results = [];

            foreach ($chunks as $chunkIndex => $chunk) {
                $chunkStartTime = microtime(true);

                $chunkResult = $this->processChunk($chunk, $operation);
                $results[] = $chunkResult;

                $chunkEndTime = microtime(true);
                $chunkExecutionTime = $chunkEndTime - $chunkStartTime;

                $this->logger->debug('批次处理完成', [
                    'chunk_index' => $chunkIndex,
                    'chunk_size' => count($chunk),
                    'execution_time' => $chunkExecutionTime,
                    'success_count' => $chunkResult['success_count'] ?? 0
                ]);

                // 记录批量处理统计
                $this->recordBatchStats($operation, count($chunk), $chunkExecutionTime, $chunkResult);

                // 防止内存溢出，定期清理
                if ($chunkIndex % 5 === 0) {
                    $this->entityManager->clear();
                    $this->optimizeMemory();
                }
            }

            $endTime = microtime(true);
            $totalExecutionTime = $endTime - $startTime;

            // 汇总结果
            $summary = $this->summarizeBatchResults($results, $taskCount, $totalExecutionTime);

            $this->logger->info('批量处理完成', $summary);

            return $summary;

        } catch (\Exception $e) {
            $this->logger->error('批量处理失败', [
                'operation' => $operation,
                'task_count' => $taskCount,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 批量更新任务状态
     */
    public function batchUpdateStatus(array $taskIds, string $status, array $additionalData = []): array
    {
        $this->logger->info('开始批量更新任务状态', [
            'task_ids_count' => count($taskIds),
            'status' => $status
        ]);

        try {
            $startTime = microtime(true);
            $batchSize = $this->calculateOptimalBatchSize(count($taskIds), 'update_status');
            $chunks = array_chunk($taskIds, $batchSize);

            $updatedCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($chunks as $chunkIndex => $chunk) {
                try {
                    $this->entityManager->beginTransaction();

                    // 使用原生SQL提高性能
                    $sql = "UPDATE async_tasks SET status = :status, updated_at = :updated_at";
                    $params = [
                        'status' => $status,
                        'updated_at' => new \DateTime()
                    ];

                    // 添加额外数据
                    if (!empty($additionalData)) {
                        foreach ($additionalData as $field => $value) {
                            $sql .= ", {$field} = :{$field}";
                            $params[$field] = $value;
                        }
                    }

                    $sql .= " WHERE id IN (:ids)";
                    $params['ids'] = $chunk;

                    $stmt = $this->entityManager->getConnection()->executeStatement($sql, $params);
                    $updatedCount += $stmt;

                    $this->entityManager->commit();

                } catch (\Exception $e) {
                    $this->entityManager->rollback();
                    $errorCount += count($chunk);
                    $errors[] = [
                        'chunk_index' => $chunkIndex,
                        'chunk_size' => count($chunk),
                        'error' => $e->getMessage()
                    ];

                    $this->logger->error('批量状态更新块失败', [
                        'chunk_index' => $chunkIndex,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;

            $result = [
                'updated_count' => $updatedCount,
                'error_count' => $errorCount,
                'errors' => $errors,
                'execution_time' => $executionTime,
                'success_rate' => count($taskIds) > 0 ? ($updatedCount / count($taskIds)) * 100 : 0
            ];

            $this->recordBatchStats('update_status', count($taskIds), $executionTime, $result);

            $this->logger->info('批量状态更新完成', $result);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('批量状态更新失败', [
                'task_ids_count' => count($taskIds),
                'status' => $status,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 批量创建日志
     */
    public function batchCreateLogs(array $logs): array
    {
        $this->logger->info('开始批量创建日志', ['log_count' => count($logs)]);

        try {
            $startTime = microtime(true);
            $batchSize = $this->calculateOptimalBatchSize(count($logs), 'create_logs');
            $chunks = array_chunk($logs, $batchSize);

            $createdCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($chunks as $chunkIndex => $chunk) {
                try {
                    $this->entityManager->beginTransaction();

                    // 准备批量插入数据
                    $insertData = [];
                    foreach ($chunk as $log) {
                        $insertData[] = [
                            'task_id' => $log['task_id'] ?? null,
                            'log_level' => $log['log_level'] ?? 'info',
                            'log_message' => $log['log_message'] ?? '',
                            'log_context' => json_encode($log['context'] ?? []),
                            'created_at' => new \DateTime()
                        ];
                    }

                    // 批量插入
                    if (!empty($insertData)) {
                        $columns = array_keys($insertData[0]);
                        $sql = $this->buildInsertSQL('task_execution_logs', $columns);
                        $params = [];

                        foreach ($insertData as $i => $data) {
                            foreach ($columns as $column) {
                                $params[$column . '_' . $i] = $data[$column];
                            }
                        }

                        $stmt = $this->entityManager->getConnection()->executeStatement($sql, $params);
                        $createdCount += $stmt;
                    }

                    $this->entityManager->commit();

                } catch (\Exception $e) {
                    $this->entityManager->rollback();
                    $errorCount += count($chunk);
                    $errors[] = [
                        'chunk_index' => $chunkIndex,
                        'chunk_size' => count($chunk),
                        'error' => $e->getMessage()
                    ];

                    $this->logger->error('批量日志创建块失败', [
                        'chunk_index' => $chunkIndex,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;

            $result = [
                'created_count' => $createdCount,
                'error_count' => $errorCount,
                'errors' => $errors,
                'execution_time' => $executionTime,
                'success_rate' => count($logs) > 0 ? ($createdCount / count($logs)) * 100 : 0
            ];

            $this->recordBatchStats('create_logs', count($logs), $executionTime, $result);

            $this->logger->info('批量日志创建完成', $result);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('批量日志创建失败', [
                'log_count' => count($logs),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 批量发送通知
     */
    public function batchSendNotifications(array $notifications): array
    {
        $this->logger->info('开始批量发送通知', ['notification_count' => count($notifications)]);

        try {
            $startTime = microtime(true);
            $batchSize = $this->calculateOptimalBatchSize(count($notifications), 'send_notifications');
            $chunks = array_chunk($notifications, $batchSize);

            $sentCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($chunks as $chunkIndex => $chunk) {
                $chunkResult = $this->processNotificationChunk($chunk);

                $sentCount += $chunkResult['sent_count'];
                $errorCount += $chunkResult['error_count'];

                if (!empty($chunkResult['errors'])) {
                    $errors = array_merge($errors, $chunkResult['errors']);
                }

                $this->logger->debug('通知批次处理完成', [
                    'chunk_index' => $chunkIndex,
                    'sent_count' => $chunkResult['sent_count'],
                    'error_count' => $chunkResult['error_count']
                ]);
            }

            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;

            $result = [
                'sent_count' => $sentCount,
                'error_count' => $errorCount,
                'errors' => $errors,
                'execution_time' => $executionTime,
                'success_rate' => count($notifications) > 0 ? ($sentCount / count($notifications)) * 100 : 0
            ];

            $this->recordBatchStats('send_notifications', count($notifications), $executionTime, $result);

            $this->logger->info('批量通知发送完成', $result);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('批量通知发送失败', [
                'notification_count' => count($notifications),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 获取批量处理统计
     */
    public function getBatchStats(): array
    {
        return [
            'operations' => $this->batchStats['operations'],
            'total_processed' => array_sum($this->batchStats['processed_counts']),
            'total_execution_time' => array_sum($this->batchStats['execution_times']),
            'average_batch_size' => $this->calculateAverageBatchSize(),
            'success_rate' => $this->calculateOverallSuccessRate(),
            'performance_metrics' => $this->calculatePerformanceMetrics()
        ];
    }

    /**
     * 优化批量处理配置
     */
    public function optimizeBatchConfig(): array
    {
        $this->logger->info('开始优化批量处理配置');

        $stats = $this->getBatchStats();
        $optimizations = [];

        // 分析统计数据并优化配置
        foreach ($this->batchConfig as $operation => $config) {
            $operationStats = $stats['operations'][$operation] ?? [];

            if (!empty($operationStats)) {
                $optimization = $this->optimizeOperationConfig($operation, $config, $operationStats);
                if (!empty($optimization)) {
                    $optimizations[$operation] = $optimization;
                }
            }
        }

        $this->logger->info('批量处理配置优化完成', ['optimizations' => $optimizations]);

        return $optimizations;
    }

    /**
     * 处理单个批次
     */
    private function processChunk(array $chunk, string $operation): array
    {
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        try {
            switch ($operation) {
                case 'persist':
                    $result = $this->batchPersist($chunk);
                    break;
                case 'update':
                    $result = $this->batchUpdate($chunk);
                    break;
                case 'remove':
                    $result = $this->batchRemove($chunk);
                    break;
                default:
                    throw new \InvalidArgumentException("Unsupported operation: {$operation}");
            }

            $successCount = $result['success_count'] ?? 0;
            $errorCount = $result['error_count'] ?? 0;
            $errors = $result['errors'] ?? [];

        } catch (\Exception $e) {
            $errorCount = count($chunk);
            $errors[] = [
                'operation' => $operation,
                'chunk_size' => count($chunk),
                'error' => $e->getMessage()
            ];
        }

        return [
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $errors,
            'chunk_size' => count($chunk)
        ];
    }

    /**
     * 批量持久化
     */
    private function batchPersist(array $entities): array
    {
        $successCount = 0;
        $errors = [];

        try {
            $this->entityManager->beginTransaction();

            foreach ($entities as $entity) {
                try {
                    $this->entityManager->persist($entity);
                    $successCount++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'entity_id' => $entity->getId() ?? null,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        return [
            'success_count' => $successCount,
            'error_count' => count($errors),
            'errors' => $errors
        ];
    }

    /**
     * 批量更新
     */
    private function batchUpdate(array $entities): array
    {
        $successCount = 0;
        $errors = [];

        try {
            $this->entityManager->beginTransaction();

            foreach ($entities as $entity) {
                try {
                    $this->entityManager->merge($entity);
                    $successCount++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'entity_id' => $entity->getId() ?? null,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        return [
            'success_count' => $successCount,
            'error_count' => count($errors),
            'errors' => $errors
        ];
    }

    /**
     * 批量删除
     */
    private function batchRemove(array $entities): array
    {
        $successCount = 0;
        $errors = [];

        try {
            $this->entityManager->beginTransaction();

            foreach ($entities as $entity) {
                try {
                    $this->entityManager->remove($entity);
                    $successCount++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'entity_id' => $entity->getId() ?? null,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        return [
            'success_count' => $successCount,
            'error_count' => count($errors),
            'errors' => $errors
        ];
    }

    /**
     * 处理通知批次
     */
    private function processNotificationChunk(array $notifications): array
    {
        $sentCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($notifications as $notification) {
            try {
                // 这里可以实现具体的通知发送逻辑
                // 例如发送邮件、WebSocket消息、Webhook等
                $this->sendNotification($notification);
                $sentCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = [
                    'notification_id' => $notification['id'] ?? null,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'sent_count' => $sentCount,
            'error_count' => $errorCount,
            'errors' => $errors
        ];
    }

    /**
     * 发送单个通知
     */
    private function sendNotification(array $notification): void
    {
        // 模拟通知发送
        // 实际实现中可以调用邮件服务、WebSocket服务等

        $this->logger->debug('发送通知', [
            'type' => $notification['type'] ?? 'unknown',
            'recipient' => $notification['recipient'] ?? 'unknown'
        ]);
    }

    /**
     * 计算最优批量大小
     */
    private function calculateOptimalBatchSize(int $totalItems, string $operation): int
    {
        $config = $this->batchConfig[$operation] ?? $this->batchConfig['default'];

        // 基础批量大小
        $baseBatchSize = $config['base_batch_size'];
        $maxBatchSize = $config['max_batch_size'];
        $minBatchSize = $config['min_batch_size'];

        // 根据总数量调整
        if ($totalItems < $minBatchSize) {
            return $totalItems;
        }

        // 根据历史性能调整
        $historyStats = $this->batchStats['operations'][$operation] ?? [];
        if (!empty($historyStats)) {
            $avgExecutionTime = array_sum($historyStats['execution_times'] ?? []) / count($historyStats['execution_times'] ?? [1]);
            $avgSuccessRate = array_sum($historyStats['success_rates'] ?? []) / count($historyStats['success_rates'] ?? [100]);

            // 如果执行时间过长，减小批量大小
            if ($avgExecutionTime > 5.0) { // 5秒
                $baseBatchSize = max($minBatchSize, intval($baseBatchSize * 0.8));
            }

            // 如果成功率低，减小批量大小
            if ($avgSuccessRate < 90) { // 90%
                $baseBatchSize = max($minBatchSize, intval($baseBatchSize * 0.9));
            }
        }

        return min($maxBatchSize, max($minBatchSize, $baseBatchSize));
    }

    /**
     * 汇总批量处理结果
     */
    private function summarizeBatchResults(array $results, int $totalTasks, float $totalTime): array
    {
        $totalSuccessCount = array_sum(array_column($results, 'success_count'));
        $totalErrorCount = array_sum(array_column($results, 'error_count'));
        $allErrors = [];

        foreach ($results as $result) {
            if (!empty($result['errors'])) {
                $allErrors = array_merge($allErrors, $result['errors']);
            }
        }

        return [
            'total_tasks' => $totalTasks,
            'total_success' => $totalSuccessCount,
            'total_errors' => $totalErrorCount,
            'success_rate' => $totalTasks > 0 ? ($totalSuccessCount / $totalTasks) * 100 : 0,
            'total_execution_time' => $totalTime,
            'average_task_time' => $totalTasks > 0 ? $totalTime / $totalTasks : 0,
            'throughput' => $totalTime > 0 ? $totalTasks / $totalTime : 0,
            'errors' => array_slice($allErrors, 0, 50), // 限制错误数量
            'chunk_count' => count($results)
        ];
    }

    /**
     * 构建批量插入SQL
     */
    private function buildInsertSQL(string $table, array $columns): string
    {
        $columnList = implode(', ', $columns);
        $placeholders = [];

        // 假设每个批次最多100个记录
        for ($i = 0; $i < 100; $i++) {
            $rowPlaceholders = [];
            foreach ($columns as $column) {
                $rowPlaceholders[] = ':' . $column . '_' . $i;
            }
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        $placeholderList = implode(', ', $placeholders);

        return "INSERT INTO {$table} ({$columnList}) VALUES {$placeholderList}";
    }

    /**
     * 内存优化
     */
    private function optimizeMemory(): void
    {
        // 清理实体管理器
        $this->entityManager->clear();

        // 垃圾回收
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /**
     * 记录批量处理统计
     */
    private function recordBatchStats(string $operation, int $itemCount, float $executionTime, array $result): void
    {
        if (!isset($this->batchStats['operations'][$operation])) {
            $this->batchStats['operations'][$operation] = [
                'processed_counts' => [],
                'execution_times' => [],
                'success_rates' => []
            ];
        }

        $this->batchStats['operations'][$operation]['processed_counts'][] = $itemCount;
        $this->batchStats['operations'][$operation]['execution_times'][] = $executionTime;
        $this->batchStats['operations'][$operation]['success_rates'][] = $result['success_rate'] ?? 0;

        $this->batchStats['processed_counts'][] = $itemCount;
        $this->batchStats['execution_times'][] = $executionTime;

        // 限制统计数组大小
        if (count($this->batchStats['processed_counts']) > 1000) {
            $this->batchStats['processed_counts'] = array_slice($this->batchStats['processed_counts'], -500);
            $this->batchStats['execution_times'] = array_slice($this->batchStats['execution_times'], -500);
        }
    }

    /**
     * 计算平均批量大小
     */
    private function calculateAverageBatchSize(): float
    {
        $counts = $this->batchStats['processed_counts'];
        return empty($counts) ? 0 : array_sum($counts) / count($counts);
    }

    /**
     * 计算总体成功率
     */
    private function calculateOverallSuccessRate(): float
    {
        $allSuccessRates = [];

        foreach ($this->batchStats['operations'] as $operationStats) {
            $allSuccessRates = array_merge($allSuccessRates, $operationStats['success_rates'] ?? []);
        }

        return empty($allSuccessRates) ? 0 : array_sum($allSuccessRates) / count($allSuccessRates);
    }

    /**
     * 计算性能指标
     */
    private function calculatePerformanceMetrics(): array
    {
        $executionTimes = $this->batchStats['execution_times'];

        if (empty($executionTimes)) {
            return [
                'avg_execution_time' => 0,
                'min_execution_time' => 0,
                'max_execution_time' => 0,
                'total_processed' => 0
            ];
        }

        return [
            'avg_execution_time' => array_sum($executionTimes) / count($executionTimes),
            'min_execution_time' => min($executionTimes),
            'max_execution_time' => max($executionTimes),
            'total_processed' => array_sum($this->batchStats['processed_counts'])
        ];
    }

    /**
     * 优化操作配置
     */
    private function optimizeOperationConfig(string $operation, array $config, array $stats): array
    {
        $optimizations = [];

        $avgExecutionTime = array_sum($stats['execution_times'] ?? []) / count($stats['execution_times'] ?? [1]);
        $avgSuccessRate = array_sum($stats['success_rates'] ?? []) / count($stats['success_rates'] ?? [100]);

        // 执行时间优化
        if ($avgExecutionTime > 3.0) {
            $newBatchSize = max($config['min_batch_size'], intval($config['base_batch_size'] * 0.8));
            $optimizations['batch_size'] = [
                'old' => $config['base_batch_size'],
                'new' => $newBatchSize,
                'reason' => '执行时间过长，减小批量大小'
            ];
        }

        // 成功率优化
        if ($avgSuccessRate < 95) {
            $newBatchSize = max($config['min_batch_size'], intval($config['base_batch_size'] * 0.9));
            $optimizations['batch_size'] = [
                'old' => $config['base_batch_size'],
                'new' => $newBatchSize,
                'reason' => '成功率较低，减小批量大小以提高稳定性'
            ];
        }

        return $optimizations;
    }

    /**
     * 初始化批量处理配置
     */
    private function initializeBatchConfig(): void
    {
        $this->batchConfig = [
            'default' => [
                'base_batch_size' => 100,
                'min_batch_size' => 10,
                'max_batch_size' => 1000,
                'timeout' => 30,
                'retry_attempts' => 3
            ],
            'persist' => [
                'base_batch_size' => 50,
                'min_batch_size' => 5,
                'max_batch_size' => 500,
                'timeout' => 60,
                'retry_attempts' => 3
            ],
            'update' => [
                'base_batch_size' => 200,
                'min_batch_size' => 20,
                'max_batch_size' => 2000,
                'timeout' => 30,
                'retry_attempts' => 2
            ],
            'remove' => [
                'base_batch_size' => 100,
                'min_batch_size' => 10,
                'max_batch_size' => 1000,
                'timeout' => 30,
                'retry_attempts' => 2
            ],
            'update_status' => [
                'base_batch_size' => 500,
                'min_batch_size' => 50,
                'max_batch_size' => 5000,
                'timeout' => 20,
                'retry_attempts' => 1
            ],
            'create_logs' => [
                'base_batch_size' => 1000,
                'min_batch_size' => 100,
                'max_batch_size' => 10000,
                'timeout' => 30,
                'retry_attempts' => 1
            ],
            'send_notifications' => [
                'base_batch_size' => 20,
                'min_batch_size' => 5,
                'max_batch_size' => 100,
                'timeout' => 60,
                'retry_attempts' => 3
            ]
        ];
    }

    /**
     * 初始化批量处理统计
     */
    private function initializeBatchStats(): void
    {
        $this->batchStats = [
            'operations' => [],
            'processed_counts' => [],
            'execution_times' => [],
            'last_reset' => time()
        ];
    }
}
