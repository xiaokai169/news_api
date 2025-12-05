<?php

/**
 * 数据库连接和权限验证器
 * 用于检查数据库连接、权限和表结构问题
 *
 * 使用方法:
 * php public/database_connection_validator.php [table_name] [operation]
 * table_name: 要检查的表名 (可选，默认检查所有关键表)
 * operation: 操作类型 (check|test|repair)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class DatabaseConnectionValidator
{
    private Kernel $kernel;
    private EntityManagerInterface $entityManager;
    private Connection $connection;
    private array $results = [];
    private array $criticalTables = [
        'official',
        'wechat_public_account',
        'distributed_locks',
        'sys_news_article_category'
    ];

    public function __construct()
    {
        $this->kernel = new Kernel($_ENV['APP_ENV'] ?? 'dev', (bool)($_ENV['APP_DEBUG'] ?? true));
        $this->kernel->boot();

        $this->entityManager = $this->kernel->getContainer()->get('doctrine.orm.default_entity_manager');
        $this->connection = $this->entityManager->getConnection();
    }

    public function validate(string $tableName = '', string $operation = 'check'): void
    {
        echo "=== 数据库连接和权限验证器 ===\n";
        echo "操作类型: {$operation}\n";
        echo "目标表: " . ($tableName ?: '所有关键表') . "\n";
        echo "开始时间: " . date('Y-m-d H:i:s') . "\n\n";

        // 1. 基础连接检查
        $this->checkBasicConnection();

        // 2. 数据库信息检查
        $this->checkDatabaseInfo();

        // 3. 权限检查
        $this->checkPermissions();

        // 4. 表结构检查
        if ($tableName) {
            $this->checkTableStructure($tableName);
        } else {
            $this->checkAllTables();
        }

        // 5. 数据完整性检查
        $this->checkDataIntegrity();

        // 6. 性能检查
        $this->checkPerformance();

        // 7. 生成报告
        $this->generateReport($operation);
    }

    private function checkBasicConnection(): void
    {
        echo "=== 1. 基础连接检查 ===\n";

        try {
            // 检查连接状态
            $this->connection->connect();
            $this->results['connection']['status'] = 'success';
            $this->results['connection']['message'] = '数据库连接成功';
            echo "✓ 数据库连接成功\n";

            // 检查连接参数
            $params = $this->connection->getParams();
            echo "✓ 数据库: " . ($params['dbname'] ?? 'unknown') . "\n";
            echo "✓ 主机: " . ($params['host'] ?? 'unknown') . "\n";
            echo "✓ 端口: " . ($params['port'] ?? 'default') . "\n";
            echo "✓ 字符集: " . ($params['charset'] ?? 'default') . "\n";

        } catch (Exception $e) {
            $this->results['connection']['status'] = 'error';
            $this->results['connection']['message'] = $e->getMessage();
            echo "✗ 数据库连接失败: " . $e->getMessage() . "\n";
            return;
        }

        echo "\n";
    }

    private function checkDatabaseInfo(): void
    {
        echo "=== 2. 数据库信息检查 ===\n";

        try {
            // 数据库版本
            $version = $this->connection->fetchOne('SELECT VERSION()');
            $this->results['database']['version'] = $version;
            echo "✓ MySQL版本: {$version}\n";

            // 当前数据库
            $currentDb = $this->connection->fetchOne('SELECT DATABASE()');
            $this->results['database']['name'] = $currentDb;
            echo "✓ 当前数据库: {$currentDb}\n";

            // 字符集
            $charset = $this->connection->fetchOne('SELECT DEFAULT_CHARACTER_SET_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$currentDb]);
            $this->results['database']['charset'] = $charset;
            echo "✓ 数据库字符集: {$charset}\n";

            // 排序规则
            $collation = $this->connection->fetchOne('SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$currentDb]);
            $this->results['database']['collation'] = $collation;
            echo "✓ 排序规则: {$collation}\n";

            // 数据库大小
            $size = $this->connection->fetchOne('SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema = ?', [$currentDb]);
            $this->results['database']['size_mb'] = $size;
            echo "✓ 数据库大小: {$size} MB\n";

        } catch (Exception $e) {
            echo "✗ 数据库信息检查失败: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    private function checkPermissions(): void
    {
        echo "=== 3. 权限检查 ===\n";

        $permissions = [
            'SELECT' => 'SELECT 1',
            'INSERT' => 'CREATE TEMPORARY TABLE temp_test (id INT)',
            'UPDATE' => 'CREATE TEMPORARY TABLE temp_test (id INT)',
            'DELETE' => 'CREATE TEMPORARY TABLE temp_test (id INT)',
            'CREATE' => 'CREATE TEMPORARY TABLE temp_test (id INT)',
            'DROP' => 'CREATE TEMPORARY TABLE temp_test (id INT)',
            'INDEX' => 'CREATE TEMPORARY TABLE temp_test (id INT, INDEX idx_id (id))'
        ];

        foreach ($permissions as $perm => $testSql) {
            try {
                $this->connection->executeStatement($testSql);
                $this->results['permissions'][$perm] = 'granted';
                echo "✓ {$perm} 权限: 已授予\n";
            } catch (Exception $e) {
                $this->results['permissions'][$perm] = 'denied';
                echo "✗ {$perm} 权限: 被拒绝 - " . $e->getMessage() . "\n";
            }
        }

        // 清理临时表
        try {
            $this->connection->executeStatement('DROP TEMPORARY TABLE IF EXISTS temp_test');
        } catch (Exception $e) {
            // 忽略清理错误
        }

        echo "\n";
    }

    private function checkAllTables(): void
    {
        echo "=== 4. 关键表结构检查 ===\n";

        foreach ($this->criticalTables as $tableName) {
            $this->checkTableStructure($tableName);
        }
    }

    private function checkTableStructure(string $tableName): void
    {
        echo "--- 检查表: {$tableName} ---\n";

        try {
            // 检查表是否存在
            $schemaManager = $this->connection->createSchemaManager();
            $exists = $schemaManager->tablesExist([$tableName]);

            if (!$exists) {
                echo "✗ 表不存在\n";
                $this->results['tables'][$tableName]['status'] = 'missing';
                return;
            }

            echo "✓ 表存在\n";

            // 获取表结构
            $columns = $this->connection->fetchAllAssociative("DESCRIBE {$tableName}");
            $this->results['tables'][$tableName]['columns'] = $columns;
            echo "✓ 字段数量: " . count($columns) . "\n";

            // 检查关键字段
            $this->checkCriticalColumns($tableName, $columns);

            // 检查索引
            $indexes = $this->connection->fetchAllAssociative("SHOW INDEX FROM {$tableName}");
            $this->results['tables'][$tableName]['indexes'] = $indexes;
            echo "✓ 索引数量: " . count($indexes) . "\n";

            // 检查数据量
            $count = $this->connection->fetchOne("SELECT COUNT(*) FROM {$tableName}");
            $this->results['tables'][$tableName]['row_count'] = $count;
            echo "✓ 记录数量: {$count}\n";

            // 检查表大小
            $size = $this->connection->fetchOne("
                SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2)
                FROM information_schema.TABLES
                WHERE table_schema = DATABASE() AND table_name = ?
            ", [$tableName]);
            $this->results['tables'][$tableName]['size_mb'] = $size;
            echo "✓ 表大小: {$size} MB\n";

            $this->results['tables'][$tableName]['status'] = 'ok';

        } catch (Exception $e) {
            echo "✗ 检查失败: " . $e->getMessage() . "\n";
            $this->results['tables'][$tableName]['status'] = 'error';
            $this->results['tables'][$tableName]['error'] = $e->getMessage();
        }

        echo "\n";
    }

    private function checkCriticalColumns(string $tableName, array $columns): void
    {
        $criticalColumns = [
            'official' => ['id', 'title', 'content', 'article_id'],
            'wechat_public_account' => ['id', 'app_id', 'app_secret'],
            'distributed_locks' => ['lock_key', 'lock_id', 'expire_time'],
            'sys_news_article_category' => ['id', 'code', 'name']
        ];

        if (!isset($criticalColumns[$tableName])) {
            return;
        }

        $columnNames = array_column($columns, 'Field');
        $required = $criticalColumns[$tableName];

        foreach ($required as $requiredColumn) {
            if (in_array($requiredColumn, $columnNames)) {
                echo "✓ 关键字段 {$requiredColumn}: 存在\n";
            } else {
                echo "✗ 关键字段 {$requiredColumn}: 缺失\n";
                $this->results['tables'][$tableName]['missing_columns'][] = $requiredColumn;
            }
        }
    }

    private function checkDataIntegrity(): void
    {
        echo "=== 5. 数据完整性检查 ===\n";

        try {
            // 检查official表的数据完整性
            if (isset($this->results['tables']['official']) && $this->results['tables']['official']['status'] === 'ok') {
                $nullTitles = $this->connection->fetchOne("SELECT COUNT(*) FROM official WHERE title IS NULL OR title = ''");
                $nullContent = $this->connection->fetchOne("SELECT COUNT(*) FROM official WHERE content IS NULL OR content = ''");
                $duplicateArticleIds = $this->connection->fetchOne("
                    SELECT COUNT(*) - COUNT(DISTINCT article_id)
                    FROM official
                    WHERE article_id IS NOT NULL AND article_id != ''
                ");

                echo "✓ official表空标题: {$nullTitles}\n";
                echo "✓ official表空内容: {$nullContent}\n";
                echo "✓ official表重复article_id: {$duplicateArticleIds}\n";

                $this->results['integrity']['official'] = [
                    'null_titles' => $nullTitles,
                    'null_content' => $nullContent,
                    'duplicate_article_ids' => $duplicateArticleIds
                ];
            }

            // 检查分布式锁的过期锁
            if (isset($this->results['tables']['distributed_locks']) && $this->results['tables']['distributed_locks']['status'] === 'ok') {
                $expiredLocks = $this->connection->fetchOne("SELECT COUNT(*) FROM distributed_locks WHERE expire_time < NOW()");
                echo "✓ 过期锁数量: {$expiredLocks}\n";

                $this->results['integrity']['distributed_locks'] = [
                    'expired_locks' => $expiredLocks
                ];
            }

        } catch (Exception $e) {
            echo "✗ 数据完整性检查失败: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    private function checkPerformance(): void
    {
        echo "=== 6. 性能检查 ===\n";

        try {
            // 检查连接数
            $connections = $this->connection->fetchOne("SHOW STATUS LIKE 'Threads_connected'");
            echo "✓ 当前连接数: " . ($connections['Value'] ?? 'N/A') . "\n";

            // 检查慢查询
            $slowQueries = $this->connection->fetchOne("SHOW STATUS LIKE 'Slow_queries'");
            echo "✓ 慢查询数量: " . ($slowQueries['Value'] ?? 'N/A') . "\n";

            // 检查查询缓存
            $queryCache = $this->connection->fetchOne("SHOW STATUS LIKE 'Qcache_hits'");
            echo "✓ 查询缓存命中: " . ($queryCache['Value'] ?? 'N/A') . "\n";

            // 测试简单查询性能
            $startTime = microtime(true);
            $this->connection->fetchAllAssociative("SELECT 1");
            $queryTime = (microtime(true) - $startTime) * 1000;
            echo "✓ 简单查询响应时间: " . number_format($queryTime, 2) . " ms\n";

            $this->results['performance'] = [
                'connections' => $connections['Value'] ?? null,
                'slow_queries' => $slowQueries['Value'] ?? null,
                'query_cache_hits' => $queryCache['Value'] ?? null,
                'simple_query_time_ms' => $queryTime
            ];

        } catch (Exception $e) {
            echo "✗ 性能检查失败: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    private function generateReport(string $operation): void
    {
        echo "=== 验证报告 ===\n";

        $issues = [];
        $recommendations = [];

        // 检查连接问题
        if ($this->results['connection']['status'] !== 'success') {
            $issues[] = "数据库连接失败";
            $recommendations[] = "检查数据库配置和网络连接";
        }

        // 检查权限问题
        foreach ($this->results['permissions'] ?? [] as $perm => $status) {
            if ($status === 'denied') {
                $issues[] = "缺少 {$perm} 权限";
                $recommendations[] = "联系数据库管理员授予相应权限";
            }
        }

        // 检查表问题
        foreach ($this->results['tables'] ?? [] as $table => $info) {
            if ($info['status'] === 'missing') {
                $issues[] = "表 {$table} 不存在";
                $recommendations[] = "运行数据库迁移创建缺失的表";
            } elseif ($info['status'] === 'error') {
                $issues[] = "表 {$table} 检查失败";
                $recommendations[] = "检查表结构或重建表";
            }
        }

        // 检查数据完整性问题
        if (isset($this->results['integrity']['official'])) {
            $integrity = $this->results['integrity']['official'];
            if ($integrity['null_titles'] > 0) {
                $issues[] = "official表存在空标题记录";
                $recommendations[] = "清理或修复空标题记录";
            }
            if ($integrity['null_content'] > 0) {
                $issues[] = "official表存在空内容记录";
                $recommendations[] = "清理或修复空内容记录";
            }
            if ($integrity['duplicate_article_ids'] > 0) {
                $issues[] = "official表存在重复的article_id";
                $recommendations[] = "清理重复的article_id记录";
            }
        }

        // 输出问题和建议
        if (empty($issues)) {
            echo "✅ 未发现严重问题\n";
        } else {
            echo "❌ 发现问题:\n";
            foreach ($issues as $issue) {
                echo "  - {$issue}\n";
            }
        }

        if (!empty($recommendations)) {
            echo "\n💡 建议:\n";
            foreach ($recommendations as $rec) {
                echo "  - {$rec}\n";
            }
        }

        // 保存详细报告
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'operation' => $operation,
            'results' => $this->results,
            'issues' => $issues,
            'recommendations' => $recommendations
        ];

        $reportFile = __DIR__ . '/database_validation_report_' . date('Ymd_His') . '.json';
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "\n详细报告已保存到: {$reportFile}\n";
    }
}

// 主执行逻辑
$tableName = $argv[1] ?? '';
$operation = $argv[2] ?? 'check';

try {
    $validator = new DatabaseConnectionValidator();
    $validator->validate($tableName, $operation);
} catch (\Exception $e) {
    echo "数据库验证失败: " . $e->getMessage() . "\n";
    echo "堆栈跟踪: " . $e->getTraceAsString() . "\n";
    exit(1);
}
