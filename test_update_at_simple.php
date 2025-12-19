<?php
/**
 * update_at 字段修复验证 - 简化版本
 * 直接验证关键功能，避免复杂的Symfony环境依赖
 */

echo "=== update_at 字段修复验证测试 ===\n\n";

// 数据库配置 - 从.env文件获取
$dbConfig = [
    'host' => '127.0.0.1',
    'port' => '3306',
    'dbname' => 'official_website',
    'username' => 'root',
    'password' => 'qwe147258..',
    'charset' => 'utf8mb4'
];

try {
    // 连接数据库
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ 数据库连接成功\n";
} catch (Exception $e) {
    echo "✗ 数据库连接失败: " . $e->getMessage() . "\n";
    exit(1);
}

$testResults = [];
$testStartTime = microtime(true);

// 测试1: 验证表结构
echo "\n=== 测试1: 验证表结构 ===\n";

$tables = [
    'sys_news_article' => ['updated_at', 'create_at'],
    'article_read_logs' => ['updated_at', 'create_at'],
    'article_read_statistics' => ['updated_at', 'create_at']
];

foreach ($tables as $table => $expectedFields) {
    try {
        $stmt = $pdo->query("DESCRIBE {$table}");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $allFieldsExist = true;
        foreach ($expectedFields as $field) {
            if (!in_array($field, $columns)) {
                echo "✗ 表 {$table} 缺少字段 {$field}\n";
                $allFieldsExist = false;
            }
        }

        // 检查是否还有 update_at 字段（不应该存在）
        if (in_array('update_at', $columns)) {
            echo "✗ 表 {$table} 仍然存在错误的 update_at 字段\n";
            $allFieldsExist = false;
        }

        if ($allFieldsExist) {
            echo "✓ 表 {$table} 结构正确\n";
            $testResults['table_structure_' . $table] = true;
        } else {
            $testResults['table_structure_' . $table] = false;
        }
    } catch (Exception $e) {
        echo "✗ 检查表 {$table} 结构失败: " . $e->getMessage() . "\n";
        $testResults['table_structure_' . $table] = false;
    }
}

// 测试2: 验证查询操作
echo "\n=== 测试2: 验证查询操作 ===\n";

$queries = [
    'sys_news_article' => "SELECT id, name, updated_at, create_at FROM sys_news_article LIMIT 5",
    'article_read_logs' => "SELECT id, article_id, updated_at, create_at FROM article_read_logs LIMIT 5",
    'article_read_statistics' => "SELECT id, article_id, updated_at, create_at FROM article_read_statistics LIMIT 5"
];

foreach ($queries as $table => $sql) {
    try {
        $stmt = $pdo->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "✓ 表 {$table} 查询成功，返回 " . count($results) . " 条记录\n";
        $testResults['query_' . $table] = true;
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'update_at') !== false) {
            echo "✗ 表 {$table} 查询失败，仍存在 update_at 字段错误: " . $e->getMessage() . "\n";
        } else {
            echo "✗ 表 {$table} 查询失败: " . $e->getMessage() . "\n";
        }
        $testResults['query_' . $table] = false;
    }
}

// 测试3: 验证复杂查询
echo "\n=== 测试3: 验证复杂查询 ===\n";

try {
    $complexSql = "
        SELECT
            a.id,
            a.name,
            a.updated_at,
            a.create_at,
            COUNT(l.id) as read_count
        FROM sys_news_article a
        LEFT JOIN article_read_logs l ON a.id = l.article_id
        WHERE a.status = 1
        GROUP BY a.id, a.name, a.updated_at, a.create_at
        LIMIT 5
    ";

    $stmt = $pdo->query($complexSql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✓ 复杂关联查询成功，返回 " . count($results) . " 条记录\n";
    $testResults['complex_query'] = true;
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'update_at') !== false) {
        echo "✗ 复杂查询失败，仍存在 update_at 字段错误: " . $e->getMessage() . "\n";
    } else {
        echo "✗ 复杂查询失败: " . $e->getMessage() . "\n";
    }
    $testResults['complex_query'] = false;
}

// 测试4: 验证插入操作
echo "\n=== 测试4: 验证插入操作 ===\n";

try {
    // 插入测试文章
    $insertSql = "
        INSERT INTO sys_news_article (
            merchant_id, user_id, name, cover, content, status,
            category_id, updated_at, create_at
        ) VALUES (
            1, 1, '测试文章', 'test.jpg', '测试内容', 1,
            1, NOW(), NOW()
        )
    ";

    $pdo->exec($insertSql);
    $articleId = $pdo->lastInsertId();
    echo "✓ 文章插入成功，ID: {$articleId}\n";

    // 验证插入的数据
    $selectSql = "SELECT id, name, updated_at, create_at FROM sys_news_article WHERE id = ?";
    $stmt = $pdo->prepare($selectSql);
    $stmt->execute([$articleId]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($article && isset($article['updated_at']) && isset($article['create_at'])) {
        echo "✓ 插入数据的时间字段正确\n";
        $testResults['insert_operation'] = true;
    } else {
        echo "✗ 插入数据的时间字段有问题\n";
        $testResults['insert_operation'] = false;
    }

    // 清理测试数据
    $pdo->exec("DELETE FROM sys_news_article WHERE id = {$articleId}");
    echo "✓ 测试数据清理完成\n";

} catch (Exception $e) {
    echo "✗ 插入操作失败: " . $e->getMessage() . "\n";
    $testResults['insert_operation'] = false;
}

// 测试5: 验证更新操作
echo "\n=== 测试5: 验证更新操作 ===\n";

try {
    // 先插入一条记录
    $insertSql = "
        INSERT INTO article_read_logs (
            article_id, user_id, ip_address, device_type,
            updated_at, create_at
        ) VALUES (
            1, 1, '127.0.0.1', 'desktop',
            NOW(), NOW()
        )
    ";

    $pdo->exec($insertSql);
    $logId = $pdo->lastInsertId();

    // 获取原始更新时间
    $stmt = $pdo->prepare("SELECT updated_at FROM article_read_logs WHERE id = ?");
    $stmt->execute([$logId]);
    $originalUpdatedAt = $stmt->fetchColumn();

    // 等待1秒确保时间差异
    sleep(1);

    // 更新记录
    $updateSql = "
        UPDATE article_read_logs
        SET duration_seconds = 120, updated_at = NOW()
        WHERE id = ?
    ";
    $pdo->prepare($updateSql)->execute([$logId]);

    // 验证更新时间
    $stmt = $pdo->prepare("SELECT updated_at FROM article_read_logs WHERE id = ?");
    $stmt->execute([$logId]);
    $newUpdatedAt = $stmt->fetchColumn();

    if ($newUpdatedAt > $originalUpdatedAt) {
        echo "✓ 更新操作成功，updated_at 时间正确更新\n";
        $testResults['update_operation'] = true;
    } else {
        echo "✗ 更新操作失败，updated_at 时间未正确更新\n";
        $testResults['update_operation'] = false;
    }

    // 清理测试数据
    $pdo->exec("DELETE FROM article_read_logs WHERE id = {$logId}");
    echo "✓ 测试数据清理完成\n";

} catch (Exception $e) {
    echo "✗ 更新操作失败: " . $e->getMessage() . "\n";
    $testResults['update_operation'] = false;
}

// 测试6: 验证字段命名一致性
echo "\n=== 测试6: 验证字段命名一致性 ===\n";

$fieldConsistency = true;
foreach ($tables as $table => $expectedFields) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM {$table}");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $column) {
            $fieldName = $column['Field'];

            // 检查是否还有错误的字段名
            if ($fieldName === 'update_at') {
                echo "✗ 表 {$table} 仍存在错误的字段名 'update_at'\n";
                $fieldConsistency = false;
            }

            // 检查字段名是否符合预期
            if (in_array($fieldName, $expectedFields)) {
                echo "✓ 表 {$table} 字段 {$fieldName} 命名正确\n";
            }
        }
    } catch (Exception $e) {
        echo "✗ 检查表 {$table} 字段命名失败: " . $e->getMessage() . "\n";
        $fieldConsistency = false;
    }
}

if ($fieldConsistency) {
    $testResults['field_consistency'] = true;
} else {
    $testResults['field_consistency'] = false;
}

// 生成测试报告
$totalTime = microtime(true) - $testStartTime;

echo "\n=== 测试报告 ===\n";
echo "总耗时: " . number_format($totalTime, 3) . " 秒\n\n";

// 测试结果统计
$totalTests = count($testResults);
$passedTests = count(array_filter($testResults));
$failedTests = $totalTests - $passedTests;

echo "测试统计:\n";
echo "  总测试数: {$totalTests}\n";
echo "  通过: {$passedTests}\n";
echo "  失败: {$failedTests}\n";
echo "  成功率: " . number_format(($passedTests / $totalTests) * 100, 1) . "%\n\n";

// 详细测试结果
echo "详细测试结果:\n";
foreach ($testResults as $test => $result) {
    $status = $result ? '✓ 通过' : '✗ 失败';
    echo "  {$test}: {$status}\n";
}
echo "\n";

// 修复效果确认
echo "=== 修复效果确认 ===\n";

if ($testResults['query_sys_news_article'] && $testResults['query_article_read_logs'] && $testResults['query_article_read_statistics']) {
    echo "✓ 数据库查询操作已修复，不再出现 'update_at' 字段错误\n";
} else {
    echo "✗ 数据库查询操作仍存在问题\n";
}

if ($testResults['field_consistency']) {
    echo "✓ 字段命名一致性检查通过\n";
} else {
    echo "✗ 字段命名一致性检查失败\n";
}

if ($testResults['insert_operation'] && $testResults['update_operation']) {
    echo "✓ 数据库操作（插入、更新）正常\n";
} else {
    echo "✗ 数据库操作存在问题\n";
}

echo "\n";

// 结论
if ($failedTests === 0) {
    echo "🎉 所有测试通过！update_at 字段错误已完全修复，系统功能正常。\n";
} else {
    echo "⚠️  仍有 {$failedTests} 个测试失败，需要进一步检查和修复。\n";
}

// 保存报告到文件
$report = [
    'test_time' => date('Y-m-d H:i:s'),
    'total_time' => $totalTime,
    'test_results' => $testResults,
    'summary' => [
        'total_tests' => $totalTests,
        'passed_tests' => $passedTests,
        'failed_tests' => $failedTests,
        'success_rate' => ($passedTests / $totalTests) * 100
    ]
];

$filename = 'update_at_field_test_report_' . date('Y-m-d_H-i-s') . '.json';
file_put_contents($filename, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n📄 详细报告已保存到: {$filename}\n";
