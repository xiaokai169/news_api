<?php
// src/Service/PerformanceMonitorService.php
namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

class PerformanceMonitorService
{
    private LoggerInterface $logger;
    private array $metrics = [];
    private array $config;

    public function __construct(LoggerInterface $logger, ParameterBagInterface $params)
    {
        $this->logger = $logger;
        $this->loadConfig($params->get('kernel.project_dir') . '/config/monitoring/performance.yaml');
    }

    private function loadConfig(string $configFile): void
    {
        if (file_exists($configFile)) {
            $this->config = yaml_parse_file($configFile);
        } else {
            // 默认配置
            $this->config = [
                'sampling' => [
                    'enabled' => true,
                    'sample_rate' => 0.1, // 10%采样率
                    'max_samples' => 1000
                ],
                'thresholds' => [
                    'response_time' => 5000, // 5秒
                    'memory_usage' => 512, // 512MB
                    'cpu_usage' => 80 // 80%
                ],
                'storage' => [
                    'type' => 'file',
                    'path' => '/var/log/performance_metrics.json',
                    'retention_days' => 7
                ]
            ];
        }
    }

    public function startRequest(string $requestId, string $method, string $uri): void
    {
        if (!$this->shouldSample()) {
            return;
        }

        $this->metrics[$requestId] = [
            'request_id' => $requestId,
            'method' => $method,
            'uri' => $uri,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'start_cpu' => $this->getCpuUsage()
        ];
    }

    public function endRequest(string $requestId, int $statusCode, array $additionalData = []): void
    {
        if (!isset($this->metrics[$requestId])) {
            return;
        }

        $metric = &$this->metrics[$requestId];
        $endTime = microtime(true);

        $metric['end_time'] = $endTime;
        $metric['duration'] = ($endTime - $metric['start_time']) * 1000; // 毫秒
        $metric['memory_used'] = memory_get_usage(true) - $metric['start_memory'];
        $metric['peak_memory'] = memory_get_peak_usage(true);
        $metric['cpu_usage'] = $this->getCpuUsage() - $metric['start_cpu'];
        $metric['status_code'] = $statusCode;
        $metric['timestamp'] = date('Y-m-d H:i:s');

        // 合并额外数据
        $metric = array_merge($metric, $additionalData);

        // 检查阈值
        $this->checkThresholds($metric);

        // 存储指标
        $this->storeMetric($metric);

        // 清理
        unset($this->metrics[$requestId]);
    }

    private function shouldSample(): bool
    {
        if (!$this->config['sampling']['enabled']) {
            return false;
        }

        return (mt_rand() / mt_getrandmax()) <= $this->config['sampling']['sample_rate'];
    }

    private function getCpuUsage(): float
    {
        $load = sys_getloadavg();
        return $load[0] ?? 0.0;
    }

    private function checkThresholds(array $metric): void
    {
        $thresholds = $this->config['thresholds'];

        // 检查响应时间
        if ($metric['duration'] > $thresholds['response_time']) {
            $this->logger->warning('响应时间超过阈值', [
                'request_id' => $metric['request_id'],
                'duration' => $metric['duration'],
                'threshold' => $thresholds['response_time'],
                'uri' => $metric['uri']
            ]);
        }

        // 检查内存使用
        if ($metric['peak_memory'] > $thresholds['memory_usage'] * 1024 * 1024) {
            $this->logger->warning('内存使用超过阈值', [
                'request_id' => $metric['request_id'],
                'memory_used' => $metric['peak_memory'],
                'threshold' => $thresholds['memory_usage'] * 1024 * 1024,
                'uri' => $metric['uri']
            ]);
        }

        // 检查CPU使用
        if ($metric['cpu_usage'] > $thresholds['cpu_usage']) {
            $this->logger->warning('CPU使用超过阈值', [
                'request_id' => $metric['request_id'],
                'cpu_usage' => $metric['cpu_usage'],
                'threshold' => $thresholds['cpu_usage'],
                'uri' => $metric['uri']
            ]);
        }
    }

    private function storeMetric(array $metric): void
    {
        $storageType = $this->config['storage']['type'];

        switch ($storageType) {
            case 'file':
                $this->storeToFile($metric);
                break;
            case 'redis':
                $this->storeToRedis($metric);
                break;
            case 'database':
                $this->storeToDatabase($metric);
                break;
        }
    }

    private function storeToFile(array $metric): void
    {
        $filePath = $this->config['storage']['path'];
        $retentionDays = $this->config['storage']['retention_days'];

        // 创建目录
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // 读取现有数据
        $data = [];
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            if ($content !== false) {
                $data = json_decode($content, true) ?: [];
            }
        }

        // 添加新指标
        $data[] = $metric;

        // 清理旧数据
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-$retentionDays days"));
        $data = array_filter($data, function($item) use ($cutoffDate) {
            return $item['timestamp'] >= $cutoffDate;
        });

        // 限制最大样本数
        $maxSamples = $this->config['sampling']['max_samples'];
        if (count($data) > $maxSamples) {
            $data = array_slice($data, -$maxSamples);
        }

        // 写入文件
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function storeToRedis(array $metric): void
    {
        // Redis存储实现
        $this->logger->debug('存储性能指标到Redis', ['metric' => $metric]);
    }

    private function storeToDatabase(array $metric): void
    {
        // 数据库存储实现
        $this->logger->debug('存储性能指标到数据库', ['metric' => $metric]);
    }

    public function getMetricsSummary(int $minutes = 60): array
    {
        $filePath = $this->config['storage']['path'];

        if (!file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true) ?: [];
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-$minutes minutes"));

        // 过滤时间范围
        $recentData = array_filter($data, function($item) use ($cutoffTime) {
            return $item['timestamp'] >= $cutoffTime;
        });

        if (empty($recentData)) {
            return [];
        }

        // 计算汇总统计
        $durations = array_column($recentData, 'duration');
        $memoryUsages = array_column($recentData, 'memory_used');
        $statusCodes = array_column($recentData, 'status_code');

        $statusCounts = array_count_values($statusCodes);
        $errorCount = ($statusCounts[400] ?? 0) + ($statusCounts[500] ?? 0);

        return [
            'total_requests' => count($recentData),
            'avg_response_time' => array_sum($durations) / count($durations),
            'max_response_time' => max($durations),
            'min_response_time' => min($durations),
            'avg_memory_usage' => array_sum($memoryUsages) / count($memoryUsages),
            'max_memory_usage' => max($memoryUsages),
            'error_count' => $errorCount,
            'error_rate' => ($errorCount / count($recentData)) * 100,
            'time_range' => [
                'start' => $cutoffTime,
                'end' => date('Y-m-d H:i:s')
            ]
        ];
    }

    public function getSlowQueries(int $limit = 10): array
    {
        $filePath = $this->config['storage']['path'];

        if (!file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true) ?: [];

        // 按响应时间排序
        usort($data, function($a, $b) {
            return $b['duration'] <=> $a['duration'];
        });

        return array_slice($data, 0, $limit);
    }

    public function getErrorTrends(int $hours = 24): array
    {
        $filePath = $this->config['storage']['path'];

        if (!file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true) ?: [];
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-$hours hours"));

        // 过滤错误请求
        $errors = array_filter($data, function($item) use ($cutoffTime) {
            return $item['timestamp'] >= $cutoffTime && $item['status_code'] >= 400;
        });

        // 按小时分组
        $hourlyErrors = [];
        foreach ($errors as $error) {
            $hour = date('Y-m-d H:00:00', strtotime($error['timestamp']));
            if (!isset($hourlyErrors[$hour])) {
                $hourlyErrors[$hour] = 0;
            }
            $hourlyErrors[$hour]++;
        }

        return $hourlyErrors;
    }
}
