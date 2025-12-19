<?php
/**
 * 检查目标表的结构
 */

echo "=== 检查目标表结构 ===\n\n";

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

// 需要检查的表
$targetTables = [
    'sys_news_article',
    'article_read_logs',
    'article_read_statistics'
];

foreach ($targetTables as $table) {
    echo "\n=== 检查表: $table ===\n";

    // 检查表是否存在
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;

        if (!$exists) {
            echo "✗ 表 $table 不存在\n";
            continue;
        }

        echo "✓ 表 $table 存在\n";

        // 获取表结构
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "字段列表:\n";
        foreach ($columns as $column) {
            echo "  - {$column['Field']} ({$column['Type']}) " .
                 ($column['Null'] === 'NO' ? 'NOT NULL' : 'NULL') .
                 (empty($column['Default']) ? '' : " DEFAULT {$column['Default']}") . "\n";
        }

        // 检查特定的字段
        $fieldNames = array_column($columns, 'Field');

        echo "\n字段检查:\n";

        // 检查正确的字段名
        if (in_array('updated_at', $fieldNames)) {
            echo "✓ 包含正确的 updated_at 字段\n";
        } else {
            echo "✗ 缺少 updated_at 字段\n";
        }

        if (in_array('create_at', $fieldNames)) {
            echo "✓ 包含正确的 create_at 字段\n";
        } else {
            echo "✗ 缺少 create_at 字段\n";
        }

        // 检查错误的字段名
        if (in_array('update_at', $fieldNames)) {
            echo "✗ 仍然存在错误的 update_at 字段\n";
        } else {
            echo "✓ 没有错误的 update_at 字段\n";
        }

        // 检查其他可能的字段名变体
        $variants = ['created_at', 'createdAt', 'updatedAt'];
        foreach ($variants as $variant) {
            if (in_array($variant, $fieldNames)) {
                echo "! 发现字段名变体: $variant\n";
            }
        }

    } catch (Exception $e) {
        echo "✗ 检查表 $table 时出错: " . $e->getMessage() . "\n";
    }
}

echo "\n=== 检查完成 ===\n";
