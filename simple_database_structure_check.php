<?php

// 直接读取.env文件
$env_file = __DIR__ . '/.env';
$env_vars = [];

if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue; // 跳过注释
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $env_vars[trim($key)] = trim($value);
        }
    }
}

// 数据库连接参数 - 从DATABASE_URL解析或使用默认值
$database_url = $env_vars['DATABASE_URL'] ?? '';
if ($database_url && preg_match('/mysql:\/\/([^:]+):([^@]+)@([^:]+):(\d+)\/([^?]+)/', $database_url, $matches)) {
    $username = $matches[1];
    $password = $matches[2];
    $host = $matches[3];
    $port = $matches[4];
    $dbname = $matches[5];
} else {
    // 备用配置
    $host = $env_vars['DATABASE_HOST'] ?? '127.0.0.1';
    $port = $env_vars['DATABASE_PORT'] ?? '3306';
    $dbname = $env_vars['DATABASE_NAME'] ?? 'official_website';
    $username = $env_vars['DATABASE_USER'] ?? 'root';
    $password = $env_vars['DATABASE_PASSWORD'] ?? 'qwe147258..';
}

// 要检查的表列表
$tables_to_check = [
    'sys_news_article',
    'article_read_logs',
    'article_read_statistics'
];

// 要检查的字段
$target_field = 'update_at';
$alternative_fields = ['update_time', 'updated_at', 'modified_at', 'last_updated'];

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== 数据库连接成功 ===\n";
    echo "数据库: $dbname\n";
    echo "主机: $host:$port\n";
    echo "检查时间: " . date('Y-m-d H:i:s') . "\n\n";

    $fixes_needed = [];
    $table_structures = [];

    foreach ($tables_to_check as $table) {
        echo "=== 检查表: $table ===\n";

        // 检查表是否存在
        $table_exists = false;
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->rowCount() > 0) {
            $table_exists = true;
        }

        if (!$table_exists) {
            echo "❌ 表 $table 不存在\n\n";
            continue;
        }

        // 获取表结构
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[$row['Field']] = $row;
        }
        $table_structures[$table] = $columns;

        echo "表字段列表:\n";
        foreach ($columns as $field => $details) {
            echo "  - $field: {$details['Type']} ({$details['Null']}, {$details['Key']})\n";
        }

        // 检查目标字段
        $has_target_field = isset($columns[$target_field]);
        $found_alternative = null;

        if (!$has_target_field) {
            // 检查替代字段
            foreach ($alternative_fields as $alt_field) {
                if (isset($columns[$alt_field])) {
                    $found_alternative = $alt_field;
                    break;
                }
            }
        }

        if ($has_target_field) {
            echo "✅ 找到字段: $target_field\n";
            echo "   类型: {$columns[$target_field]['Type']}\n";
            echo "   可空: {$columns[$target_field]['Null']}\n";
            echo "   默认值: " . ($columns[$target_field]['Default'] ?: 'NULL') . "\n";
        } else {
            echo "❌ 未找到字段: $target_field\n";
            if ($found_alternative) {
                echo "⚠️  找到类似字段: $found_alternative\n";
                echo "   类型: {$columns[$found_alternative]['Type']}\n";
            } else {
                echo "⚠️  未找到任何更新时间相关字段\n";
                $fixes_needed[$table] = [
                    'action' => 'add_field',
                    'field' => $target_field,
                    'existing_columns' => array_keys($columns)
                ];
            }
        }

        echo "\n";
    }

    // 生成修复脚本
    if (!empty($fixes_needed)) {
        echo "=== 需要修复的表 ===\n";
        $fix_sql = "-- 数据库表结构修复脚本\n";
        $fix_sql .= "-- 生成时间: " . date('Y-m-d H:i:s') . "\n";
        $fix_sql .= "-- 目标: 为缺少 update_at 字段的表添加该字段\n\n";

        foreach ($fixes_needed as $table => $fix_info) {
            echo "表 $table 需要添加 $target_field 字段\n";

            // 检查是否有 create_at 字段来确定合适的字段类型
            $has_create_at = false;
            $create_at_type = 'timestamp'; // 默认类型

            if (isset($table_structures[$table]['create_at'])) {
                $create_at_type = $table_structures[$table]['create_at']['Type'];
                $has_create_at = true;
            } elseif (isset($table_structures[$table]['created_at'])) {
                $create_at_type = $table_structures[$table]['created_at']['Type'];
                $has_create_at = true;
            } elseif (isset($table_structures[$table]['create_time'])) {
                $create_at_type = $table_structures[$table]['create_time']['Type'];
                $has_create_at = true;
            }

            if ($has_create_at) {
                $fix_sql .= "-- 表 $table: 添加 $target_field 字段 (基于现有创建时间字段类型: $create_at_type)\n";
                $fix_sql .= "ALTER TABLE $table ADD COLUMN $target_field $create_at_type DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间';\n\n";
            } else {
                $fix_sql .= "-- 表 $table: 添加 $target_field 字段 (使用默认类型)\n";
                $fix_sql .= "ALTER TABLE $table ADD COLUMN $target_field timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间';\n\n";
            }
        }

        // 保存修复脚本
        file_put_contents('fix_update_at_fields.sql', $fix_sql);
        echo "\n✅ 修复脚本已生成: fix_update_at_fields.sql\n";
        echo "请检查脚本内容，确认无误后执行:\n";
        echo "mysql -h $host -P $port -u $username -p $dbname < fix_update_at_fields.sql\n\n";

        // 显示修复脚本内容
        echo "=== 修复脚本内容预览 ===\n";
        echo $fix_sql;
    } else {
        echo "✅ 所有表都已包含 $target_field 字段，无需修复\n\n";
    }

    // 生成详细报告
    $report = "=== 数据库表结构检查报告 ===\n";
    $report .= "检查时间: " . date('Y-m-d H:i:s') . "\n";
    $report .= "数据库: $dbname\n";
    $report .= "主机: $host:$port\n\n";

    foreach ($tables_to_check as $table) {
        $report .= "表: $table\n";
        if (isset($table_structures[$table])) {
            $has_update_at = isset($table_structures[$table][$target_field]);
            $report .= "  状态: " . ($has_update_at ? "✅ 包含 $target_field" : "❌ 缺少 $target_field") . "\n";

            $report .= "  字段详情:\n";
            foreach ($table_structures[$table] as $field => $details) {
                $marker = ($field === $target_field) ? " <-- 目标字段" : "";
                $report .= "    - $field: {$details['Type']} ({$details['Null']}, {$details['Key']})$marker\n";
            }
        } else {
            $report .= "  状态: ❌ 表不存在\n";
        }
        $report .= "\n";
    }

    file_put_contents('database_structure_check_report.txt', $report);
    echo "📄 详细检查报告已保存: database_structure_check_report.txt\n\n";

} catch (PDOException $e) {
    echo "❌ 数据库连接失败: " . $e->getMessage() . "\n";
    echo "请检查数据库配置和连接参数\n";
    echo "使用的连接信息:\n";
    echo "  主机: $host:$port\n";
    echo "  数据库: $dbname\n";
    echo "  用户名: $username\n";
}

echo "\n=== 检查完成 ===\n";
