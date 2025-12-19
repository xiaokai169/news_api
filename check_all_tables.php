<?php

// 直接读取.env文件
$env_file = __DIR__ . '/.env';
$env_vars = [];

if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $env_vars[trim($key)] = trim($value);
        }
    }
}

// 从DATABASE_URL解析数据库连接信息
$database_url = $env_vars['DATABASE_URL'] ?? '';
if ($database_url && preg_match('/mysql:\/\/([^:]+):([^@]+)@([^:]+):(\d+)\/([^?]+)/', $database_url, $matches)) {
    $username = $matches[1];
    $password = $matches[2];
    $host = $matches[3];
    $port = $matches[4];
    $dbname = $matches[5];
} else {
    die("无法解析数据库连接信息\n");
}

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== 数据库连接成功 ===\n";
    echo "数据库: $dbname\n";
    echo "主机: $host:$port\n\n";

    // 获取所有表
    echo "=== 数据库中的所有表 ===\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = [];
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
        echo "- {$row[0]}\n";
    }

    // 检查我们关心的表
    $target_tables = ['sys_news_article', 'article_read_logs', 'article_read_statistics'];
    echo "\n=== 检查目标表 ===\n";

    foreach ($target_tables as $table) {
        if (in_array($table, $tables)) {
            echo "✅ 表 $table 存在\n";

            // 获取表结构
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[$row['Field']] = $row;
            }

            echo "  字段列表:\n";
            foreach ($columns as $field => $details) {
                $marker = "";
                if ($field === 'update_at') $marker = " <-- 目标字段";
                if (in_array($field, ['update_time', 'updated_at', 'modified_at', 'last_updated'])) $marker = " <-- 类似字段";
                echo "    - $field: {$details['Type']} ({$details['Null']})$marker\n";
            }

            // 检查update_at字段
            if (isset($columns['update_at'])) {
                echo "  ✅ 包含 update_at 字段\n";
            } else {
                echo "  ❌ 缺少 update_at 字段\n";
            }

        } else {
            echo "❌ 表 $table 不存在\n";
        }
        echo "\n";
    }

    // 如果缺少表，检查是否有相关的创建脚本
    $missing_tables = array_diff($target_tables, $tables);
    if (!empty($missing_tables)) {
        echo "=== 缺少的表 ===\n";
        foreach ($missing_tables as $table) {
            echo "- $table\n";
        }

        echo "\n=== 检查相关的表创建脚本 ===\n";
        $sql_files = ['setup_reading_tables.sql', 'create_table.sql', 'create_tables.sql'];
        foreach ($sql_files as $file) {
            if (file_exists($file)) {
                echo "✅ 找到脚本: $file\n";
                $content = file_get_contents($file);
                foreach ($missing_tables as $table) {
                    if (strpos($content, $table) !== false) {
                        echo "  - 包含表 $table 的创建语句\n";
                    }
                }
            } else {
                echo "❌ 脚本不存在: $file\n";
            }
        }
    }

} catch (PDOException $e) {
    echo "❌ 数据库连接失败: " . $e->getMessage() . "\n";
}

echo "\n=== 检查完成 ===\n";
