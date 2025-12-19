<?php
/**
 * 调试新闻API接口的数据库字段问题
 */

echo "=== 调试新闻API接口字段问题 ===\n\n";

// 数据库配置
$dbConfig = [
    'host' => '127.0.0.1',
    'port' => '3306',
    'dbname' => 'official_website',
    'username' => 'root',
    'password' => 'qwe147258..',
    'charset' => 'utf8mb4'
];

try {
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

// 检查 sys_news_article 表
echo "\n=== 检查 sys_news_article 表 ===\n";

try {
    $stmt = $pdo->query("DESCRIBE sys_news_article");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "所有字段:\n";
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']})\n";
    }

    // 检查时间字段
    $timeFields = [];
    foreach ($columns as $column) {
        if (strpos($column['Field'], 'time') !== false ||
            strpos($column['Field'], 'create') !== false ||
            strpos($column['Field'], 'update') !== false) {
            $timeFields[] = $column['Field'];
        }
    }

    echo "\n时间相关字段:\n";
    foreach ($timeFields as $field) {
        echo "  - $field\n";
    }

    // 特别检查 update_at 字段
    $hasUpdateAt = false;
    $hasUpdatedAt = false;
    $hasUpdateTime = false;

    foreach ($columns as $column) {
        if ($column['Field'] === 'update_at') {
            $hasUpdateAt = true;
        }
        if ($column['Field'] === 'updated_at') {
            $hasUpdatedAt = true;
        }
        if ($column['Field'] === 'update_time') {
            $hasUpdateTime = true;
        }
    }

    echo "\n字段检查结果:\n";
    echo "  - update_at: " . ($hasUpdateAt ? "✓ 存在" : "✗ 不存在") . "\n";
    echo "  - updated_at: " . ($hasUpdatedAt ? "✓ 存在" : "✗ 不存在") . "\n";
    echo "  - update_time: " . ($hasUpdateTime ? "✓ 存在" : "✗ 不存在") . "\n";

    if ($hasUpdateAt) {
        echo "  ⚠ 发现问题：存在 update_at 字段，这可能是错误的原因！\n";
    }

} catch (Exception $e) {
    echo "✗ 检查 sys_news_article 表失败: " . $e->getMessage() . "\n";
}

// 检查其他相关表
$relatedTables = ['article_read_logs', 'article_read_statistics', 'sys_news_article_category'];

foreach ($relatedTables as $table) {
    echo "\n=== 检查 $table 表 ===\n";

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() === 0) {
            echo "✗ 表不存在\n";
            continue;
        }

        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 检查 update 相关字段
        $updateFields = [];
        foreach ($columns as $column) {
            if (strpos($column['Field'], 'update') !== false) {
                $updateFields[] = $column['Field'];
            }
        }

        if (!empty($updateFields)) {
            echo "update 相关字段:\n";
            foreach ($updateFields as $field) {
                echo "  - $field\n";
                if ($field === 'update_at') {
                    echo "    ⚠ 发现错误的 update_at 字段！\n";
                }
            }
        } else {
            echo "没有 update 相关字段\n";
        }

    } catch (Exception $e) {
        echo "✗ 检查失败: " . $e->getMessage() . "\n";
    }
}

// 测试一个简单的查询来复现错误
echo "\n=== 测试查询操作 ===\n";

try {
    $stmt = $pdo->query("SELECT * FROM sys_news_article LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✓ 简单查询成功\n";
} catch (Exception $e) {
    echo "✗ 简单查询失败: " . $e->getMessage() . "\n";
    if (strpos($e->getMessage(), 'update_at') !== false) {
        echo "  🔍 确认发现了 update_at 字段错误！\n";
    }
}

// 测试带别名的查询（模拟 Doctrine 查询）
echo "\n=== 测试带别名的查询 ===\n";

try {
    $stmt = $pdo->query("SELECT s0_.* FROM sys_news_article s0_ LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✓ 带别名查询成功\n";
} catch (Exception $e) {
    echo "✗ 带别名查询失败: " . $e->getMessage() . "\n";
    if (strpos($e->getMessage(), 's0_.update_at') !== false) {
        echo "  🔍 确认发现了 s0_.update_at 字段错误！\n";
    }
}

echo "\n=== 检查完成 ===\n";
