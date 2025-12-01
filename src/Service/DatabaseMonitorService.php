<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * 数据库监控服务
 *
 * 提供数据库连接状态监控、性能指标收集、健康检查等功能
 * 适合生产环境使用，包含完整的错误处理和日志记录
 */
class DatabaseMonitorService
{
    private ManagerRegistry $doctrine;
    private LoggerInterface $logger;
    private ParameterBagInterface $parameterBag;
    private FilesystemAdapter $cache;
    private array $config;

    public function __construct(
        ManagerRegistry $doctrine,
        LoggerInterface $logger,
        ParameterBagInterface $parameterBag
    ) {
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->parameterBag = $parameterBag;
        $this->cache = new FilesystemAdapter('db_monitor', 3600, $this->parameterBag->get('kernel.cache_dir'));

        // 默认配置
        $this->config = [
            'response_time_threshold' => 1000, // ms
            'error_rate_threshold' => 5, // percentage
            'connection_timeout' => 5, // seconds
            'max_retry_attempts' => 3,
            'alert_cooldown' => 300, // seconds
        ];
    }

    /**
     * 获取所有数据库连接的完整状态
     */
    public function getFullDatabaseStatus(): array
    {
        $startTime = microtime(true);
        $status = [
            'timestamp' => date('Y-m-d H:i:s'),
            'execution_time' => 0,
            'environment' => $this->parameterBag->get('kernel.environment'),
            'overall_status' => 'healthy',
            'connections' => [],
            'performance_metrics' => [],
            'alerts' => [],
            'summary' => []
        ];

        try {
            $allConnections = $this->doctrine->getConnections();
            $defaultConnection = $this->doctrine->getDefaultConnectionName();

            $healthyConnections = 0;
            $totalConnections = count($allConnections);
            $totalResponseTime = 0;

            foreach ($allConnections as $name => $connection) {
                $connectionStatus = $this->checkConnectionStatus($name, $connection);
                $status['connections'][$name] = $connectionStatus;

                if ($connectionStatus['status'] === 'connected') {
                    $healthyConnections++;
                    $totalResponseTime += $connectionStatus['response_time'];
                }

                // 收集性能指标
                $status['performance_metrics'][$name] = $this->collectPerformanceMetrics($name, $connection);
            }

            // 计算摘要信息
            $status['summary'] = [
                'total_connections' => $totalConnections,
                'healthy_connections' => $healthyConnections,
                'unhealthy_connections' => $totalConnections - $healthyConnections,
                'health_percentage' => round(($healthyConnections / $totalConnections) * 100, 2),
                'average_response_time' => $healthyConnections > 0 ? round($totalResponseTime / $healthyConnections, 2) : 0,
                'default_connection' => $defaultConnection
            ];

            // 检查告警条件
            $status['alerts'] = $this->checkAlerts($status);

            // 确定整体状态
            if ($status['summary']['unhealthy_connections'] > 0) {
                $status['overall_status'] = 'degraded';
            }

            if ($status['summary']['health_percentage'] < 50) {
                $status['overall_status'] = 'unhealthy';
            }

        } catch (\Exception $e) {
            $this->logger->error('数据库监控检查失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            $status['overall_status'] = 'error';
            $status['error'] = $e->getMessage();
        }

        $status['execution_time'] = round((microtime(true) - $startTime) * 1000, 2);

        // 缓存结果
        $this->cacheStatus($status);

        return $status;
    }

    /**
     * 检查单个连接状态
     */
    public function checkConnectionStatus(string $connectionName, ?Connection $connection = null): array
    {
        if ($connection === null) {
            $connection = $this->doctrine->getConnection($connectionName);
        }

        $status = [
            'name' => $connectionName,
            'status' => 'unknown',
            'database' => null,
            'host' => null,
            'port' => null,
            'driver' => null,
            'response_time' => 0,
            'error' => null,
            'mysql_version' => null,
            'connection_id' => null,
            'last_check' => date('Y-m-d H:i:s'),
            'is_default' => $connectionName === $this->doctrine->getDefaultConnectionName()
        ];

        try {
            // 获取连接参数
            $params = $connection->getParams();
            $status['database'] = $params['dbname'] ?? 'unknown';
            $status['host'] = $params['host'] ?? 'unknown';
            $status['port'] = $params['port'] ?? 'default';
            $status['driver'] = $params['driver'] ?? 'unknown';

            // 测试连接响应时间
            $testStart = microtime(true);
            $connection->executeQuery('SELECT 1');
            $status['response_time'] = round((microtime(true) - $testStart) * 1000, 2);
            $status['status'] = 'connected';

            // 获取MySQL版本和连接ID
            try {
                $infoQuery = $connection->executeQuery('
                    SELECT VERSION() as version, CONNECTION_ID() as connection_id
                ');
                $info = $infoQuery->fetchAssociative();
                $status['mysql_version'] = $info['version'] ?? 'unknown';
                $status['connection_id'] = $info['connection_id'] ?? 'unknown';
            } catch (\Exception $e) {
                $this->logger->warning('获取数据库信息失败', [
                    'connection' => $connectionName,
                    'error' => $e->getMessage()
                ]);
            }

            // 获取数据库详细信息
            $status['details'] = $this->getDatabaseDetails($connection);

        } catch (\Exception $e) {
            $status['status'] = 'error';
            $status['error'] = $e->getMessage();

            $this->logger->error('数据库连接检查失败', [
                'connection' => $connectionName,
                'error' => $e->getMessage(),
                'database' => $status['database'],
                'host' => $status['host']
            ]);
        }

        return $status;
    }

    /**
     * 收集性能指标
     */
    public function collectPerformanceMetrics(string $connectionName, ?Connection $connection = null): array
    {
        if ($connection === null) {
            $connection = $this->doctrine->getConnection($connectionName);
        }

        $metrics = [
            'connection_name' => $connectionName,
            'timestamp' => date('Y-m-d H:i:s'),
            'queries_per_second' => 0,
            'slow_queries' => 0,
            'connections_used' => 0,
            'connections_max' => 0,
            'buffer_pool_size' => 0,
            'buffer_pool_usage' => 0,
            'table_locks_waited' => 0,
            'table_locks_immediate' => 0,
            'error_rate' => 0
        ];

        try {
            // 获取MySQL状态变量
            $statusQuery = $connection->executeQuery('SHOW GLOBAL STATUS');
            $statusData = [];
            while ($row = $statusQuery->fetchAssociative()) {
                $statusData[$row['Variable_name']] = $row['Value'];
            }

            // 获取MySQL变量
            $variablesQuery = $connection->executeQuery('SHOW GLOBAL VARIABLES');
            $variablesData = [];
            while ($row = $variablesQuery->fetchAssociative()) {
                $variablesData[$row['Variable_name']] = $row['Value'];
            }

            // 填充指标
            $metrics['queries_per_second'] = $this->calculateQueriesPerSecond($statusData);
            $metrics['slow_queries'] = (int)($statusData['Slow_queries'] ?? 0);
            $metrics['connections_used'] = (int)($statusData['Threads_connected'] ?? 0);
            $metrics['connections_max'] = (int)($variablesData['max_connections'] ?? 100);
            $metrics['buffer_pool_size'] = (int)($variablesData['innodb_buffer_pool_size'] ?? 0);
            $metrics['buffer_pool_usage'] = $this->calculateBufferPoolUsage($statusData, $variablesData);
            $metrics['table_locks_waited'] = (int)($statusData['Table_locks_waited'] ?? 0);
            $metrics['table_locks_immediate'] = (int)($statusData['Table_locks_immediate'] ?? 0);

            // 计算错误率
            $totalConnections = (int)($statusData['Connections'] ?? 0);
            $connectionErrors = (int)($statusData['Connection_errors_max_connections'] ?? 0);
            $metrics['error_rate'] = $totalConnections > 0 ? round(($connectionErrors / $totalConnections) * 100, 2) : 0;

        } catch (\Exception $e) {
            $this->logger->warning('收集性能指标失败', [
                'connection' => $connectionName,
                'error' => $e->getMessage()
            ]);
            $metrics['error'] = $e->getMessage();
        }

        return $metrics;
    }

    /**
     * 健康检查端点
     */
    public function healthCheck(): array
    {
        $startTime = microtime(true);
        $health = [
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'checks' => [],
            'response_time' => 0
        ];

        try {
            $connections = $this->doctrine->getConnections();
            $allPassed = true;

            foreach ($connections as $name => $connection) {
                $checkResult = $this->performHealthCheck($name, $connection);
                $health['checks'][$name] = $checkResult;

                if ($checkResult['status'] !== 'pass') {
                    $allPassed = false;
                }
            }

            $health['status'] = $allPassed ? 'healthy' : 'unhealthy';

        } catch (\Exception $e) {
            $health['status'] = 'error';
            $health['error'] = $e->getMessage();
        }

        $health['response_time'] = round((microtime(true) - $startTime) * 1000, 2);

        return $health;
    }

    /**
     * 获取历史统计数据
     */
    public function getHistoricalStats(int $hours = 24): array
    {
        $stats = [
            'period_hours' => $hours,
            'data_points' => [],
            'summary' => [
                'avg_response_time' => 0,
                'max_response_time' => 0,
                'min_response_time' => PHP_FLOAT_MAX,
                'total_checks' => 0,
                'failed_checks' => 0,
                'availability_percentage' => 0
            ]
        ];

        try {
            // 从缓存或日志中获取历史数据
            // 这里简化实现，实际生产环境可能需要专门的时序数据库
            $cacheKey = "db_stats_{$hours}h";
            $cached = $this->cache->getItem($cacheKey);

            if ($cached->isHit()) {
                return $cached->get();
            }

            // 模拟历史数据收集
            $stats = $this->generateMockHistoricalData($hours);

            // 缓存结果
            $cached->set($stats);
            $cached->expiresAfter(300); // 5分钟缓存
            $this->cache->save($cached);

        } catch (\Exception $e) {
            $this->logger->error('获取历史统计数据失败', [
                'error' => $e->getMessage(),
                'hours' => $hours
            ]);
            $stats['error'] = $e->getMessage();
        }

        return $stats;
    }

    /**
     * 执行单个健康检查
     */
    private function performHealthCheck(string $name, Connection $connection): array
    {
        $check = [
            'name' => $name,
            'status' => 'pass',
            'response_time' => 0,
            'error' => null,
            'details' => []
        ];

        try {
            $start = microtime(true);

            // 基本连接测试
            $connection->executeQuery('SELECT 1');
            $check['response_time'] = round((microtime(true) - $start) * 1000, 2);

            // 响应时间检查
            if ($check['response_time'] > $this->config['response_time_threshold']) {
                $check['status'] = 'warn';
                $check['details'][] = "响应时间过长: {$check['response_time']}ms";
            }

            // 数据库大小检查
            try {
                $sizeQuery = $connection->executeQuery("
                    SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                    FROM information_schema.tables
                    WHERE table_schema = DATABASE()
                ");
                $size = $sizeQuery->fetchOne();
                $check['details']['database_size_mb'] = $size;
            } catch (\Exception $e) {
                $check['details']['database_size_error'] = $e->getMessage();
            }

        } catch (\Exception $e) {
            $check['status'] = 'fail';
            $check['error'] = $e->getMessage();
        }

        return $check;
    }

    /**
     * 获取数据库详细信息
     */
    private function getDatabaseDetails(Connection $connection): array
    {
        $details = [];

        try {
            // 数据库大小
            $sizeQuery = $connection->executeQuery("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
            ");
            $details['size_mb'] = $sizeQuery->fetchOne();

            // 表数量
            $tableQuery = $connection->executeQuery("
                SELECT COUNT(*) as table_count
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
            ");
            $details['table_count'] = $tableQuery->fetchOne();

            // 字符集信息
            $charsetQuery = $connection->executeQuery("
                SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
                FROM information_schema.SCHEMATA
                WHERE SCHEMA_NAME = DATABASE()
            ");
            $charsetInfo = $charsetQuery->fetchAssociative();
            $details['charset'] = $charsetInfo['DEFAULT_CHARACTER_SET_NAME'] ?? 'unknown';
            $details['collation'] = $charsetInfo['DEFAULT_COLLATION_NAME'] ?? 'unknown';

        } catch (\Exception $e) {
            $details['error'] = $e->getMessage();
        }

        return $details;
    }

    /**
     * 检查告警条件
     */
    private function checkAlerts(array $status): array
    {
        $alerts = [];
        $cacheKey = 'db_alerts_last_sent';
        $lastSent = $this->cache->getItem($cacheKey);

        foreach ($status['connections'] as $name => $connection) {
            // 连接失败告警
            if ($connection['status'] === 'error') {
                $alerts[] = [
                    'type' => 'connection_error',
                    'severity' => 'critical',
                    'message' => "数据库连接 {$name} 失败",
                    'details' => $connection['error'],
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }

            // 响应时间告警
            if ($connection['response_time'] > $this->config['response_time_threshold']) {
                $alerts[] = [
                    'type' => 'slow_response',
                    'severity' => 'warning',
                    'message' => "数据库连接 {$name} 响应时间过长",
                    'details' => "响应时间: {$connection['response_time']}ms",
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
        }

        // 整体健康度告警
        if ($status['summary']['health_percentage'] < 80) {
            $alerts[] = [
                'type' => 'low_health',
                'severity' => 'warning',
                'message' => "数据库整体健康度过低",
                'details' => "健康度: {$status['summary']['health_percentage']}%",
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }

        return $alerts;
    }

    /**
     * 缓存状态数据
     */
    private function cacheStatus(array $status): void
    {
        try {
            $cacheKey = 'db_status_' . date('Y-m-d H:i');
            $cached = $this->cache->getItem($cacheKey);
            $cached->set($status);
            $cached->expiresAfter(3600); // 1小时过期
            $this->cache->save($cached);
        } catch (\Exception $e) {
            $this->logger->warning('缓存状态数据失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 计算每秒查询数
     */
    private function calculateQueriesPerSecond(array $statusData): float
    {
        $queries = (int)($statusData['Questions'] ?? 0);
        $uptime = (int)($statusData['Uptime'] ?? 1);

        return $uptime > 0 ? round($queries / $uptime, 2) : 0;
    }

    /**
     * 计算缓冲池使用率
     */
    private function calculateBufferPoolUsage(array $statusData, array $variablesData): float
    {
        $poolSize = (int)($variablesData['innodb_buffer_pool_size'] ?? 0);
        $poolPages = (int)($statusData['Innodb_buffer_pool_pages_total'] ?? 1);
        $poolPagesDirty = (int)($statusData['Innodb_buffer_pool_pages_dirty'] ?? 0);

        return $poolPages > 0 ? round(($poolPagesDirty / $poolPages) * 100, 2) : 0;
    }

    /**
     * 生成模拟历史数据（生产环境应使用真实的时序数据库）
     */
    private function generateMockHistoricalData(int $hours): array
    {
        $stats = [
            'period_hours' => $hours,
            'data_points' => [],
            'summary' => [
                'avg_response_time' => 0,
                'max_response_time' => 0,
                'min_response_time' => PHP_FLOAT_MAX,
                'total_checks' => 0,
                'failed_checks' => 0,
                'availability_percentage' => 0
            ]
        ];

        $totalResponseTime = 0;
        $dataPointCount = $hours * 12; // 每5分钟一个数据点

        for ($i = 0; $i < $dataPointCount; $i++) {
            $timestamp = date('Y-m-d H:i:s', time() - ($i * 300)); // 5分钟间隔
            $responseTime = rand(50, 200); // 模拟响应时间
            $isHealthy = rand(1, 100) > 5; // 95%可用性

            $dataPoint = [
                'timestamp' => $timestamp,
                'response_time' => $responseTime,
                'status' => $isHealthy ? 'healthy' : 'unhealthy'
            ];

            $stats['data_points'][] = $dataPoint;
            $stats['summary']['total_checks']++;

            if ($isHealthy) {
                $totalResponseTime += $responseTime;
                $stats['summary']['max_response_time'] = max($stats['summary']['max_response_time'], $responseTime);
                $stats['summary']['min_response_time'] = min($stats['summary']['min_response_time'], $responseTime);
            } else {
                $stats['summary']['failed_checks']++;
            }
        }

        $stats['summary']['avg_response_time'] = $stats['summary']['total_checks'] > 0 ?
            round($totalResponseTime / $stats['summary']['total_checks'], 2) : 0;

        $stats['summary']['availability_percentage'] = $stats['summary']['total_checks'] > 0 ?
            round((($stats['summary']['total_checks'] - $stats['summary']['failed_checks']) / $stats['summary']['total_checks']) * 100, 2) : 0;

        return $stats;
    }
}
