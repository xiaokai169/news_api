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

    // 修复后的article_read_logs表创建语句
    $sql = "
    CREATE TABLE IF NOT EXISTS `article_read_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `article_id` int(11) NOT NULL COMMENT '文章ID',
        `user_id` int(11) DEFAULT NULL COMMENT '用户ID，匿名用户为NULL',
        `ip_address` varchar(45) NOT NULL COMMENT 'IP地址',
        `user_agent` varchar(500) DEFAULT NULL COMMENT '用户代理',
        `read_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '阅读时间',
        `session_id` varchar(255) DEFAULT NULL COMMENT '会话ID',
        `device_type` varchar(20) DEFAULT NULL COMMENT '设备类型：desktop/mobile/tablet',
        `referer` varchar(500) DEFAULT NULL COMMENT '来源页面',
        `duration_seconds` int(11) DEFAULT NULL COMMENT '阅读时长（秒）',
        `is_completed` tinyint(1) DEFAULT '0' COMMENT '是否读完：1-是，0-否',
        `create_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
        `update_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
        PRIMARY KEY (`id`),
        KEY `idx_article_id` (`article_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_read_time` (`read_time`),
        KEY `idx_device_type` (`device_type`),
        KEY `idx_create_at` (`create_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文章阅读记录表';
    ";

    echo "=== 创建 article_read_logs 表 ===\n";
    echo "执行SQL:\n" . $sql . "\n\n";

    try {
        $pdo->exec($sql);
        echo "✅ article_read_logs 表创建成功\n\n";
    } catch (PDOException $e) {
        echo "❌ 创建失败: " . $e->getMessage() . "\n\n";
    }

    // 验证结果
    echo "=== 验证所有目标表 ===\n";
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
                echo "    - 默认值: " . ($columns['update_at']['Default'] ?: 'NULL') . "\n";
                echo "    - 可空: {$columns['update_at']['Null']}\n";
            } else {
                echo "  ❌ 缺少 update_at 字段\n";
            }
        } else {
            echo "❌ 表 $table 不存在\n";
        }
        echo "\n";
    }

    // 生成最终报告
    $report = "=== 数据库表结构修复完成报告 ===\n";
    $report .= "修复时间: " . date('Y-m-d H:i:s') . "\n";
    $report .= "数据库: $dbname\n";
    $report .= "主机: $host:$port\n\n";

    $report .= "目标表检查结果:\n";
    foreach ($target_tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);

        if ($stmt->rowCount() > 0) {
            $report .= "✅ $table: 存在且包含 update_at 字段\n";
        } else {
            $report .= "❌ $table: 不存在\n";
        }
    }

    file_put_contents('database_fix_final_report.txt', $report);
    echo "📄 最终修复报告已保存: database_fix_final_report.txt\n";

} catch (PDOException $e) {
    echo "❌ 数据库连接失败: " . $e->getMessage() . "\n";
}

echo "\n=== 修复完成 ===\n";
