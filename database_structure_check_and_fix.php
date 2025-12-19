<?php
require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// 加载环境变量
$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

// 数据库连接参数
$host = $_ENV['DATABASE_HOST'] ?? '127.0.0.1';
$port = $_ENV['DATABASE_PORT'] ?? '3306';
$dbname = $_ENV['DATABASE_NAME'] ?? 'official_website';
$username = $_ENV['DATABASE_USER'] ?? 'root';
$password = $_ENV['DATABASE_PASSWORD'] ?? '';

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
        $fix_sql .= "-- 生成时间: " . date('Y-m-d H:i:s') . "\n\n";

        foreach ($fixes_needed as $table => $fix_info) {
            echo "表 $table 需要添加 $target_field 字段\n";

            // 检查是否有 create_at 字段来确定合适的字段类型
            $has_create_at = false;
            if (isset($table_structures[$table]['create_at'])) {
                $create_at_type = $table_structures[$table]['create_at']['Type'];
                $has_create_at = true;
            } elseif (isset($table_structures[$table]['created_at'])) {
                $create_at_type = $table_structures[$table]['created_at']['Type'];
                $has_create_at = true;
            }

            if ($has_create_at) {
                $fix_sql .= "-- 表 $table: 添加 $target_field 字段 (基于 create_at 字段类型)\n";
                $fix_sql .= "ALTER TABLE $table ADD COLUMN $target_field $create_at_type DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;\n\n";
            } else {
                $fix_sql .= "-- 表 $table: 添加 $target_field 字段 (默认类型)\n";
                $fix_sql .= "ALTER TABLE $table ADD COLUMN $target_field timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;\n\n";
            }
        }

        // 保存修复脚本
        file_put_contents('fix_update_at_fields.sql', $fix_sql);
        echo "\n✅ 修复脚本已生成: fix_update_at_fields.sql\n";
        echo "请检查脚本内容，确认无误后执行:\n";
        echo "mysql -h $host -P $port -u $username -p $dbname < fix_update_at_fields.sql\n\n";
    } else {
        echo "✅ 所有表都已包含 $target_field 字段，无需修复\n\n";
    }

    // 生成详细报告
    $report = "=== 数据库表结构检查报告 ===\n";
    $report .= "检查时间: " . date('Y-m-d H:i:s') . "\n";
    $report .= "数据库: $dbname\n\n";

    foreach ($tables_to_check as $table) {
        $report .= "表: $table\n";
        if (isset($table_structures[$table])) {
            $has_update_at = isset($table_structures[$table][$target_field]);
            $report .= "  状态: " . ($has_update_at ? "✅ 包含 $target_field" : "❌ 缺少 $target_field") . "\n";

            foreach ($table_structures[$table] as $field => $details) {
                $report .= "  - $field: {$details['Type']}\n";
            }
        } else {
            $report .= "  状态: ❌ 表不存在\n";
        }
        $report .= "\n";
    }

    file_put_contents('database_structure_check_report.txt', $report);
    echo "📄 详细检查报告已保存: database_structure_check_report.txt\n";

} catch (PDOException $e) {
    echo "❌ 数据库连接失败: " . $e->getMessage() . "\n";
    echo "请检查数据库配置和连接参数\n";
}

echo "\n=== 检查完成 ===\n";
