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

    // 读取并执行setup_reading_tables.sql
    $sql_file = __DIR__ . '/setup_reading_tables.sql';
    if (!file_exists($sql_file)) {
        die("SQL文件不存在: $sql_file\n");
    }

    echo "=== 执行数据库修复脚本 ===\n";
    echo "脚本文件: setup_reading_tables.sql\n\n";

    $sql_content = file_get_contents($sql_file);

    // 分割SQL语句（简单分割，以分号结尾的语句）
    $statements = array_filter(array_map('trim', explode(';', $sql_content)));

    $success_count = 0;
    $error_count = 0;

    foreach ($statements as $i => $statement) {
        if (empty($statement)) continue;

        echo "执行语句 " . ($i + 1) . ":\n";
        echo substr($statement, 0, 100) . (strlen($statement) > 100 ? "..." : "") . "\n";

        try {
            $pdo->exec($statement);
            echo "✅ 执行成功\n\n";
            $success_count++;
        } catch (PDOException $e) {
            echo "❌ 执行失败: " . $e->getMessage() . "\n\n";
            $error_count++;
        }
    }

    echo "=== 执行结果统计 ===\n";
    echo "成功: $success_count 条语句\n";
    echo "失败: $error_count 条语句\n\n";

    // 验证修复结果
    echo "=== 验证修复结果 ===\n";
    $target_tables = ['sys_news_article', 'article_read_logs', 'article_read_statistics'];

    foreach ($target_tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);

        if ($stmt->rowCount() > 0) {
            echo "✅ 表 $table 存在\n";

            // 检查update_at字段
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[$row['Field']] = $row;
            }

            if (isset($columns['update_at'])) {
                echo "  ✅ 包含 update_at 字段 ({$columns['update_at']['Type']})\n";
            } else {
                echo "  ❌ 缺少 update_at 字段\n";
            }
        } else {
            echo "❌ 表 $table 不存在\n";
        }
    }

} catch (PDOException $e) {
    echo "❌ 数据库连接失败: " . $e->getMessage() . "\n";
}

echo "\n=== 修复完成 ===\n";
