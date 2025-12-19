<?php

echo "=== 修复剩余的 update_at 字段问题 (简化版) ===\n\n";

// 数据库配置（从 .env 文件获取）
$dbConfig = [
    'host' => '127.0.0.1',
    'port' => '3306',
    'dbname' => 'official_website',
    'user' => 'root',
    'password' => 'qwe147258..'
];

try {
    // 创建数据库连接
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    echo "1. 数据库连接成功\n\n";

    // 2. 修复 article_read_statistics 表
    echo "2. 修复 article_read_statistics 表...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM article_read_statistics LIKE 'update_at'");
    $result = $stmt->fetchAll();

    if (!empty($result)) {
        echo "   发现 update_at 字段，开始修复...\n";

        // 检查是否已有 updated_at 字段
        $stmt = $pdo->query("SHOW COLUMNS FROM article_read_statistics LIKE 'updated_at'");
        $hasUpdatedField = $stmt->fetchAll();

        if (empty($hasUpdatedField)) {
            // 添加 updated_at 字段
            $pdo->exec("ALTER TABLE article_read_statistics ADD COLUMN updated_at DATETIME NULL COMMENT '更新时间'");
            echo "   添加 updated_at 字段成功\n";
        } else {
            echo "   updated_at 字段已存在\n";
        }

        // 复制数据
        $pdo->exec("UPDATE article_read_statistics SET updated_at = update_at WHERE update_at IS NOT NULL");
        echo "   数据复制完成\n";

        // 删除 update_at 字段
        $pdo->exec("ALTER TABLE article_read_statistics DROP COLUMN update_at");
        echo "   删除 update_at 字段成功\n";
    } else {
        echo "   未发现 update_at 字段\n";
    }

    // 3. 修复 sys_news_article 表
    echo "\n3. 修复 sys_news_article 表...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM sys_news_article LIKE 'update_at'");
    $result = $stmt->fetchAll();

    if (!empty($result)) {
        echo "   发现 update_at 字段，开始修复...\n";

        // 检查是否已有 updated_at 字段
        $stmt = $pdo->query("SHOW COLUMNS FROM sys_news_article LIKE 'updated_at'");
        $hasUpdatedField = $stmt->fetchAll();

        if (empty($hasUpdatedField)) {
            // 添加 updated_at 字段
            $pdo->exec("ALTER TABLE sys_news_article ADD COLUMN updated_at DATETIME NULL COMMENT '更新时间'");
            echo "   添加 updated_at 字段成功\n";
        } else {
            echo "   updated_at 字段已存在\n";
        }

        // 复制数据
        $pdo->exec("UPDATE sys_news_article SET updated_at = update_at WHERE update_at IS NOT NULL");
        echo "   数据复制完成\n";

        // 删除 update_at 字段
        $pdo->exec("ALTER TABLE sys_news_article DROP COLUMN update_at");
        echo "   删除 update_at 字段成功\n";
    } else {
        echo "   未发现 update_at 字段\n";
    }

    // 4. 验证修复结果
    echo "\n4. 验证修复结果...\n";
    $tables = ['article_read_statistics', 'sys_news_article', 'article_read_logs'];

    foreach ($tables as $table) {
        echo "   表: $table\n";

        // 检查 update_at 字段
        $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE 'update_at'");
        $hasUpdateField = $stmt->fetchAll();

        // 检查 updated_at 字段
        $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE 'updated_at'");
        $hasUpdatedField = $stmt->fetchAll();

        if (empty($hasUpdateField) && !empty($hasUpdatedField)) {
            echo "     ✓ 修复成功：无 update_at，有 updated_at\n";
        } elseif (!empty($hasUpdateField)) {
            echo "     ✗ 仍有 update_at 字段\n";
        } elseif (empty($hasUpdatedField)) {
            echo "     ✗ 缺少 updated_at 字段\n";
        } else {
            echo "     ✓ 状态正常\n";
        }
    }

    echo "\n=== 数据库表修复完成 ===\n";

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
