<?php

echo "=== 修复 update_at 字段问题 (改进版) ===\n\n";

// 连接数据库
try {
    $envFile = __DIR__ . '/.env';
    $envContent = file_get_contents($envFile);
    $lines = explode("\n", $envContent);
    $databaseUrl = '';

    foreach ($lines as $line) {
        if (strpos(trim($line), 'DATABASE_URL=') === 0 && strpos(trim($line), '#') !== 0) {
            $databaseUrl = trim(substr(trim($line), strlen('DATABASE_URL=')));
            $databaseUrl = trim($databaseUrl, '"');
            break;
        }
    }

    $urlParts = parse_url($databaseUrl);
    $host = $urlParts['host'] ?? '127.0.0.1';
    $port = $urlParts['port'] ?? '3306';
    $dbname = substr($urlParts['path'], 1) ?? '';
    $username = $urlParts['user'] ?? '';
    $password = $urlParts['pass'] ?? '';

    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "1. 数据库连接成功\n\n";

    // 需要修复的表
    $tablesToFix = [
        'article_read_logs',
        'article_read_statistics',
        'sys_news_article'
    ];

    echo "2. 开始修复表结构...\n";

    foreach ($tablesToFix as $table) {
        echo "   处理表: $table\n";

        // 检查表是否存在
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array($table, $tables)) {
            echo "     - 表不存在，跳过\n";
            continue;
        }

        // 检查字段
        $columns = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll();
        $columnNames = array_column($columns, 'Field');

        if (in_array('update_at', $columnNames)) {
            echo "     - 发现 update_at 字段，开始修复\n";

            // 获取 update_at 字段的定义
            $updateAtColumn = null;
            foreach ($columns as $column) {
                if ($column['Field'] === 'update_at') {
                    $updateAtColumn = $column;
                    break;
                }
            }

            if ($updateAtColumn) {
                try {
                    $pdo->beginTransaction();

                    // 添加新字段（简化版本）
                    if (!in_array('updated_at', $columnNames)) {
                        $pdo->exec("ALTER TABLE $table ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
                        echo "     - 添加 updated_at 字段成功\n";
                    }

                    // 复制数据
                    $pdo->exec("UPDATE $table SET updated_at = update_at WHERE update_at IS NOT NULL");
                    echo "     - 数据复制完成\n";

                    // 删除旧字段
                    $pdo->exec("ALTER TABLE $table DROP COLUMN update_at");
                    echo "     - 删除 update_at 字段成功\n";

                    $pdo->commit();
                    echo "     ✓ 表 $table 修复完成\n";

                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo "     ✗ 修复表 $table 失败: " . $e->getMessage() . "\n";
                }
            }
        } elseif (in_array('updated_at', $columnNames)) {
            echo "     - ✓ 已有正确的 updated_at 字段\n";
        } else {
            echo "     - ⚠ 没有找到 update_at 或 updated_at 字段\n";
        }
    }

    echo "\n3. 验证修复结果...\n";

    foreach ($tablesToFix as $table) {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array($table, $tables)) {
            continue;
        }

        $columns = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll();
        $columnNames = array_column($columns, 'Field');

        echo "   表 $table:\n";

        if (in_array('updated_at', $columnNames)) {
            echo "     ✓ updated_at 字段存在\n";
        } else {
            echo "     ✗ updated_at 字段不存在\n";
        }

        if (in_array('update_at', $columnNames)) {
            echo "     ✗ update_at 字段仍然存在（修复失败）\n";
        } else {
            echo "     ✓ update_at 字段已清除\n";
        }
    }

    echo "\n4. 测试查询...\n";

    // 测试一个简单的查询，确保没有 update_at 字段错误
    try {
        $result = $pdo->query("SELECT COUNT(*) as count FROM article_read_logs WHERE updated_at IS NOT NULL")->fetch();
        echo "   - article_read_logs 表 updated_at 查询测试: ✓ 成功 (记录数: {$result['count']})\n";
    } catch (Exception $e) {
        echo "   - article_read_logs 表 updated_at 查询测试: ✗ 失败 - " . $e->getMessage() . "\n";
    }

    try {
        $result = $pdo->query("SELECT COUNT(*) as count FROM sys_news_article WHERE updated_at IS NOT NULL")->fetch();
        echo "   - sys_news_article 表 updated_at 查询测试: ✓ 成功 (记录数: {$result['count']})\n";
    } catch (Exception $e) {
        echo "   - sys_news_article 表 updated_at 查询测试: ✗ 失败 - " . $e->getMessage() . "\n";
    }

    try {
        $result = $pdo->query("SELECT COUNT(*) as count FROM article_read_statistics WHERE updated_at IS NOT NULL")->fetch();
        echo "   - article_read_statistics 表 updated_at 查询测试: ✓ 成功 (记录数: {$result['count']})\n";
    } catch (Exception $e) {
        echo "   - article_read_statistics 表 updated_at 查询测试: ✗ 失败 - " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "数据库连接失败: " . $e->getMessage() . "\n";
}

echo "\n=== 修复完成 ===\n";
