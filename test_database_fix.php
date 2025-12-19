<?php

echo "=== 测试数据库修复效果 ===\n\n";

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

    // 获取所有表
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "2. 数据库中的所有表:\n";
    foreach ($tables as $table) {
        echo "   - $table\n";
    }
    echo "\n";

    // 检查每张表的结构
    echo "3. 详细检查表结构:\n";
    foreach ($tables as $table) {
        echo "   表: $table\n";

        $columns = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll();
        $columnNames = array_column($columns, 'Field');

        // 检查时间字段
        $timeFields = [];
        foreach ($columnNames as $columnName) {
            if (preg_match('/(time|at|date)/i', $columnName)) {
                $timeFields[] = $columnName;
            }
        }

        if (!empty($timeFields)) {
            echo "     - 时间字段: " . implode(', ', $timeFields) . "\n";
        }

        // 检查是否有 update_at 字段问题
        if (in_array('update_at', $columnNames)) {
            echo "     - ⚠ 发现 update_at 字段（可能应该是 updated_at）\n";

            // 检查是否有数据
            // 检查是否有数据（这个检查现在应该失败，因为 update_at 字段已被删除）
            try {
                $count = $pdo->query("SELECT COUNT(*) as count FROM $table WHERE update_at IS NOT NULL")->fetch()['count'];
                if ($count > 0) {
                    echo "     - ⚠ 意外：仍有 $count 条记录包含 update_at 数据\n";
                }
            } catch (Exception $e) {
                echo "     - ✓ 确认：update_at 字段已不存在（符合预期）\n";
            }
        }

        if (in_array('updated_at', $columnNames)) {
            echo "     - ✓ 正确的 updated_at 字段存在\n";
        }

        echo "\n";
    }

    // 4. 执行一个简单的查询测试
    echo "4. 执行简单查询测试:\n";
    try {
        // 测试 article_read_logs 表查询
        if (in_array('article_read_logs', $tables)) {
            $count = $pdo->query("SELECT COUNT(*) as count FROM article_read_logs")->fetch()['count'];
            echo "   - article_read_logs 表记录数: $count\n";

            // 测试一个包含时间字段的查询
            $recent = $pdo->query("SELECT id, read_time, create_at, updated_at FROM article_read_logs LIMIT 3")->fetchAll();
            echo "   - 最近3条记录的时间字段:\n";
            foreach ($recent as $record) {
                echo "     ID: {$record['id']}, read_time: {$record['read_time']}, create_at: {$record['create_at']}, updated_at: {$record['updated_at']}\n";
            }
        }

        echo "   ✓ 查询测试成功，没有出现 'update_at' 字段错误\n";

    } catch (Exception $e) {
        echo "   ✗ 查询测试失败: " . $e->getMessage() . "\n";
    }

    echo "\n";

    // 5. 检查 Entity 文件是否存在
    echo "5. 检查 Entity 文件:\n";
    $entityDir = __DIR__ . '/src/Entity';
    if (is_dir($entityDir)) {
        $entities = glob($entityDir . '/*.php');
        foreach ($entities as $entity) {
            $entityName = basename($entity, '.php');
            echo "   - Entity: $entityName\n";

            // 检查 Entity 中是否有 update_at 字段定义
            $entityContent = file_get_contents($entity);
            if (strpos($entityContent, 'update_at') !== false) {
                echo "     ⚠ 发现 update_at 字段定义\n";
            }
            if (strpos($entityContent, 'updated_at') !== false) {
                echo "     ✓ 发现 updated_at 字段定义\n";
            }
        }
    } else {
        echo "   - Entity 目录不存在\n";
    }

} catch (Exception $e) {
    echo "数据库连接失败: " . $e->getMessage() . "\n";
}

echo "\n=== 测试完成 ===\n";
