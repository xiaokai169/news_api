<?php

/**
 * 数据库验证脚本
 * 用于验证数据库连接、表结构和数据完整性
 */

class DatabaseValidation
{
    private $pdo;
    private $testResults = [];
    private $verbose = false;

    public function __construct($verbose = false)
    {
        $this->verbose = $verbose;
        $this->connect();
    }

    /**
     * 连接数据库
     */
    private function connect()
    {
        try {
            // 从.env文件读取数据库配置
            $envFile = __DIR__ . '/../.env';
            if (!file_exists($envFile)) {
                throw new Exception('.env文件不存在');
            }

            $envContent = file_get_contents($envFile);
            preg_match('/DATABASE_URL="([^"]+)"/', $envContent, $matches);

            if (!$matches[1]) {
                throw new Exception('DATABASE_URL未找到');
            }

            $dbUrl = $matches[1];
            $parsedUrl = parse_url($dbUrl);

            $host = $parsedUrl['host'] ?? 'localhost';
            $port = $parsedUrl['port'] ?? 3306;
            $dbname = ltrim($parsedUrl['path'], '/');
            $username = $parsedUrl['user'] ?? 'root';
            $password = $parsedUrl['pass'] ?? '';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);

            $this->logTest('数据库连接', true, '连接成功');
        } catch (Exception $e) {
            $this->logTest('数据库连接', false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * 记录测试结果
     */
    private function logTest($testName, $passed, $details = '')
    {
        $status = $passed ? 'PASS' : 'FAIL';
        $this->testResults[] = [
            'test' => $testName,
            'status' => $status,
            'details' => $details
        ];

        if ($this->verbose) {
            echo sprintf("[%s] %s: %s\n", $status, $testName, $details);
        } else {
            echo sprintf("%s %s\n", $passed ? '✓' : '✗', $testName);
        }
    }

    /**
     * 验证表结构
     */
    public function validateTableStructure()
    {
        $requiredTables = [
            'sys_news_article_category' => [
                'id' => 'INT',
                'code' => 'VARCHAR',
                'name' => 'VARCHAR',
                'created_at' => 'DATETIME',
                'updated_at' => 'DATETIME'
            ],
            'sys_news_article' => [
                'id' => 'INT',
                'title' => 'VARCHAR',
                'content' => 'TEXT',
                'category_id' => 'INT',
                'created_at' => 'DATETIME',
                'updated_at' => 'DATETIME'
            ]
        ];

        foreach ($requiredTables as $tableName => $requiredColumns) {
            $this->validateTable($tableName, $requiredColumns);
        }
    }

    /**
     * 验证单个表
     */
    private function validateTable($tableName, $requiredColumns)
    {
        try {
            // 检查表是否存在
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$tableName]);

            if ($stmt->rowCount() === 0) {
                $this->logTest("表 {$tableName}", false, '表不存在');
                return false;
            }

            // 检查列结构
            $stmt = $this->pdo->prepare("DESCRIBE {$tableName}");
            $stmt->execute();
            $columns = $stmt->fetchAll();

            $columnNames = array_column($columns, 'Field');
            $missingColumns = array_diff(array_keys($requiredColumns), $columnNames);

            if (!empty($missingColumns)) {
                $this->logTest("表 {$tableName}", false, '缺少列: ' . implode(', ', $missingColumns));
                return false;
            }

            $this->logTest("表 {$tableName}", true, '结构正确');
            return true;

        } catch (Exception $e) {
            $this->logTest("表 {$tableName}", false, $e->getMessage());
            return false;
        }
    }

    /**
     * 验证数据完整性
     */
    public function validateDataIntegrity()
    {
        $this->validateCategoryData();
        $this->validateArticleData();
    }

    /**
     * 验证分类数据
     */
    private function validateCategoryData()
    {
        try {
            // 检查是否有数据
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM sys_news_article_category");
            $count = $stmt->fetch()['count'];

            $this->logTest('分类数据存在性', true, "共{$count}条记录");

            if ($count > 0) {
                // 检查数据完整性
                $stmt = $this->pdo->query("
                    SELECT
                        COUNT(*) as total,
                        COUNT(CASE WHEN code IS NULL OR code = '' THEN 1 END) as missing_code,
                        COUNT(CASE WHEN name IS NULL OR name = '' THEN 1 END) as missing_name
                    FROM sys_news_article_category
                ");
                $stats = $stmt->fetch();

                if ($stats['missing_code'] > 0) {
                    $this->logTest('分类数据完整性', false, "有{$stats['missing_code']}条记录缺少code");
                } else {
                    $this->logTest('分类数据完整性', true, '所有记录都有code');
                }

                if ($stats['missing_name'] > 0) {
                    $this->logTest('分类数据完整性', false, "有{$stats['missing_name']}条记录缺少name");
                } else {
                    $this->logTest('分类数据完整性', true, '所有记录都有name');
                }

                // 检查重复的code
                $stmt = $this->pdo->query("
                    SELECT code, COUNT(*) as cnt
                    FROM sys_news_article_category
                    GROUP BY code
                    HAVING cnt > 1
                ");
                $duplicates = $stmt->fetchAll();

                if (count($duplicates) > 0) {
                    $this->logTest('分类code唯一性', false, '发现重复的code');
                } else {
                    $this->logTest('分类code唯一性', true, '所有code都是唯一的');
                }
            }

        } catch (Exception $e) {
            $this->logTest('分类数据验证', false, $e->getMessage());
        }
    }

    /**
     * 验证文章数据
     */
    private function validateArticleData()
    {
        try {
            // 检查是否有数据
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM sys_news_article");
            $count = $stmt->fetch()['count'];

            $this->logTest('文章数据存在性', true, "共{$count}条记录");

            if ($count > 0) {
                // 检查外键关系
                $stmt = $this->pdo->query("
                    SELECT COUNT(*) as invalid_count
                    FROM sys_news_article a
                    LEFT JOIN sys_news_article_category c ON a.category_id = c.id
                    WHERE c.id IS NULL
                ");
                $invalidCount = $stmt->fetch()['invalid_count'];

                if ($invalidCount > 0) {
                    $this->logTest('文章外键关系', false, "有{$invalidCount}条记录的分类ID无效");
                } else {
                    $this->logTest('文章外键关系', true, '所有记录的分类ID都有效');
                }
            }

        } catch (Exception $e) {
            $this->logTest('文章数据验证', false, $e->getMessage());
        }
    }

    /**
     * 测试数据库操作
     */
    public function testDatabaseOperations()
    {
        $this->testInsertOperation();
        $this->testUpdateOperation();
        $this->testDeleteOperation();
    }

    /**
     * 测试插入操作
     */
    private function testInsertOperation()
    {
        try {
            $testCode = 'TEST_' . time();
            $testName = '测试分类_' . date('Y-m-d H:i:s');

            $stmt = $this->pdo->prepare("
                INSERT INTO sys_news_article_category (code, name, created_at, updated_at)
                VALUES (?, ?, NOW(), NOW())
            ");
            $result = $stmt->execute([$testCode, $testName]);

            if ($result) {
                $insertId = $this->pdo->lastInsertId();
                $this->logTest('插入操作', true, "插入成功，ID: {$insertId}");

                // 验证插入的数据
                $stmt = $this->pdo->prepare("SELECT * FROM sys_news_article_category WHERE id = ?");
                $stmt->execute([$insertId]);
                $insertedData = $stmt->fetch();

                if ($insertedData && $insertedData['code'] === $testCode) {
                    $this->logTest('插入数据验证', true, '数据正确插入');
                } else {
                    $this->logTest('插入数据验证', false, '插入的数据不匹配');
                }

                return $insertId;
            } else {
                $this->logTest('插入操作', false, '插入失败');
                return null;
            }

        } catch (Exception $e) {
            $this->logTest('插入操作', false, $e->getMessage());
            return null;
        }
    }

    /**
     * 测试更新操作
     */
    private function testUpdateOperation()
    {
        try {
            // 获取一条记录进行更新测试
            $stmt = $this->pdo->query("SELECT id FROM sys_news_article_category LIMIT 1");
            $record = $stmt->fetch();

            if (!$record) {
                $this->logTest('更新操作', false, '没有可更新的记录');
                return;
            }

            $id = $record['id'];
            $newName = '更新后的名称_' . time();

            $stmt = $this->pdo->prepare("
                UPDATE sys_news_article_category
                SET name = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([$newName, $id]);

            if ($result) {
                // 验证更新
                $stmt = $this->pdo->prepare("SELECT name FROM sys_news_article_category WHERE id = ?");
                $stmt->execute([$id]);
                $updatedRecord = $stmt->fetch();

                if ($updatedRecord && $updatedRecord['name'] === $newName) {
                    $this->logTest('更新操作', true, '更新成功');
                } else {
                    $this->logTest('更新操作', false, '更新验证失败');
                }
            } else {
                $this->logTest('更新操作', false, '更新失败');
            }

        } catch (Exception $e) {
            $this->logTest('更新操作', false, $e->getMessage());
        }
    }

    /**
     * 测试删除操作
     */
    private function testDeleteOperation()
    {
        try {
            // 先插入一条测试记录
            $testCode = 'DELETE_TEST_' . time();
            $stmt = $this->pdo->prepare("
                INSERT INTO sys_news_article_category (code, name, created_at, updated_at)
                VALUES (?, ?, NOW(), NOW())
            ");
            $stmt->execute([$testCode, '删除测试']);

            $insertId = $this->pdo->lastInsertId();

            // 删除刚插入的记录
            $stmt = $this->pdo->prepare("DELETE FROM sys_news_article_category WHERE id = ?");
            $result = $stmt->execute([$insertId]);

            if ($result) {
                // 验证删除
                $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM sys_news_article_category WHERE id = ?");
                $stmt->execute([$insertId]);
                $count = $stmt->fetch()['count'];

                if ($count == 0) {
                    $this->logTest('删除操作', true, '删除成功');
                } else {
                    $this->logTest('删除操作', false, '删除验证失败');
                }
            } else {
                $this->logTest('删除操作', false, '删除失败');
            }

        } catch (Exception $e) {
            $this->logTest('删除操作', false, $e->getMessage());
        }
    }

    /**
     * 测试数据库性能
     */
    public function testPerformance()
    {
        try {
            $startTime = microtime(true);

            // 测试查询性能
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM sys_news_article_category");
            $count = $stmt->fetch()['count'];

            $queryTime = microtime(true) - $startTime;

            if ($queryTime < 0.1) {
                $this->logTest('查询性能', true, "查询时间: " . round($queryTime * 1000, 2) . "ms");
            } else {
                $this->logTest('查询性能', false, "查询时间过长: " . round($queryTime * 1000, 2) . "ms");
            }

            // 测试索引使用情况
            $stmt = $this->pdo->query("
                SHOW INDEX FROM sys_news_article_category WHERE Column_name = 'code'
            ");
            $indexes = $stmt->fetchAll();

            if (count($indexes) > 0) {
                $this->logTest('code字段索引', true, '索引存在');
            } else {
                $this->logTest('code字段索引', false, '建议为code字段添加索引');
            }

        } catch (Exception $e) {
            $this->logTest('性能测试', false, $e->getMessage());
        }
    }

    /**
     * 运行完整的数据库验证
     */
    public function runFullValidation()
    {
        echo "开始数据库完整验证...\n\n";

        $startTime = microtime(true);

        $this->validateTableStructure();
        $this->validateDataIntegrity();
        $this->testDatabaseOperations();
        $this->testPerformance();

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        $this->printValidationSummary($duration);
    }

    /**
     * 打印验证总结
     */
    private function printValidationSummary($duration)
    {
        $total = count($this->testResults);
        $passed = count(array_filter($this->testResults, fn($r) => $r['status'] === 'PASS'));
        $failed = $total - $passed;

        echo "\n" . str_repeat("=", 50) . "\n";
        echo "数据库验证总结\n";
        echo str_repeat("=", 50) . "\n";
        echo "总验证数: {$total}\n";
        echo "通过: {$passed}\n";
        echo "失败: {$failed}\n";
        echo "耗时: {$duration}秒\n";
        echo "成功率: " . round(($passed / $total) * 100, 2) . "%\n";

        if ($failed > 0) {
            echo "\n失败的验证:\n";
            foreach ($this->testResults as $result) {
                if ($result['status'] === 'FAIL') {
                    echo "  ✗ {$result['test']}: {$result['details']}\n";
                }
            }
        }

        echo "\n";
    }

    /**
     * 获取验证结果
     */
    public function getValidationResults()
    {
        return $this->testResults;
    }
}

// 命令行执行
if (php_sapi_name() === 'cli') {
    $options = getopt('v', ['verbose']);
    $verbose = isset($options['v']) || isset($options['verbose']);

    try {
        $validation = new DatabaseValidation($verbose);
        $validation->runFullValidation();
    } catch (Exception $e) {
        echo "数据库验证失败: " . $e->getMessage() . "\n";
        exit(1);
    }
}
