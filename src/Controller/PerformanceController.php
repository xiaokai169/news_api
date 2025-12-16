<?php

namespace App\Controller;

use App\Service\PerformanceOptimizer;
use App\Service\CacheManager;
use App\Service\BatchProcessor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

/**
 * 性能优化控制器
 *
 * 提供性能优化相关的API接口，包括：
 * - 性能指标查询
 * - 缓存管理
 * - 批量处理优化
 * - 系统性能调优
 */
#[Route('/api/performance')]
class PerformanceController extends AbstractController
{
    private PerformanceOptimizer $performanceOptimizer;
    private CacheManager $cacheManager;
    private BatchProcessor $batchProcessor;
    private LoggerInterface $logger;

    public function __construct(
        PerformanceOptimizer $performanceOptimizer,
        CacheManager $cacheManager,
        BatchProcessor $batchProcessor,
        LoggerInterface $logger
    ) {
        $this->performanceOptimizer = $performanceOptimizer;
        $this->cacheManager = $cacheManager;
        $this->batchProcessor = $batchProcessor;
        $this->logger = $logger;
    }

    /**
     * 获取性能指标概览
     */
    #[Route('/metrics', name: 'performance_metrics', methods: ['GET'])]
    public function getMetrics(Request $request): JsonResponse
    {
        try {
            $filters = $this->extractFilters($request);
            $metrics = $this->performanceOptimizer->getPerformanceMetrics($filters);

            $this->logger->info('性能指标查询成功', ['filters' => $filters]);

            return $this->json([
                'success' => true,
                'data' => $metrics,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('性能指标查询失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve performance metrics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取缓存统计信息
     */
    #[Route('/cache/stats', name: 'performance_cache_stats', methods: ['GET'])]
    public function getCacheStats(): JsonResponse
    {
        try {
            $stats = $this->cacheManager->getStats();
            $healthStatus = $this->cacheManager->getHealthStatus();

            $this->logger->info('缓存统计查询成功');

            return $this->json([
                'success' => true,
                'data' => [
                    'statistics' => $stats,
                    'health' => $healthStatus
                ],
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('缓存统计查询失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve cache statistics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 清理缓存
     */
    #[Route('/cache/clear', name: 'performance_cache_clear', methods: ['POST'])]
    public function clearCache(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $tags = $data['tags'] ?? null;

            if ($tags) {
                $success = $this->cacheManager->invalidateTags($tags);
                $operation = 'invalidate_tags';
            } else {
                $success = $this->cacheManager->clear();
                $operation = 'clear_all';
            }

            $this->logger->info('缓存清理操作', [
                'operation' => $operation,
                'tags' => $tags,
                'success' => $success
            ]);

            return $this->json([
                'success' => true,
                'data' => [
                    'operation' => $operation,
                    'success' => $success,
                    'tags' => $tags
                ],
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('缓存清理失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to clear cache',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 预热缓存
     */
    #[Route('/cache/warmup', name: 'performance_cache_warmup', methods: ['POST'])]
    public function warmupCache(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $warmupConfig = $data['config'] ?? [];

            $results = $this->cacheManager->warmup($warmupConfig);

            $this->logger->info('缓存预热完成', ['results' => $results]);

            return $this->json([
                'success' => true,
                'data' => [
                    'results' => $results,
                    'summary' => $this->summarizeWarmupResults($results)
                ],
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('缓存预热失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to warmup cache',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取批量处理统计
     */
    #[Route('/batch/stats', name: 'performance_batch_stats', methods: ['GET'])]
    public function getBatchStats(): JsonResponse
    {
        try {
            $stats = $this->batchProcessor->getBatchStats();

            $this->logger->info('批量处理统计查询成功');

            return $this->json([
                'success' => true,
                'data' => $stats,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('批量处理统计查询失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve batch statistics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 优化批量处理配置
     */
    #[Route('/batch/optimize', name: 'performance_batch_optimize', methods: ['POST'])]
    public function optimizeBatchConfig(): JsonResponse
    {
        try {
            $optimizations = $this->batchProcessor->optimizeBatchConfig();

            $this->logger->info('批量处理配置优化完成', ['optimizations' => $optimizations]);

            return $this->json([
                'success' => true,
                'data' => [
                    'optimizations' => $optimizations,
                    'applied_count' => count($optimizations)
                ],
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('批量处理配置优化失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to optimize batch configuration',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 应用性能优化策略
     */
    #[Route('/optimize', name: 'performance_optimize', methods: ['POST'])]
    public function applyOptimizations(): JsonResponse
    {
        try {
            $appliedStrategies = $this->performanceOptimizer->applyOptimizationStrategies();

            $this->logger->info('性能优化策略应用完成', ['strategies' => array_keys($appliedStrategies)]);

            return $this->json([
                'success' => true,
                'data' => [
                    'applied_strategies' => $appliedStrategies,
                    'applied_count' => count($appliedStrategies)
                ],
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('性能优化策略应用失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to apply optimization strategies',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 优化内存使用
     */
    #[Route('/memory/optimize', name: 'performance_memory_optimize', methods: ['POST'])]
    public function optimizeMemory(): JsonResponse
    {
        try {
            $metrics = $this->performanceOptimizer->optimizeMemoryUsage();

            $this->logger->info('内存优化完成', $metrics);

            return $this->json([
                'success' => true,
                'data' => $metrics,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('内存优化失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to optimize memory usage',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 清理性能数据
     */
    #[Route('/cleanup', name: 'performance_cleanup', methods: ['POST'])]
    public function cleanupPerformanceData(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $daysOld = $data['days_old'] ?? 7;

            $cleaned = $this->performanceOptimizer->cleanupPerformanceData($daysOld);

            $this->logger->info('性能数据清理完成', ['cleaned' => $cleaned]);

            return $this->json([
                'success' => true,
                'data' => [
                    'cleaned_items' => $cleaned,
                    'days_old' => $daysOld
                ],
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('性能数据清理失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to cleanup performance data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取系统性能报告
     */
    #[Route('/report', name: 'performance_report', methods: ['GET'])]
    public function getPerformanceReport(Request $request): JsonResponse
    {
        try {
            $period = $request->query->get('period', '24h'); // 1h, 6h, 24h, 7d, 30d

            $report = $this->generatePerformanceReport($period);

            $this->logger->info('性能报告生成成功', ['period' => $period]);

            return $this->json([
                'success' => true,
                'data' => $report,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('性能报告生成失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to generate performance report',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取性能建议
     */
    #[Route('/recommendations', name: 'performance_recommendations', methods: ['GET'])]
    public function getPerformanceRecommendations(): JsonResponse
    {
        try {
            $recommendations = $this->generatePerformanceRecommendations();

            $this->logger->info('性能建议生成成功', ['recommendations_count' => count($recommendations)]);

            return $this->json([
                'success' => true,
                'data' => [
                    'recommendations' => $recommendations,
                    'priority_summary' => $this->summarizeRecommendationsByPriority($recommendations)
                ],
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('性能建议生成失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to generate performance recommendations',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 测试性能
     */
    #[Route('/benchmark', name: 'performance_benchmark', methods: ['POST'])]
    public function runBenchmark(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $testType = $data['test_type'] ?? 'basic'; // basic, cache, batch, memory
            $iterations = $data['iterations'] ?? 100;

            $results = $this->runPerformanceTest($testType, $iterations);

            $this->logger->info('性能测试完成', [
                'test_type' => $testType,
                'iterations' => $iterations,
                'results' => $results
            ]);

            return $this->json([
                'success' => true,
                'data' => [
                    'test_type' => $testType,
                    'iterations' => $iterations,
                    'results' => $results
                ],
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('性能测试失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to run performance benchmark',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 提取请求过滤器
     */
    private function extractFilters(Request $request): array
    {
        $filters = [];

        if ($request->query->has('start_time')) {
            $filters['start_time'] = $request->query->get('start_time');
        }

        if ($request->query->has('end_time')) {
            $filters['end_time'] = $request->query->get('end_time');
        }

        if ($request->query->has('operation')) {
            $filters['operation'] = $request->query->get('operation');
        }

        if ($request->query->has('limit')) {
            $filters['limit'] = (int) $request->query->get('limit');
        }

        return $filters;
    }

    /**
     * 汇总预热结果
     */
    private function summarizeWarmupResults(array $results): array
    {
        $summary = [
            'total_items' => count($results),
            'successful_items' => 0,
            'failed_items' => 0,
            'total_size' => 0,
            'errors' => []
        ];

        foreach ($results as $key => $result) {
            if ($result['success'] ?? false) {
                $summary['successful_items']++;
                $summary['total_size'] += $result['value_size'] ?? 0;
            } else {
                $summary['failed_items']++;
                $summary['errors'][] = [
                    'key' => $key,
                    'error' => $result['error'] ?? 'Unknown error'
                ];
            }
        }

        return $summary;
    }

    /**
     * 生成性能报告
     */
    private function generatePerformanceReport(string $period): array
    {
        // 获取各项指标
        $performanceMetrics = $this->performanceOptimizer->getPerformanceMetrics();
        $cacheStats = $this->cacheManager->getStats();
        $batchStats = $this->batchProcessor->getBatchStats();

        return [
            'period' => $period,
            'generated_at' => date('Y-m-d H:i:s'),
            'summary' => [
                'overall_health' => $this->calculateOverallHealth($performanceMetrics, $cacheStats, $batchStats),
                'key_metrics' => $this->extractKeyMetrics($performanceMetrics, $cacheStats, $batchStats)
            ],
            'performance_metrics' => $performanceMetrics,
            'cache_analysis' => [
                'statistics' => $cacheStats,
                'health_status' => $this->cacheManager->getHealthStatus()
            ],
            'batch_analysis' => $batchStats,
            'trends' => $this->analyzeTrends($period),
            'recommendations' => $this->generatePerformanceRecommendations()
        ];
    }

    /**
     * 生成性能建议
     */
    private function generatePerformanceRecommendations(): array
    {
        $recommendations = [];

        // 获取当前性能数据
        $cacheStats = $this->cacheManager->getStats();
        $batchStats = $this->batchProcessor->getBatchStats();

        // 缓存相关建议
        if ($cacheStats['hit_rate'] < 80) {
            $recommendations[] = [
                'type' => 'cache',
                'priority' => 'high',
                'title' => '缓存命中率过低',
                'description' => "当前缓存命中率为 {$cacheStats['hit_rate']}%，建议优化缓存策略或增加缓存时间。",
                'action' => 'review_cache_strategy'
            ];
        }

        if ($cacheStats['error_rate'] > 5) {
            $recommendations[] = [
                'type' => 'cache',
                'priority' => 'critical',
                'title' => '缓存错误率过高',
                'description' => "当前缓存错误率为 {$cacheStats['error_rate']}%，需要检查缓存配置和连接。",
                'action' => 'check_cache_configuration'
            ];
        }

        // 批量处理相关建议
        if ($batchStats['success_rate'] < 95) {
            $recommendations[] = [
                'type' => 'batch',
                'priority' => 'medium',
                'title' => '批量处理成功率偏低',
                'description' => "当前批量处理成功率为 {$batchStats['success_rate']}%，建议调整批量大小或增加重试机制。",
                'action' => 'optimize_batch_configuration'
            ];
        }

        // 通用建议
        $recommendations[] = [
            'type' => 'general',
            'priority' => 'low',
            'title' => '定期性能监控',
            'description' => '建议建立定期性能监控机制，及时发现和解决性能问题。',
            'action' => 'setup_monitoring'
        ];

        return $recommendations;
    }

    /**
     * 按优先级汇总建议
     */
    private function summarizeRecommendationsByPriority(array $recommendations): array
    {
        $summary = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0
        ];

        foreach ($recommendations as $rec) {
            $priority = $rec['priority'] ?? 'low';
            if (isset($summary[$priority])) {
                $summary[$priority]++;
            }
        }

        return $summary;
    }

    /**
     * 计算总体健康状态
     */
    private function calculateOverallHealth(array $performanceMetrics, array $cacheStats, array $batchStats): string
    {
        $healthScore = 100;

        // 缓存命中率影响
        if ($cacheStats['hit_rate'] < 70) {
            $healthScore -= 20;
        } elseif ($cacheStats['hit_rate'] < 85) {
            $healthScore -= 10;
        }

        // 缓存错误率影响
        if ($cacheStats['error_rate'] > 10) {
            $healthScore -= 30;
        } elseif ($cacheStats['error_rate'] > 5) {
            $healthScore -= 15;
        }

        // 批量处理成功率影响
        if ($batchStats['success_rate'] < 90) {
            $healthScore -= 20;
        } elseif ($batchStats['success_rate'] < 95) {
            $healthScore -= 10;
        }

        if ($healthScore >= 90) {
            return 'excellent';
        } elseif ($healthScore >= 75) {
            return 'good';
        } elseif ($healthScore >= 60) {
            return 'fair';
        } else {
            return 'poor';
        }
    }

    /**
     * 提取关键指标
     */
    private function extractKeyMetrics(array $performanceMetrics, array $cacheStats, array $batchStats): array
    {
        return [
            'cache_hit_rate' => $cacheStats['hit_rate'],
            'cache_error_rate' => $cacheStats['error_rate'],
            'batch_success_rate' => $batchStats['success_rate'],
            'average_execution_time' => $performanceMetrics['database_metrics']['average_query_time'] ?? 'N/A',
            'memory_usage' => $performanceMetrics['memory_metrics']['current_usage'] ?? 'N/A'
        ];
    }

    /**
     * 分析趋势
     */
    private function analyzeTrends(string $period): array
    {
        // 这里可以实现具体的趋势分析逻辑
        // 例如比较不同时期的性能数据

        return [
            'period' => $period,
            'cache_hit_rate_trend' => 'stable', // improving, declining, stable
            'performance_trend' => 'stable',
            'error_rate_trend' => 'declining',
            'memory_usage_trend' => 'stable'
        ];
    }

    /**
     * 运行性能测试
     */
    private function runPerformanceTest(string $testType, int $iterations): array
    {
        $startTime = microtime(true);
        $results = [];

        switch ($testType) {
            case 'cache':
                $results = $this->runCacheTest($iterations);
                break;
            case 'batch':
                $results = $this->runBatchTest($iterations);
                break;
            case 'memory':
                $results = $this->runMemoryTest($iterations);
                break;
            default:
                $results = $this->runBasicTest($iterations);
        }

        $endTime = microtime(true);

        return array_merge($results, [
            'test_duration' => $endTime - $startTime,
            'iterations' => $iterations,
            'test_type' => $testType
        ]);
    }

    /**
     * 运行基础性能测试
     */
    private function runBasicTest(int $iterations): array
    {
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);

            // 模拟一些基础操作
            usleep(1000); // 1ms

            $times[] = microtime(true) - $start;
        }

        return [
            'average_time' => array_sum($times) / count($times),
            'min_time' => min($times),
            'max_time' => max($times),
            'operations_per_second' => $iterations / array_sum($times)
        ];
    }

    /**
     * 运行缓存测试
     */
    private function runCacheTest(int $iterations): array
    {
        $times = [];
        $hitCount = 0;

        for ($i = 0; $i < $iterations; $i++) {
            $key = "test_key_$i";
            $value = "test_value_$i";

            // 测试写入
            $start = microtime(true);
            $this->cacheManager->set($key, $value, 60);
            $writeTime = microtime(true) - $start;

            // 测试读取
            $start = microtime(true);
            $retrieved = $this->cacheManager->get($key);
            $readTime = microtime(true) - $start;

            $times[] = $writeTime + $readTime;

            if ($retrieved === $value) {
                $hitCount++;
            }
        }

        return [
            'average_time' => array_sum($times) / count($times),
            'min_time' => min($times),
            'max_time' => max($times),
            'hit_rate' => ($hitCount / $iterations) * 100,
            'operations_per_second' => ($iterations * 2) / array_sum($times)
        ];
    }

    /**
     * 运行批量处理测试
     */
    private function runBatchTest(int $iterations): array
    {
        // 模拟批量处理测试
        $batchSize = 100;
        $batches = ceil($iterations / $batchSize);
        $times = [];

        for ($i = 0; $i < $batches; $i++) {
            $start = microtime(true);

            // 模拟批量操作
            $items = array_fill(0, min($batchSize, $iterations - ($i * $batchSize)), ['test' => true]);

            // 这里可以调用实际的批量处理方法
            // $this->batchProcessor->processBatch($items, 'persist');

            usleep(5000); // 模拟5ms的批量处理时间

            $times[] = microtime(true) - $start;
        }

        return [
            'average_batch_time' => array_sum($times) / count($times),
            'min_batch_time' => min($times),
            'max_batch_time' => max($times),
            'batches_per_second' => count($times) / array_sum($times),
            'items_per_second' => $iterations / array_sum($times)
        ];
    }

    /**
     * 运行内存测试
     */
    private function runMemoryTest(int $iterations): array
    {
        $memoryUsage = [];

        for ($i = 0; $i < $iterations; $i++) {
            $startMemory = memory_get_usage(true);

            // 模拟内存使用
            $data = array_fill(0, 1000, str_repeat('x', 100));

            $endMemory = memory_get_usage(true);
            $memoryUsage[] = $endMemory - $startMemory;

            unset($data);
        }

        return [
            'average_memory_usage' => array_sum($memoryUsage) / count($memoryUsage),
            'min_memory_usage' => min($memoryUsage),
            'max_memory_usage' => max($memoryUsage),
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }
}
