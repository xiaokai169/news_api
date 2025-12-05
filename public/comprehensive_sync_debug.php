<?php

/**
 * 微信同步系统性调试脚本
 * 用于排查"显示同步成功，但数据库没有值"的问题
 *
 * 使用方法:
 * php public/comprehensive_sync_debug.php [account_id] [debug_level]
 *
 * debug_level: 1-基础检查, 2-详细检查, 3-完整调试
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use App\Service\WechatArticleSyncService;
use App\Service\WechatApiService;
use App\Service\DistributedLockService;
use App\Repository\OfficialRepository;
use App\Repository\WechatPublicAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ComprehensiveSyncDebug
{
    private Kernel $kernel;
    private EntityManagerInterface $entityManager;
    private WechatArticleSyncService $syncService;
    private WechatApiService $apiService;
    private DistributedLockService $lockService;
    private OfficialRepository $officialRepository;
    private WechatPublicAccountRepository $accountRepository;

    private array $debugLog = [];
    private int $debugLevel = 2;

    public function __construct()
    {
        $this->kernel = new Kernel($_ENV['APP_ENV'] ?? 'dev', (bool)($_ENV['APP_DEBUG'] ?? true));
        $this->kernel->boot();

        $container = $this->kernel->getContainer();

        $this->entityManager = $container->get('doctrine.orm.default_entity_manager');
        $this->syncService = $container->get(WechatArticleSyncService::class);
        $this->apiService = $container->get(WechatApiService::class);
        $this->lockService = $container->get(DistributedLockService::class);
        $this->officialRepository = $container->get(OfficialRepository::class);
        $this->accountRepository = $container->get(WechatPublicAccountRepository::class);
    }

    public function run(string $accountId = null, int $debugLevel = 2): void
    {
        $this->debugLevel = $debugLevel;

        echo "=== 微信同步系统性调试开始 ===\n";
        echo "调试级别: {$debugLevel}\n";
        echo "目标账号ID: " . ($accountId ?: '全部') . "\n";
        echo "开始时间: " . date('Y-m-d H:i:s') . "\n\n";

        // 1. 环境基础检查
        $this->checkEnvironment();

        // 2. 数据库连接和表结构检查
        $this->checkDatabase();

        // 3. 微信API连接检查
        if ($accountId) {
            $this->checkWechatApi($accountId);
        }

        // 4. 分布式锁检查
        $this->checkDistributedLocks();

        // 5. 数据同步流程测试
        if ($accountId) {
            $this->testSyncProcess($accountId);
        }

        // 6. 数据一致性检查
        $this->checkDataConsistency($accountId);

        // 7. 日志分析
        $this->analyzeLogs();

        echo "\n=== 调试报告 ===\n";
        $this->generateReport();
    }

    private function checkEnvironment(): void
    {
        $this->log("=== 1. 环境基础检查 ===");

        // 检查Symfony环境
        $env = $_ENV['APP_ENV'] ?? 'unknown';
        $debug = $_ENV['APP_DEBUG'] ?? 'unknown';
        $this->log("Symfony环境: {$env}, Debug模式: {$debug}");

        // 检查数据库连接配置
        $dbUrl = $_ENV['DATABASE_URL'] ?? 'not set';
        $this->log("数据库URL: " . $this->maskSensitiveData($dbUrl));

        // 检查必要的扩展
        $requiredExtensions = ['pdo', 'pdo_mysql', 'curl', 'json'];
        foreach ($requiredExtensions as $ext) {
            $status = extension_loaded($ext) ? '✓' : '✗';
            $this->log("扩展 {$ext}: {$status}");
        }

        // 检查目录权限
        $logDir = __DIR__ . '/../var/log';
        $cacheDir = __DIR__ . '/../var/cache';
        $this->log("日志目录权限: " . (is_writable($logDir) ? '✓' : '✗'));
        $this->log("缓存目录权限: " . (is_writable($cacheDir) ? '✓' : '✗'));
    }

    private function checkDatabase(): void
    {
        $this->log("\n=== 2. 数据库连接和表结构检查 ===");

        try {
            // 测试数据库连接
            $connection = $this->entityManager->getConnection();
            $connection->connect();
            $this->log("数据库连接: ✓ 成功");

            // 检查数据库版本
            $version = $connection->fetchOne('SELECT VERSION()');
            $this->log("MySQL版本: {$version}");

            // 检查关键表是否存在
            $requiredTables = ['official', 'wechat_public_account', 'distributed_locks', 'sys_news_article_category'];
            foreach ($requiredTables as $table) {
                $exists = $this->tableExists($table);
                $status = $exists ? '✓' : '✗';
                $this->log("表 {$table}: {$status}");

                if ($exists && $this->debugLevel >= 2) {
                    $this->checkTableStructure($table);
                }
            }

            // 检查数据量
            $officialCount = $connection->fetchOne('SELECT COUNT(*) FROM official');
            $accountCount = $connection->fetchOne('SELECT COUNT(*) FROM wechat_public_account');
            $this->log("official表记录数: {$officialCount}");
            $this->log("wechat_public_account表记录数: {$accountCount}");

        } catch (\Exception $e) {
            $this->log("数据库检查失败: " . $e->getMessage(), 'ERROR');
        }
    }

    private function checkWechatApi(string $accountId): void
    {
        $this->log("\n=== 3. 微信API连接检查 ===");

        try {
            $account = $this->accountRepository->find($accountId);
            if (!$account) {
                $this->log("公众号账户不存在: {$accountId}", 'ERROR');
                return;
            }

            $this->log("公众号名称: " . ($account->getName() ?: '未设置'));
            $this->log("公众号状态: " . ($account->isActive() ? '激活' : '未激活'));
            $this->log("AppId: " . ($account->getAppId() ?: '未设置'));
            $this->log("AppSecret: " . ($account->getAppSecret() ? '已设置' : '未设置'));

            if (!$account->isActive()) {
                $this->log("公众号未激活，跳过API测试", 'WARNING');
                return;
            }

            if (!$account->getAppId() || !$account->getAppSecret()) {
                $this->log("AppId或AppSecret未设置，跳过API测试", 'WARNING');
                return;
            }

            // 测试获取access_token
            $this->log("测试获取access_token...");
            $accessToken = $this->apiService->getAccessToken($account);

            if ($accessToken) {
                $this->log("获取access_token: ✓ 成功");
                $this->log("Token长度: " . strlen($accessToken));

                // 测试获取文章列表
                if ($this->debugLevel >= 2) {
                    $this->log("测试获取已发布文章...");
                    $articles = $this->apiService->getAllPublishedArticles($accessToken, 1, 0);

                    if ($articles) {
                        $this->log("获取文章列表: ✓ 成功，数量: " . count($articles));

                        if ($this->debugLevel >= 3 && !empty($articles)) {
                            $firstArticle = $articles[0];
                            $this->log("第一篇文章示例: " . json_encode([
                                'article_id' => $firstArticle['article_id'] ?? 'N/A',
                                'title' => $firstArticle['title'] ?? 'N/A',
                                'publish_time' => $firstArticle['publish_time'] ?? 'N/A'
                            ], JSON_UNESCAPED_UNICODE));
                        }
                    } else {
                        $this->log("获取文章列表: ✗ 失败或无数据", 'WARNING');
                    }
                }
            } else {
                $this->log("获取access_token: ✗ 失败", 'ERROR');
            }

        } catch (\Exception $e) {
            $this->log("微信API检查失败: " . $e->getMessage(), 'ERROR');
        }
    }

    private function checkDistributedLocks(): void
    {
        $this->log("\n=== 4. 分布式锁检查 ===");

        try {
            $connection = $this->entityManager->getConnection();

            // 检查锁表状态
            if ($this->tableExists('distributed_locks')) {
                $activeLocks = $connection->fetchAllAssociative(
                    'SELECT lock_key, lock_id, expire_time, created_at FROM distributed_locks WHERE expire_time > NOW()'
                );

                $this->log("活跃锁数量: " . count($activeLocks));

                foreach ($activeLocks as $lock) {
                    $this->log("活跃锁: {$lock['lock_key']}, 到期时间: {$lock['expire_time']}");
                }

                // 测试锁获取
                if ($this->debugLevel >= 2) {
                    $testLockKey = 'debug_test_' . time();
                    $acquired = $this->lockService->acquireLock($testLockKey, 10);
                    $this->log("测试锁获取 ({$testLockKey}): " . ($acquired ? '✓ 成功' : '✗ 失败'));

                    if ($acquired) {
                        $released = $this->lockService->releaseLock($testLockKey);
                        $this->log("测试锁释放: " . ($released ? '✓ 成功' : '✗ 失败'));
                    }
                }
            } else {
                $this->log("distributed_locks表不存在", 'ERROR');
            }

        } catch (\Exception $e) {
            $this->log("分布式锁检查失败: " . $e->getMessage(), 'ERROR');
        }
    }

    private function testSyncProcess(string $accountId): void
    {
        $this->log("\n=== 5. 数据同步流程测试 ===");

        try {
            $this->log("开始测试同步流程...");

            // 绕过锁进行测试同步
            $result = $this->syncService->syncArticles($accountId, false, true);

            $this->log("同步结果: " . ($result['success'] ? '✓ 成功' : '✗ 失败'));
            $this->log("同步消息: " . ($result['message'] ?? '无消息'));

            if (isset($result['stats'])) {
                $stats = $result['stats'];
                $this->log("统计信息:");
                $this->log("  总数: {$stats['total']}");
                $this->log("  创建: {$stats['created']}");
                $this->log("  更新: {$stats['updated']}");
                $this->log("  跳过: {$stats['skipped']}");
                $this->log("  失败: {$stats['failed']}");
            }

            if (!empty($result['errors'])) {
                $this->log("错误信息:", 'WARNING');
                foreach ($result['errors'] as $error) {
                    $this->log("  - {$error}", 'WARNING');
                }
            }

        } catch (\Exception $e) {
            $this->log("同步流程测试失败: " . $e->getMessage(), 'ERROR');
            $this->log("堆栈跟踪: " . $e->getTraceAsString(), 'DEBUG');
        }
    }

    private function checkDataConsistency(string $accountId = null): void
    {
        $this->log("\n=== 6. 数据一致性检查 ===");

        try {
            $connection = $this->entityManager->getConnection();

            // 检查最近的同步记录
            $recentOfficial = $connection->fetchAllAssociative(
                'SELECT id, article_id, title, create_at, updated_at FROM official ORDER BY create_at DESC LIMIT 10'
            );

            $this->log("最近10条official记录:");
            foreach ($recentOfficial as $record) {
                $this->log("  ID: {$record['id']}, ArticleID: {$record['article_id']}, 标题: {$record['title']}, 创建: {$record['create_at']}");
            }

            // 检查重复的article_id
            $duplicates = $connection->fetchAllAssociative(
                'SELECT article_id, COUNT(*) as count FROM official WHERE article_id IS NOT NULL GROUP BY article_id HAVING count > 1'
            );

            if (!empty($duplicates)) {
                $this->log("发现重复的article_id:", 'WARNING');
                foreach ($duplicates as $dup) {
                    $this->log("  ArticleID: {$dup['article_id']}, 重复次数: {$dup['count']}", 'WARNING');
                }
            } else {
                $this->log("未发现重复的article_id: ✓ 正常");
            }

            // 检查空字段
            $emptyTitles = $connection->fetchOne('SELECT COUNT(*) FROM official WHERE title = "" OR title IS NULL');
            $emptyContent = $connection->fetchOne('SELECT COUNT(*) FROM official WHERE content = "" OR content IS NULL');
            $noArticleId = $connection->fetchOne('SELECT COUNT(*) FROM official WHERE article_id IS NULL OR article_id = ""');

            $this->log("空标题记录数: {$emptyTitles}");
            $this->log("空内容记录数: {$emptyContent}");
            $this->log("无article_id记录数: {$noArticleId}");

        } catch (\Exception $e) {
            $this->log("数据一致性检查失败: " . $e->getMessage(), 'ERROR');
        }
    }

    private function analyzeLogs(): void
    {
        $this->log("\n=== 7. 日志分析 ===");

        $logFiles = [
            __DIR__ . '/../var/log/prod.log',
            __DIR__ . '/../var/log/dev.log',
            __DIR__ . '/../var/log/wechat.log',
            __DIR__ . '/../var/log/database.log',
            __DIR__ . '/../var/log/error.log'
        ];

        foreach ($logFiles as $logFile) {
            if (file_exists($logFile)) {
                $size = filesize($logFile);
                $modified = date('Y-m-d H:i:s', filemtime($logFile));
                $this->log("日志文件 " . basename($logFile) . ": 大小 {$size} bytes, 修改时间 {$modified}");

                // 分析最近的错误
                if ($this->debugLevel >= 2 && strpos($logFile, 'error') !== false) {
                    $this->analyzeRecentErrors($logFile);
                }
            } else {
                $this->log("日志文件 " . basename($logFile) . ": 不存在");
            }
        }
    }

    private function analyzeRecentErrors(string $logFile): void
    {
        try {
            $lines = file($logFile);
            $recentErrors = array_slice($lines, -20); // 最近20行

            foreach ($recentErrors as $line) {
                if (strpos($line, 'ERROR') !== false || strpos($line, 'Exception') !== false) {
                    $this->log("最近错误: " . trim($line), 'ERROR');
                }
            }
        } catch (\Exception $e) {
            $this->log("分析错误日志失败: " . $e->getMessage(), 'ERROR');
        }
    }

    private function generateReport(): void
    {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'debug_level' => $this->debugLevel,
            'summary' => [
                'total_checks' => count($this->debugLog),
                'errors' => count(array_filter($this->debugLog, fn($log) => $log['level'] === 'ERROR')),
                'warnings' => count(array_filter($this->debugLog, fn($log) => $log['level'] === 'WARNING')),
            ],
            'recommendations' => $this->generateRecommendations()
        ];

        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // 保存报告到文件
        $reportFile = __DIR__ . '/sync_debug_report_' . date('Ymd_His') . '.json';
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "\n\n详细报告已保存到: {$reportFile}\n";
    }

    private function generateRecommendations(): array
    {
        $recommendations = [];

        // 基于检查结果生成建议
        $errors = array_filter($this->debugLog, fn($log) => $log['level'] === 'ERROR');
        $warnings = array_filter($this->debugLog, fn($log) => $log['level'] === 'WARNING');

        if (!empty($errors)) {
            $recommendations[] = "发现错误，请优先解决ERROR级别的问题";
        }

        if (!empty($warnings)) {
            $recommendations[] = "发现警告，建议检查WARNING级别的问题";
        }

        $recommendations[] = "定期检查日志文件大小，避免日志文件过大";
        $recommendations[] = "确保分布式锁表正常工作，避免并发同步问题";
        $recommendations[] = "验证微信API配置，确保AppId和AppSecret正确";
        $recommendations[] = "定期清理过期的分布式锁记录";

        return $recommendations;
    }

    private function tableExists(string $tableName): bool
    {
        try {
            $connection = $this->entityManager->getConnection();
            $schemaManager = $connection->createSchemaManager();
            return $schemaManager->tablesExist([$tableName]);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkTableStructure(string $tableName): void
    {
        try {
            $connection = $this->entityManager->getConnection();
            $columns = $connection->fetchAllAssociative("DESCRIBE {$tableName}");

            $this->log("表 {$tableName} 结构:");
            foreach ($columns as $column) {
                $this->log("  {$column['Field']}: {$column['Type']} {$column['Null']} {$column['Key']}");
            }
        } catch (\Exception $e) {
            $this->log("检查表 {$tableName} 结构失败: " . $e->getMessage(), 'ERROR');
        }
    }

    private function maskSensitiveData(string $data): string
    {
        return preg_replace('/(:\/\/[^:]+:)[^@]+(@)/', '$1***$2', $data);
    }

    private function log(string $message, string $level = 'INFO'): void
    {
        $timestamp = date('H:i:s');
        $logEntry = [
            'time' => $timestamp,
            'level' => $level,
            'message' => $message
        ];

        $this->debugLog[] = $logEntry;

        $levelIcon = ['INFO' => 'ℹ', 'WARNING' => '⚠', 'ERROR' => '❌', 'DEBUG' => '🐛'][$level] ?? 'ℹ';
        echo "[{$timestamp}] {$levelIcon} {$message}\n";
    }
}

// 主执行逻辑
$accountId = $argv[1] ?? null;
$debugLevel = (int)($argv[2] ?? 2);

try {
    $debugger = new ComprehensiveSyncDebug();
    $debugger->run($accountId, $debugLevel);
} catch (\Exception $e) {
    echo "调试脚本执行失败: " . $e->getMessage() . "\n";
    echo "堆栈跟踪: " . $e->getTraceAsString() . "\n";
    exit(1);
}
