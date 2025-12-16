<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * 缓存管理器
 *
 * 负责异步任务队列系统的缓存管理，包括：
 * - 多层缓存策略
 * - 缓存标签管理
 * - 缓存预热和失效
 * - 缓存性能监控
 */
class CacheManager
{
    private LoggerInterface $logger;
    private CacheItemPoolInterface $cache;
    private TagAwareAdapter $tagAwareCache;
    private ParameterBagInterface $parameterBag;
    private array $cacheConfig;
    private array $cacheStats;

    public function __construct(
        LoggerInterface $logger,
        CacheItemPoolInterface $cache,
        ParameterBagInterface $parameterBag
    ) {
        $this->logger = $logger;
        $this->cache = $cache;
        $this->parameterBag = $parameterBag;
        $this->tagAwareCache = new TagAwareAdapter($cache);

        $this->initializeCacheConfig();
        $this->initializeCacheStats();
    }

    /**
     * 获取缓存项
     */
    public function get(string $key, callable $callback = null, int $ttl = null): mixed
    {
        $startTime = microtime(true);

        try {
            $cacheItem = $this->cache->getItem($key);

            if ($cacheItem->isHit()) {
                $this->recordCacheStats('hit', $key, microtime(true) - $startTime);
                $this->logger->debug('缓存命中', ['key' => $key]);
                return $cacheItem->get();
            }

            if ($callback !== null) {
                $this->logger->debug('缓存未命中，执行回调', ['key' => $key]);

                $value = $callback();
                $this->set($key, $value, $ttl);

                $this->recordCacheStats('miss_callback', $key, microtime(true) - $startTime);
                return $value;
            }

            $this->recordCacheStats('miss', $key, microtime(true) - $startTime);
            return null;

        } catch (\Exception $e) {
            $this->logger->error('缓存获取失败', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);

            $this->recordCacheStats('error', $key, microtime(true) - $startTime);
            return $callback ? $callback() : null;
        }
    }

    /**
     * 设置缓存项
     */
    public function set(string $key, mixed $value, int $ttl = null, array $tags = []): bool
    {
        $startTime = microtime(true);

        try {
            if (!empty($tags)) {
                $cacheItem = $this->tagAwareCache->getItem($key);
            } else {
                $cacheItem = $this->cache->getItem($key);
            }

            $cacheItem->set($value);

            if ($ttl !== null) {
                $cacheItem->expiresAfter($ttl);
            } elseif (isset($this->cacheConfig['default_ttl'])) {
                $cacheItem->expiresAfter($this->cacheConfig['default_ttl']);
            }

            if (!empty($tags)) {
                $cacheItem->tag($tags);
                $success = $this->tagAwareCache->save($cacheItem);
            } else {
                $success = $this->cache->save($cacheItem);
            }

            $this->recordCacheStats('set', $key, microtime(true) - $startTime);

            $this->logger->debug('缓存设置成功', [
                'key' => $key,
                'ttl' => $ttl,
                'tags' => $tags,
                'success' => $success
            ]);

            return $success;

        } catch (\Exception $e) {
            $this->logger->error('缓存设置失败', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);

            $this->recordCacheStats('error', $key, microtime(true) - $startTime);
            return false;
        }
    }

    /**
     * 删除缓存项
     */
    public function delete(string $key): bool
    {
        $startTime = microtime(true);

        try {
            $success = $this->cache->deleteItem($key);

            $this->recordCacheStats('delete', $key, microtime(true) - $startTime);

            $this->logger->debug('缓存删除', [
                'key' => $key,
                'success' => $success
            ]);

            return $success;

        } catch (\Exception $e) {
            $this->logger->error('缓存删除失败', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);

            $this->recordCacheStats('error', $key, microtime(true) - $startTime);
            return false;
        }
    }

    /**
     * 按标签清除缓存
     */
    public function invalidateTags(array $tags): bool
    {
        $startTime = microtime(true);

        try {
            $success = $this->tagAwareCache->invalidateTags($tags);

            $this->logger->info('按标签清除缓存', [
                'tags' => $tags,
                'success' => $success
            ]);

            // 更新统计信息
            foreach ($tags as $tag) {
                $this->recordCacheStats('invalidate_tag', $tag, microtime(true) - $startTime);
            }

            return $success;

        } catch (\Exception $e) {
            $this->logger->error('按标签清除缓存失败', [
                'tags' => $tags,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * 清除所有缓存
     */
    public function clear(): bool
    {
        $startTime = microtime(true);

        try {
            $success = $this->cache->clear();

            $this->logger->info('清除所有缓存', ['success' => $success]);

            $this->recordCacheStats('clear_all', 'system', microtime(true) - $startTime);

            // 重置统计信息
            $this->initializeCacheStats();

            return $success;

        } catch (\Exception $e) {
            $this->logger->error('清除缓存失败', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 预热缓存
     */
    public function warmup(array $warmupConfig = []): array
    {
        $this->logger->info('开始缓存预热', ['config' => $warmupConfig]);

        $results = [];
        $defaultConfig = $this->cacheConfig['warmup'] ?? [];

        foreach ($defaultConfig as $key => $config) {
            try {
                $result = $this->warmupCacheItem($key, $config);
                $results[$key] = $result;

                $this->logger->debug('缓存项预热完成', [
                    'key' => $key,
                    'result' => $result
                ]);

            } catch (\Exception $e) {
                $results[$key] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];

                $this->logger->error('缓存项预热失败', [
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->logger->info('缓存预热完成', ['results' => $results]);

        return $results;
    }

    /**
     * 获取缓存统计信息
     */
    public function getStats(): array
    {
        $totalRequests = array_sum($this->cacheStats['operations']);
        $hitRate = $totalRequests > 0
            ? ($this->cacheStats['operations']['hit'] / $totalRequests) * 100
            : 0;

        return [
            'operations' => $this->cacheStats['operations'],
            'total_requests' => $totalRequests,
            'hit_rate' => round($hitRate, 2),
            'miss_rate' => round(100 - $hitRate, 2),
            'error_rate' => $totalRequests > 0
                ? round(($this->cacheStats['operations']['error'] / $totalRequests) * 100, 2)
                : 0,
            'average_response_time' => $this->calculateAverageResponseTime(),
            'cache_size' => $this->getCacheSize(),
            'memory_usage' => $this->getCacheMemoryUsage()
        ];
    }

    /**
     * 批量获取缓存
     */
    public function getMultiple(array $keys): array
    {
        $startTime = microtime(true);
        $results = [];

        try {
            foreach ($keys as $key) {
                $results[$key] = $this->get($key);
            }

            $this->logger->debug('批量获取缓存', [
                'keys_count' => count($keys),
                'execution_time' => microtime(true) - $startTime
            ]);

            return $results;

        } catch (\Exception $e) {
            $this->logger->error('批量获取缓存失败', [
                'keys' => $keys,
                'error' => $e->getMessage()
            ]);

            return array_fill_keys($keys, null);
        }
    }

    /**
     * 批量设置缓存
     */
    public function setMultiple(array $items, int $ttl = null, array $tags = []): array
    {
        $startTime = microtime(true);
        $results = [];

        foreach ($items as $key => $value) {
            $results[$key] = $this->set($key, $value, $ttl, $tags);
        }

        $successCount = count(array_filter($results));

        $this->logger->debug('批量设置缓存', [
            'items_count' => count($items),
            'success_count' => $successCount,
            'execution_time' => microtime(true) - $startTime
        ]);

        return $results;
    }

    /**
     * 检查缓存项是否存在
     */
    public function has(string $key): bool
    {
        try {
            $cacheItem = $this->cache->getItem($key);
            return $cacheItem->isHit();
        } catch (\Exception $e) {
            $this->logger->error('缓存检查失败', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取缓存项的TTL
     */
    public function getTtl(string $key): ?int
    {
        try {
            $cacheItem = $this->cache->getItem($key);

            if (!$cacheItem->isHit()) {
                return null;
            }

            $expiration = $cacheItem->getMetadata()[CacheItem::METADATA_EXPIRY] ?? null;

            if ($expiration === null) {
                return null; // 永不过期
            }

            return max(0, $expiration - time());

        } catch (\Exception $e) {
            $this->logger->error('获取TTL失败', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 延长缓存项的TTL
     */
    public function extend(string $key, int $additionalTtl): bool
    {
        try {
            $cacheItem = $this->cache->getItem($key);

            if (!$cacheItem->isHit()) {
                return false;
            }

            $currentTtl = $this->getTtl($key) ?? 0;
            $newTtl = $currentTtl + $additionalTtl;

            $cacheItem->expiresAfter($newTtl);

            return $this->cache->save($cacheItem);

        } catch (\Exception $e) {
            $this->logger->error('延长TTL失败', [
                'key' => $key,
                'additional_ttl' => $additionalTtl,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 清理过期缓存
     */
    public function cleanupExpired(): int
    {
        $startTime = microtime(true);
        $cleanedCount = 0;

        try {
            // 这里可以实现具体的过期缓存清理逻辑
            // 某些缓存驱动支持自动清理，这里主要是统计和日志

            $this->logger->info('清理过期缓存完成', [
                'cleaned_count' => $cleanedCount,
                'execution_time' => microtime(true) - $startTime
            ]);

            return $cleanedCount;

        } catch (\Exception $e) {
            $this->logger->error('清理过期缓存失败', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * 获取缓存健康状态
     */
    public function getHealthStatus(): array
    {
        $stats = $this->getStats();

        $health = [
            'status' => 'healthy',
            'issues' => [],
            'recommendations' => []
        ];

        // 检查命中率
        if ($stats['hit_rate'] < 70) {
            $health['status'] = 'warning';
            $health['issues'][] = '缓存命中率过低';
            $health['recommendations'][] = '考虑增加缓存时间或优化缓存策略';
        }

        // 检查错误率
        if ($stats['error_rate'] > 5) {
            $health['status'] = 'critical';
            $health['issues'][] = '缓存错误率过高';
            $health['recommendations'][] = '检查缓存配置和网络连接';
        }

        // 检查响应时间
        if ($stats['average_response_time'] > 100) { // 100ms
            $health['status'] = 'warning';
            $health['issues'][] = '缓存响应时间过长';
            $health['recommendations'][] = '考虑优化缓存驱动或增加内存';
        }

        return array_merge($stats, $health);
    }

    /**
     * 初始化缓存配置
     */
    private function initializeCacheConfig(): void
    {
        $this->cacheConfig = [
            'default_ttl' => 3600, // 1小时
            'max_ttl' => 86400,   // 24小时
            'warmup' => [
                'system_config' => [
                    'callback' => 'getSystemConfig',
                    'ttl' => 86400,
                    'tags' => ['system']
                ],
                'user_preferences' => [
                    'callback' => 'getUserPreferences',
                    'ttl' => 3600,
                    'tags' => ['user']
                ],
                'task_statistics' => [
                    'callback' => 'getTaskStatistics',
                    'ttl' => 300,
                    'tags' => ['statistics']
                ]
            ],
            'tags' => [
                'system' => '系统配置相关缓存',
                'user' => '用户相关缓存',
                'task' => '任务相关缓存',
                'statistics' => '统计数据缓存',
                'temporary' => '临时缓存'
            ]
        ];
    }

    /**
     * 初始化缓存统计
     */
    private function initializeCacheStats(): void
    {
        $this->cacheStats = [
            'operations' => [
                'hit' => 0,
                'miss' => 0,
                'miss_callback' => 0,
                'set' => 0,
                'delete' => 0,
                'invalidate_tag' => 0,
                'clear_all' => 0,
                'error' => 0
            ],
            'response_times' => [],
            'last_reset' => time()
        ];
    }

    /**
     * 预热单个缓存项
     */
    private function warmupCacheItem(string $key, array $config): array
    {
        $callback = $config['callback'] ?? null;
        $ttl = $config['ttl'] ?? $this->cacheConfig['default_ttl'];
        $tags = $config['tags'] ?? [];

        if ($callback === null) {
            return [
                'success' => false,
                'error' => 'No callback specified'
            ];
        }

        try {
            // 这里可以根据回调名称调用相应的方法
            $value = $this->executeWarmupCallback($callback);

            $success = $this->set($key, $value, $ttl, $tags);

            return [
                'success' => $success,
                'value_size' => strlen(serialize($value)),
                'ttl' => $ttl,
                'tags' => $tags
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 执行预热回调
     */
    private function executeWarmupCallback(string $callback): mixed
    {
        // 这里可以实现具体的回调逻辑
        // 例如调用服务方法获取数据

        switch ($callback) {
            case 'getSystemConfig':
                return $this->getSystemConfigData();
            case 'getUserPreferences':
                return $this->getUserPreferencesData();
            case 'getTaskStatistics':
                return $this->getTaskStatisticsData();
            default:
                throw new \InvalidArgumentException("Unknown warmup callback: {$callback}");
        }
    }

    /**
     * 获取系统配置数据
     */
    private function getSystemConfigData(): array
    {
        return [
            'max_concurrent_tasks' => 10,
            'default_retry_attempts' => 3,
            'cache_ttl' => 3600,
            'log_level' => 'info'
        ];
    }

    /**
     * 获取用户偏好数据
     */
    private function getUserPreferencesData(): array
    {
        return [
            'timezone' => 'Asia/Shanghai',
            'language' => 'zh-CN',
            'notification_enabled' => true
        ];
    }

    /**
     * 获取任务统计数据
     */
    private function getTaskStatisticsData(): array
    {
        return [
            'total_tasks' => 1000,
            'pending_tasks' => 50,
            'running_tasks' => 10,
            'completed_tasks' => 900,
            'failed_tasks' => 40
        ];
    }

    /**
     * 记录缓存统计
     */
    private function recordCacheStats(string $operation, string $key, float $responseTime): void
    {
        $this->cacheStats['operations'][$operation]++;
        $this->cacheStats['response_times'][] = $responseTime;

        // 限制响应时间数组大小
        if (count($this->cacheStats['response_times']) > 1000) {
            $this->cacheStats['response_times'] = array_slice(
                $this->cacheStats['response_times'],
                -500
            );
        }
    }

    /**
     * 计算平均响应时间
     */
    private function calculateAverageResponseTime(): float
    {
        $responseTimes = $this->cacheStats['response_times'];

        if (empty($responseTimes)) {
            return 0.0;
        }

        return round(array_sum($responseTimes) / count($responseTimes), 3);
    }

    /**
     * 获取缓存大小
     */
    private function getCacheSize(): string
    {
        // 这里可以实现具体的缓存大小计算逻辑
        return '0MB';
    }

    /**
     * 获取缓存内存使用
     */
    private function getCacheMemoryUsage(): string
    {
        // 这里可以实现具体的内存使用计算逻辑
        return '0MB';
    }
}
