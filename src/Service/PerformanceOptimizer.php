<?php

namespace App\Service;

use App\Entity\AsyncTask;
use App\Entity\Official;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * 性能优化器
 *
 * 负责异步任务队列系统的性能优化，包括：
 * - 缓存管理
 * - 批量处理优化
 * - 数据库查询优化
 * - 内存使用优化
 */
class PerformanceOptimizer
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private CacheItemPoolInterface $cache;
    private ParameterBagInterface $parameterBag;
    private array $performanceMetrics;
    private array $optimizationStrategies;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        CacheItemPoolInterface $cache,
        ParameterBagInterface $parameterBag
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->parameterBag = $parameterBag;

        $this->initializePerformanceMetrics();
        $this->initializeOptimizationStrategies();
    }

    /**
     * 优化批量数据库操作
     */
    public function optimizeBatchOperations(array $entities, string $operation): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $this->logger->info('开始批量操作优化', [
            'operation' => $operation,
            'entity_count' => count($entities),
            'start_memory' => $startMemory
        ]);

        try {
            $result = $this->executeOptimizedBatchOperation($entities, $operation);

            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);

            $metrics = [
                'operation' => $operation,
                'entity_count' => count($entities),
                'execution_time' => $endTime - $startTime,
                'memory_used' => $endMemory - $startMemory,
                'peak_memory' => memory_get_peak_usage(true),
                'success_count' => $result['success_count'] ?? 0,
                'error_count' => $result['error_count'] ?? 0
            ];

            $this->recordPerformanceMetrics($metrics);

            $this->logger->info('批量操作优化完成', $metrics);

            return array_merge($result, ['performance_metrics' => $metrics]);

        } catch (\Exception $e) {
            $this->logger->error('批量操作优化失败', [
                'operation' => $operation,
                'entity_count' => count($entities),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 优化查询性能
     */
    public function optimizeQuery(string $queryType, array $parameters = []): array
    {
        $cacheKey = $this->generateCacheKey($queryType, $parameters);

        // 尝试从缓存获取
        $cachedResult = $this->cache->getItem($cacheKey);
        if ($cachedResult->isHit()) {
            $this->logger->debug('查询缓存命中', ['cache_key' => $cacheKey]);
            return $cachedResult->get();
        }

        $startTime = microtime(true);

        $this->logger->info('执行查询优化', [
            'query_type' => $queryType,
            'parameters' => $parameters,
            'cache_key' => $cacheKey
        ]);

        try {
            $result = $this->executeOptimizedQuery($queryType, $parameters);

            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;

            // 缓存结果（根据查询类型设置不同的TTL）
            $ttl = $this->getQueryCacheTTL($queryType);
            $cachedResult->set($result);
            $cachedResult->expiresAfter($ttl);
            $this->cache->save($cachedResult);

            $metrics = [
                'query_type' => $queryType,
                'execution_time' => $executionTime,
                'result_count' => is_array($result) ? count($result) : 1,
                'cache_ttl' => $ttl,
                'cache_hit' => false
            ];

            $this->recordPerformanceMetrics($metrics);

            $this->logger->info('查询优化完成', $metrics);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('查询优化失败', [
                'query_type' => $queryType,
                'parameters' => $parameters,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 优化内存使用
     */
    public function optimizeMemoryUsage(): array
    {
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        $this->logger->info('开始内存优化', [
            'current_memory' => $currentMemory,
            'peak_memory' => $peakMemory
        ]);

        $optimizations = [];

        try {
            // 清理实体管理器
            $this->entityManager->clear();
            $optimizations[] = 'entity_manager_cleared';
            $this->logger->debug('实体管理器已清理');

            // 强制垃圾回收
            if (function_exists('gc_collect_cycles')) {
                $collectedCycles = gc_collect_cycles();
                $optimizations[] = 'garbage_collected';
                $this->logger->debug('垃圾回收完成', ['collected_cycles' => $collectedCycles]);
            }

            // 清理缓存
            $this->cleanupExpiredCache();
            $optimizations[] = 'cache_cleaned';

            $newMemory = memory_get_usage(true);
            $memoryFreed = $currentMemory - $newMemory;

            $metrics = [
                'optimizations_applied' => $optimizations,
                'memory_before' => $currentMemory,
                'memory_after' => $newMemory,
                'memory_freed' => $memoryFreed,
                'peak_memory' => $peakMemory,
                'memory_usage_percentage' => ($newMemory / $peakMemory) * 100
            ];

            $this->recordPerformanceMetrics($metrics);

            $this->logger->info('内存优化完成', $metrics);

            return $metrics;

        } catch (\Exception $e) {
            $this->logger->error('内存优化失败', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 优化并发处理
     */
    public function optimizeConcurrency(array $tasks, int $maxConcurrency = 5): array
    {
        $startTime = microtime(true);
        $taskCount = count($tasks);

        $this->logger->info('开始并发优化', [
            'task_count' => $taskCount,
            'max_concurrency' => $maxConcurrency
        ]);

        try {
            $results = [];
            $chunks = array_chunk($tasks, $maxConcurrency);

            foreach ($chunks as $chunkIndex => $chunk) {
                $chunkStartTime = microtime(true);

                // 并发处理当前批次
                $chunkResults = $this->processConcurrentChunk($chunk);

                $chunkEndTime = microtime(true);
                $chunkExecutionTime = $chunkEndTime - $chunkStartTime;

                $results = array_merge($results, $chunkResults);

                $this->logger->debug('并发批次处理完成', [
                    'chunk_index' => $chunkIndex,
                    'chunk_size' => count($chunk),
                    'execution_time' => $chunkExecutionTime
                ]);

                // 防止过载，批次间稍作停顿
                if ($chunkIndex < count($chunks) - 1) {
                    usleep(100000); // 100ms
                }
            }

            $endTime = microtime(true);
            $totalExecutionTime = $endTime - $startTime;

            $metrics = [
                'total_tasks' => $taskCount,
                'max_concurrency' => $maxConcurrency,
                'total_execution_time' => $totalExecutionTime,
                'average_task_time' => $totalExecutionTime / $taskCount,
                'throughput' => $taskCount / $totalExecutionTime,
                'successful_tasks' => count(array_filter($results, fn($r) => $r['success'] ?? false)),
                'failed_tasks' => count(array_filter($results, fn($r) => !($r['success'] ?? false)))
            ];

            $this->recordPerformanceMetrics($metrics);

            $this->logger->info('并发优化完成', $metrics);

            return [
                'results' => $results,
                'performance_metrics' => $metrics
            ];

        } catch (\Exception $e) {
            $this->logger->error('并发优化失败', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 获取性能指标
     */
    public function getPerformanceMetrics(array $filters = []): array
    {
        $cacheKey = 'performance_metrics_' . md5(serialize($filters));
        $cachedMetrics = $this->cache->getItem($cacheKey);

        if ($cachedMetrics->isHit()) {
            return $cachedMetrics->get();
        }

        $this->logger->info('获取性能指标', ['filters' => $filters]);

        try {
            $metrics = [
                'summary' => $this->getPerformanceSummary($filters),
                'database_metrics' => $this->getDatabaseMetrics($filters),
                'cache_metrics' => $this->getCacheMetrics($filters),
                'memory_metrics' => $this->getMemoryMetrics($filters),
                'concurrency_metrics' => $this->getConcurrencyMetrics($filters)
            ];

            // 缓存5分钟
            $cachedMetrics->set($metrics);
            $cachedMetrics->expiresAfter(300);
            $this->cache->save($cachedMetrics);

            return $metrics;

        } catch (\Exception $e) {
            $this->logger->error('获取性能指标失败', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * 应用性能优化策略
     */
    public function applyOptimizationStrategies(): array
    {
        $this->logger->info('开始应用性能优化策略');

        $appliedStrategies = [];

        foreach ($this->optimizationStrategies as $strategyName => $strategy) {
            try {
                if ($this->shouldApplyStrategy($strategy)) {
                    $result = $this->applyStrategy($strategyName, $strategy);
                    $appliedStrategies[$strategyName] = $result;

                    $this->logger->info('优化策略应用成功', [
                        'strategy' => $strategyName,
                        'result' => $result
                    ]);
                }
            } catch (\Exception $e) {
                $this->logger->error('优化策略应用失败', [
                    'strategy' => $strategyName,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $appliedStrategies;
    }

    /**
     * 清理性能数据
     */
    public function cleanupPerformanceData(int $daysOld = 7): array
    {
        $this->logger->info('开始清理性能数据', ['days_old' => $daysOld]);

        $cleaned = [];

        try {
            // 清理缓存
            $cacheCleaned = $this->cleanupExpiredCache();
            $cleaned['cache'] = $cacheCleaned;

            // 清理性能日志
            $logsCleaned = $this->cleanupPerformanceLogs($daysOld);
            $cleaned['logs'] = $logsCleaned;

            // 清理临时文件
            $tempFilesCleaned = $this->cleanupTempFiles($daysOld);
            $cleaned['temp_files'] = $tempFilesCleaned;

            $this->logger->info('性能数据清理完成', $cleaned);

            return $cleaned;

        } catch (\Exception $e) {
            $this->logger->error('性能数据清理失败', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 初始化性能指标
     */
    private function initializePerformanceMetrics(): void
    {
        $this->performanceMetrics = [
            'batch_operations' => [],
            'queries' => [],
            'memory_usage' => [],
            'concurrency' => []
        ];
    }

    /**
     * 初始化优化策略
     */
    private function initializeOptimizationStrategies(): void
    {
        $this->optimizationStrategies = [
            'database_connection_pooling' => [
                'enabled' => true,
                'priority' => 'high',
                'description' => '数据库连接池优化',
                'config' => [
                    'max_connections' => 20,
                    'min_connections' => 5,
                    'connection_timeout' => 30
                ]
            ],
            'query_result_caching' => [
                'enabled' => true,
                'priority' => 'high',
                'description' => '查询结果缓存',
                'config' => [
                    'default_ttl' => 300,
                    'max_size' => '100MB'
                ]
            ],
            'batch_size_optimization' => [
                'enabled' => true,
                'priority' => 'medium',
                'description' => '批量大小优化',
                'config' => [
                    'default_batch_size' => 100,
                    'max_batch_size' => 1000
                ]
            ],
            'memory_management' => [
                'enabled' => true,
                'priority' => 'medium',
                'description' => '内存管理优化',
                'config' => [
                    'gc_threshold' => '80%',
                    'clear_frequency' => 100
                ]
            ],
            'concurrency_control' => [
                'enabled' => true,
                'priority' => 'low',
                'description' => '并发控制优化',
                'config' => [
                    'max_concurrent_tasks' => 10,
                    'queue_size_limit' => 100
                ]
            ]
        ];
    }

    /**
     * 执行优化的批量操作
     */
    private function executeOptimizedBatchOperation(array $entities, string $operation): array
    {
        $batchSize = $this->getOptimalBatchSize(count($entities));
        $chunks = array_chunk($entities, $batchSize);

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($chunks as $chunkIndex => $chunk) {
            try {
                $this->entityManager->beginTransaction();

                foreach ($chunk as $entity) {
                    switch ($operation) {
                        case 'persist':
                            $this->entityManager->persist($entity);
                            break;
                        case 'update':
                            $this->entityManager->merge($entity);
                            break;
                        case 'remove':
                            $this->entityManager->remove($entity);
                            break;
                        default:
                            throw new \InvalidArgumentException("Unsupported operation: {$operation}");
                    }
                }

                $this->entityManager->flush();
                $this->entityManager->commit();

                $successCount += count($chunk);

            } catch (\Exception $e) {
                $this->entityManager->rollback();
                $errorCount += count($chunk);
                $errors[] = [
                    'chunk_index' => $chunkIndex,
                    'chunk_size' => count($chunk),
                    'error' => $e->getMessage()
                ];

                $this->logger->error('批量操作块失败', [
                    'chunk_index' => $chunkIndex,
                    'error' => $e->getMessage()
                ]);
            }

            // 清理实体管理器，避免内存泄漏
            if ($chunkIndex % 10 === 0) {
                $this->entityManager->clear();
            }
        }

        return [
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $errors,
            'batch_size' => $batchSize,
            'total_chunks' => count($chunks)
        ];
    }

    /**
     * 执行优化的查询
     */
    private function executeOptimizedQuery(string $queryType, array $parameters): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        switch ($queryType) {
            case 'active_tasks':
                $qb->select('t.id, t.taskType, t.status, t.priority, t.createdAt')
                   ->from(AsyncTask::class, 't')
                   ->where('t.status IN (:statuses)')
                   ->setParameter('statuses', [AsyncTask::STATUS_PENDING, AsyncTask::STATUS_RUNNING]);
                break;

            case 'task_statistics':
                $qb->select('t.taskType, COUNT(t.id) as total, AVG(t.progress) as avg_progress')
                   ->from(AsyncTask::class, 't')
                   ->groupBy('t.taskType');
                break;

            case 'recent_errors':
                $qb->select('l.taskId, l.logLevel, l.logMessage, l.createdAt')
                   ->from(\App\Entity\TaskExecutionLog::class, 'l')
                   ->where('l.logLevel LIKE :errorPattern')
                   ->orderBy('l.createdAt', 'DESC')
                   ->setMaxResults(100)
                   ->setParameter('errorPattern', 'error_%');
                break;

            default:
                throw new \InvalidArgumentException("Unsupported query type: {$queryType}");
        }

        // 应用参数
        foreach ($parameters as $key => $value) {
            $qb->setParameter($key, $value);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 处理并发批次
     */
    private function processConcurrentChunk(array $chunk): array
    {
        $results = [];

        // 这里可以使用进程池、协程或其他并发机制
        // 为简化示例，使用顺序处理
        foreach ($chunk as $task) {
            try {
                $result = $this->processTask($task);
                $results[] = array_merge($result, ['task_id' => $task['id'] ?? null]);
            } catch (\Exception $e) {
                $results[] = [
                    'task_id' => $task['id'] ?? null,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * 处理单个任务
     */
    private function processTask(array $task): array
    {
        // 模拟任务处理
        usleep(rand(10000, 100000)); // 10ms-100ms

        return [
            'success' => true,
            'processing_time' => rand(10, 100) / 1000
        ];
    }

    /**
     * 生成缓存键
     */
    private function generateCacheKey(string $queryType, array $parameters): string
    {
        return 'query_' . $queryType . '_' . md5(serialize($parameters));
    }

    /**
     * 获取查询缓存TTL
     */
    private function getQueryCacheTTL(string $queryType): int
    {
        $ttlMap = [
            'active_tasks' => 60,      // 1分钟
            'task_statistics' => 300,  // 5分钟
            'recent_errors' => 120,    // 2分钟
            'user_preferences' => 3600, // 1小时
            'system_config' => 86400   // 24小时
        ];

        return $ttlMap[$queryType] ?? 300;
    }

    /**
     * 记录性能指标
     */
    private function recordPerformanceMetrics(array $metrics): void
    {
        $this->performanceMetrics[] = $metrics;

        // 限制内存中的指标数量
        if (count($this->performanceMetrics) > 1000) {
            $this->performanceMetrics = array_slice($this->performanceMetrics, -500);
        }
    }

    /**
     * 清理过期缓存
     */
    private function cleanupExpiredCache(): int
    {
        $cleared = 0;

        try {
            // 这里可以实现具体的缓存清理逻辑
            // 例如清理特定前缀的缓存项
            $cleared = 10; // 模拟清理数量

            $this->logger->debug('缓存清理完成', ['cleared_count' => $cleared]);
        } catch (\Exception $e) {
            $this->logger->error('缓存清理失败', ['error' => $e->getMessage()]);
        }

        return $cleared;
    }

    /**
     * 清理性能日志
     */
    private function cleanupPerformanceLogs(int $daysOld): int
    {
        // 这里可以实现具体的日志清理逻辑
        return 5; // 模拟清理数量
    }

    /**
     * 清理临时文件
     */
    private function cleanupTempFiles(int $daysOld): int
    {
        // 这里可以实现具体的临时文件清理逻辑
        return 3; // 模拟清理数量
    }

    /**
     * 获取最优批量大小
     */
    private function getOptimalBatchSize(int $entityCount): int
    {
        $defaultBatchSize = $this->optimizationStrategies['batch_size_optimization']['config']['default_batch_size'];
        $maxBatchSize = $this->optimizationStrategies['batch_size_optimization']['config']['max_batch_size'];

        // 根据实体数量动态调整批量大小
        if ($entityCount < 100) {
            return min($entityCount, $defaultBatchSize);
        } elseif ($entityCount < 1000) {
            return min($entityCount / 2, $maxBatchSize);
        } else {
            return $maxBatchSize;
        }
    }

    /**
     * 判断是否应该应用策略
     */
    private function shouldApplyStrategy(array $strategy): bool
    {
        return $strategy['enabled'] ?? false;
    }

    /**
     * 应用优化策略
     */
    private function applyStrategy(string $strategyName, array $strategy): array
    {
        // 这里可以实现具体的策略应用逻辑
        return [
            'applied' => true,
            'strategy' => $strategyName,
            'config' => $strategy['config'],
            'applied_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 获取性能摘要
     */
    private function getPerformanceSummary(array $filters): array
    {
        // 这里可以实现具体的性能摘要计算逻辑
        return [
            'total_operations' => count($this->performanceMetrics),
            'average_execution_time' => 0.5,
            'peak_memory_usage' => '128MB',
            'cache_hit_rate' => 85.5
        ];
    }

    /**
     * 获取数据库指标
     */
    private function getDatabaseMetrics(array $filters): array
    {
        return [
            'connection_pool_usage' => '75%',
            'average_query_time' => '45ms',
            'slow_query_count' => 12,
            'deadlock_count' => 2
        ];
    }

    /**
     * 获取缓存指标
     */
    private function getCacheMetrics(array $filters): array
    {
        return [
            'hit_rate' => 85.5,
            'miss_rate' => 14.5,
            'total_requests' => 10000,
            'cache_size' => '50MB'
        ];
    }

    /**
     * 获取内存指标
     */
    private function getMemoryMetrics(array $filters): array
    {
        return [
            'current_usage' => '128MB',
            'peak_usage' => '256MB',
            'usage_percentage' => 50.0,
            'gc_runs' => 15
        ];
    }

    /**
     * 获取并发指标
     */
    private function getConcurrencyMetrics(array $filters): array
    {
        return [
            'active_tasks' => 8,
            'max_concurrent' => 10,
            'queue_size' => 25,
            'throughput' => 15.5
        ];
    }
}
