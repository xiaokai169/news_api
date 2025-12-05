<?php

/**
 * 微信同步日志分析器
 * 专门用于分析同步相关的日志问题
 *
 * 使用方法:
 * php public/sync_log_analyzer.php [hours] [keyword]
 * hours: 分析最近几小时的日志 (默认24小时)
 * keyword: 关键词过滤 (可选)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;

class SyncLogAnalyzer
{
    private Kernel $kernel;
    private array $logFiles = [];
    private array $analysis = [];

    public function __construct()
    {
        $this->kernel = new Kernel($_ENV['APP_ENV'] ?? 'dev', (bool)($_ENV['APP_DEBUG'] ?? true));
        $this->kernel->boot();

        $this->logFiles = [
            'wechat' => __DIR__ . '/../var/log/wechat.log',
            'database' => __DIR__ . '/../var/log/database.log',
            'error' => __DIR__ . '/../var/log/error.log',
            'prod' => __DIR__ . '/../var/log/prod.log',
            'dev' => __DIR__ . '/../var/log/dev.log'
        ];
    }

    public function analyze(int $hours = 24, string $keyword = ''): void
    {
        echo "=== 微信同步日志分析器 ===\n";
        echo "分析时间范围: 最近 {$hours} 小时\n";
        if ($keyword) {
            echo "关键词过滤: {$keyword}\n";
        }
        echo "开始时间: " . date('Y-m-d H:i:s') . "\n\n";

        $cutoffTime = time() - ($hours * 3600);

        foreach ($this->logFiles as $type => $logFile) {
            $this->analyzeLogFile($type, $logFile, $cutoffTime, $keyword);
        }

        $this->generateSummary();
        $this->identifyIssues();
        $this->provideRecommendations();
    }

    private function analyzeLogFile(string $type, string $logFile, int $cutoffTime, string $keyword): void
    {
        echo "=== 分析日志文件: {$type} ===\n";

        if (!file_exists($logFile)) {
            echo "文件不存在: {$logFile}\n\n";
            return;
        }

        $fileSize = filesize($logFile);
        $modifiedTime = date('Y-m-d H:i:s', filemtime($logFile));
        echo "文件大小: " . $this->formatBytes($fileSize) . "\n";
        echo "修改时间: {$modifiedTime}\n";

        try {
            $lines = $this->readLogFile($logFile, $cutoffTime);
            $filteredLines = $keyword ? $this->filterByKeyword($lines, $keyword) : $lines;

            echo "时间范围内行数: " . count($lines) . "\n";
            echo "关键词过滤后行数: " . count($filteredLines) . "\n";

            $this->analysis[$type] = [
                'file_path' => $logFile,
                'total_lines' => count($lines),
                'filtered_lines' => count($filteredLines),
                'errors' => $this->extractErrors($filteredLines),
                'warnings' => $this->extractWarnings($filteredLines),
                'sync_operations' => $this->extractSyncOperations($filteredLines),
                'database_operations' => $this->extractDatabaseOperations($filteredLines),
                'api_calls' => $this->extractApiCalls($filteredLines)
            ];

            // 显示关键信息
            $this->displayKeyInfo($type, $this->analysis[$type]);

        } catch (\Exception $e) {
            echo "分析文件失败: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    private function readLogFile(string $logFile, int $cutoffTime): array
    {
        $lines = [];
        $handle = fopen($logFile, 'r');

        if (!$handle) {
            throw new \Exception("无法打开文件: {$logFile}");
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if (empty($line)) continue;

            // 提取时间戳
            $timestamp = $this->extractTimestamp($line);
            if ($timestamp && $timestamp >= $cutoffTime) {
                $lines[] = $line;
            }
        }

        fclose($handle);
        return $lines;
    }

    private function filterByKeyword(array $lines, string $keyword): array
    {
        return array_filter($lines, function($line) use ($keyword) {
            return stripos($line, $keyword) !== false;
        });
    }

    private function extractErrors(array $lines): array
    {
        $errors = [];
        foreach ($lines as $line) {
            if (preg_match('/(ERROR|Exception|Fatal|Failed)/i', $line)) {
                $errors[] = $line;
            }
        }
        return $errors;
    }

    private function extractWarnings(array $lines): array
    {
        $warnings = [];
        foreach ($lines as $line) {
            if (preg_match('/(WARNING|Warn|Deprecated)/i', $line)) {
                $warnings[] = $line;
            }
        }
        return $warnings;
    }

    private function extractSyncOperations(array $lines): array
    {
        $syncOps = [];
        foreach ($lines as $line) {
            if (preg_match('/(sync|同步|WechatArticleSyncService)/i', $line)) {
                $syncOps[] = $line;
            }
        }
        return $syncOps;
    }

    private function extractDatabaseOperations(array $lines): array
    {
        $dbOps = [];
        foreach ($lines as $line) {
            if (preg_match('/(INSERT|UPDATE|DELETE|SELECT|official|wechat_public_account)/i', $line)) {
                $dbOps[] = $line;
            }
        }
        return $dbOps;
    }

    private function extractApiCalls(array $lines): array
    {
        $apiCalls = [];
        foreach ($lines as $line) {
            if (preg_match('/(api\.weixin\.qq\.com|access_token|freepublish)/i', $line)) {
                $apiCalls[] = $line;
            }
        }
        return $apiCalls;
    }

    private function displayKeyInfo(string $type, array $analysis): void
    {
        echo "错误数量: " . count($analysis['errors']) . "\n";
        echo "警告数量: " . count($analysis['warnings']) . "\n";
        echo "同步操作: " . count($analysis['sync_operations']) . "\n";
        echo "数据库操作: " . count($analysis['database_operations']) . "\n";
        echo "API调用: " . count($analysis['api_calls']) . "\n";

        // 显示最近的几个错误
        if (!empty($analysis['errors'])) {
            echo "\n最近错误:\n";
            foreach (array_slice($analysis['errors'], -3) as $error) {
                echo "  " . substr($error, 0, 100) . "...\n";
            }
        }

        // 显示最近的同步操作
        if (!empty($analysis['sync_operations'])) {
            echo "\n最近同步操作:\n";
            foreach (array_slice($analysis['sync_operations'], -3) as $sync) {
                echo "  " . substr($sync, 0, 100) . "...\n";
            }
        }
    }

    private function generateSummary(): void
    {
        echo "=== 分析总结 ===\n";

        $totalErrors = 0;
        $totalWarnings = 0;
        $totalSyncOps = 0;
        $totalDbOps = 0;
        $totalApiCalls = 0;

        foreach ($this->analysis as $type => $data) {
            $totalErrors += count($data['errors']);
            $totalWarnings += count($data['warnings']);
            $totalSyncOps += count($data['sync_operations']);
            $totalDbOps += count($data['database_operations']);
            $totalApiCalls += count($data['api_calls']);
        }

        echo "总错误数: {$totalErrors}\n";
        echo "总警告数: {$totalWarnings}\n";
        echo "总同步操作: {$totalSyncOps}\n";
        echo "总数据库操作: {$totalDbOps}\n";
        echo "总API调用: {$totalApiCalls}\n";

        if ($totalErrors > 0) {
            echo "⚠️  发现错误，需要进一步调查\n";
        }

        if ($totalSyncOps === 0) {
            echo "⚠️  未发现同步操作，可能同步未执行\n";
        }

        if ($totalDbOps === 0) {
            echo "⚠️  未发现数据库操作，可能数据未写入\n";
        }

        echo "\n";
    }

    private function identifyIssues(): void
    {
        echo "=== 问题识别 ===\n";

        $issues = [];

        // 检查常见问题模式
        foreach ($this->analysis as $type => $data) {
            foreach ($data['errors'] as $error) {
                if (strpos($error, 'access_token') !== false) {
                    $issues[] = "微信API access_token获取失败";
                }
                if (strpos($error, 'database') !== false || strpos($error, 'SQL') !== false) {
                    $issues[] = "数据库操作失败";
                }
                if (strpos($error, 'distributed_lock') !== false) {
                    $issues[] = "分布式锁问题";
                }
                if (strpos($error, 'Connection') !== false) {
                    $issues[] = "网络连接问题";
                }
            }

            foreach ($data['warnings'] as $warning) {
                if (strpos($warning, 'rollback') !== false) {
                    $issues[] = "事务回滚，数据未保存";
                }
                if (strpos($warning, 'skip') !== false) {
                    $issues[] = "数据被跳过，可能重复";
                }
            }
        }

        $issues = array_unique($issues);

        if (empty($issues)) {
            echo "✅ 未发现明显问题模式\n";
        } else {
            foreach ($issues as $issue) {
                echo "❌ {$issue}\n";
            }
        }

        echo "\n";
    }

    private function provideRecommendations(): void
    {
        echo "=== 建议 ===\n";

        $recommendations = [
            "1. 检查微信API配置是否正确",
            "2. 验证数据库连接权限",
            "3. 确认分布式锁表状态",
            "4. 检查同步接口调用参数",
            "5. 查看完整的错误堆栈信息",
            "6. 验证公众号账户状态",
            "7. 检查网络连接稳定性"
        ];

        foreach ($recommendations as $rec) {
            echo $rec . "\n";
        }

        echo "\n=== 详细日志文件 ===\n";
        foreach ($this->analysis as $type => $data) {
            if (!empty($data['errors']) || !empty($data['warnings'])) {
                echo "查看详细日志: {$data['file_path']}\n";
            }
        }
    }

    private function extractTimestamp(string $line): ?int
    {
        // 尝试多种时间戳格式
        $patterns = [
            '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/',
            '/(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})/',
            '/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line, $matches)) {
                $timestamp = strtotime($matches[1]);
                if ($timestamp !== false) {
                    return $timestamp;
                }
            }
        }

        return null;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// 主执行逻辑
$hours = (int)($argv[1] ?? 24);
$keyword = $argv[2] ?? '';

try {
    $analyzer = new SyncLogAnalyzer();
    $analyzer->analyze($hours, $keyword);
} catch (\Exception $e) {
    echo "日志分析失败: " . $e->getMessage() . "\n";
    echo "堆栈跟踪: " . $e->getTraceAsString() . "\n";
    exit(1);
}
