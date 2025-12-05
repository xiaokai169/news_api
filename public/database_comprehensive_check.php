<?php
require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// 加载环境变量
$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/../.env');

// 解析DATABASE_URL
$databaseUrl = $_ENV['DATABASE_URL'] ?? '';
if (empty($databaseUrl)) {
    die("DATABASE_URL 环境变量未设置\n");
}

// 解析 mysql://user:password@host:port/database?serverVersion=xxx&charset=utf8
$parsedUrl = parse_url($databaseUrl);
if (!$parsedUrl || $parsedUrl['scheme'] !== 'mysql') {
    die("无效的 DATABASE_URL 格式\n");
}

$dbHost = $parsedUrl['host'] ?? '127.0.0.1';
$dbPort = $parsedUrl['port'] ?? '3306';
$dbName = ltrim($parsedUrl['path'] ?? 'official_website', '/');
$dbUser = $parsedUrl['user'] ?? 'root';
$dbPassword = $parsedUrl['pass'] ?? '';

echo "数据库连接配置:\n";
echo "Host: {$dbHost}\n";
echo "Port: {$dbPort}\n";
echo "Database: {$dbName}\n";
echo "User: {$dbUser}\n";
echo "Password: " . (empty($dbPassword) ? '(空)' : str_repeat('*', strlen($dbPassword))) . "\n\n";

try {
    // 创建数据库连接
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    echo "=== 数据库连接成功 ===\n\n";

    // 1. 检查所有表
    echo "=== 1. 数据库中的所有表 ===\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "- {$table}\n";
    }
    echo "\n";

    // 2. 查找微信相关的表
    echo "=== 2. 微信相关的表 ===\n";
    $wechatTables = array_filter($tables, function($table) {
        return stripos($table, 'wechat') !== false || stripos($table, 'article') !== false;
    });

    if (empty($wechatTables)) {
        echo "未找到微信相关的表\n\n";
    } else {
        foreach ($wechatTables as $table) {
            echo "- {$table}\n";
        }
        echo "\n";
    }

    // 3. 检查每个微信相关表的结构
    echo "=== 3. 微信相关表的结构 ===\n";
    foreach ($wechatTables as $table) {
        echo "--- 表: {$table} ---\n";
        $columns = $pdo->query("DESCRIBE {$table}")->fetchAll();
        foreach ($columns as $column) {
            echo "  {$column['Field']} | {$column['Type']} | {$column['Null']} | {$column['Key']} | {$column['Default']}\n";
        }
        echo "\n";

        // 检查索引
        $indexes = $pdo->query("SHOW INDEX FROM {$table}")->fetchAll();
        if (!empty($indexes)) {
            echo "  索引:\n";
            foreach ($indexes as $index) {
                echo "    - {$index['Key_name']} ({$index['Column_name']})\n";
            }
            echo "\n";
        }
    }

    // 4. 检查微信文章数据
    echo "=== 4. 微信文章数据统计 ===\n";
    foreach ($wechatTables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) as count FROM {$table}")->fetch()['count'];
            echo "表 {$table}: {$count} 条记录\n";

            if ($count > 0) {
                // 显示最近几条记录的创建时间
                if (in_array('created_at', array_column($pdo->query("DESCRIBE {$table}")->fetchAll(), 'Field'))) {
                    $recent = $pdo->query("SELECT created_at FROM {$table} ORDER BY created_at DESC LIMIT 3")->fetchAll();
                    echo "  最近创建时间:\n";
                    foreach ($recent as $row) {
                        echo "    - {$row['created_at']}\n";
                    }
                }

                // 检查数据来源字段
                $columns = array_column($pdo->query("DESCRIBE {$table}")->fetchAll(), 'Field');
                $sourceFields = array_filter($columns, function($field) {
                    return stripos($field, 'source') !== false || stripos($field, 'type') !== false || stripos($field, 'material') !== false;
                });

                if (!empty($sourceFields)) {
                    echo "  数据来源字段: " . implode(', ', $sourceFields) . "\n";

                    foreach ($sourceFields as $field) {
                        $sources = $pdo->query("SELECT DISTINCT {$field} FROM {$table} WHERE {$field} IS NOT NULL LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
                        if (!empty($sources)) {
                            echo "    {$field}: " . implode(', ', $sources) . "\n";
                        }
                    }
                }
            }
            echo "\n";
        } catch (Exception $e) {
            echo "检查表 {$table} 时出错: {$e->getMessage()}\n\n";
        }
    }

    // 5. 检查公众号账户配置
    echo "=== 5. 公众号账户配置检查 ===\n";
    if (in_array('wechat_public_account', $tables)) {
        echo "--- wechat_public_account 表结构 ---\n";
        $columns = $pdo->query("DESCRIBE wechat_public_account")->fetchAll();
        foreach ($columns as $column) {
            echo "  {$column['Field']} | {$column['Type']} | {$column['Null']} | {$column['Key']}\n";
        }
        echo "\n";

        $count = $pdo->query("SELECT COUNT(*) as count FROM wechat_public_account")->fetch()['count'];
        echo "公众号账户数量: {$count}\n\n";

        if ($count > 0) {
            $accounts = $pdo->query("SELECT id, name, app_id, is_active, created_at, updated_at FROM wechat_public_account")->fetchAll();
            foreach ($accounts as $account) {
                echo "公众号 ID: {$account['id']}\n";
                echo "  名称: {$account['name']}\n";
                echo "  App ID: {$account['app_id']}\n";
                echo "  状态: " . ($account['is_active'] ? '激活' : '未激活') . "\n";
                echo "  创建时间: {$account['created_at']}\n";
                echo "  更新时间: {$account['updated_at']}\n\n";
            }
        }
    } else {
        echo "未找到 wechat_public_account 表\n\n";
    }

    // 6. 检查分布式锁状态
    echo "=== 6. 分布式锁状态检查 ===\n";
    if (in_array('distributed_locks', $tables)) {
        echo "--- distributed_locks 表结构 ---\n";
        $columns = $pdo->query("DESCRIBE distributed_locks")->fetchAll();
        foreach ($columns as $column) {
            echo "  {$column['Field']} | {$column['Type']} | {$column['Null']} | {$column['Key']}\n";
        }
        echo "\n";

        $count = $pdo->query("SELECT COUNT(*) as count FROM distributed_locks")->fetch()['count'];
        echo "分布式锁数量: {$count}\n\n";

        if ($count > 0) {
            $locks = $pdo->query("SELECT lock_key, is_locked, created_at, expire_time FROM distributed_locks ORDER BY created_at DESC")->fetchAll();
            foreach ($locks as $lock) {
                echo "锁键: {$lock['lock_key']}\n";
                echo "  状态: " . ($lock['is_locked'] ? '锁定' : '未锁定') . "\n";
                echo "  创建时间: {$lock['created_at']}\n";
                echo "  过期时间: {$lock['expire_time']}\n";

                // 检查是否过期
                $now = new DateTime();
                $expireTime = new DateTime($lock['expire_time']);
                $isExpired = $expireTime < $now;
                echo "  是否过期: " . ($isExpired ? '是' : '否') . "\n\n";
            }
        }
    } else {
        echo "未找到 distributed_locks 表\n\n";
    }

    // 7. 检查其他可能相关的表
    echo "=== 7. 其他可能相关的表 ===\n";
    $otherTables = array_filter($tables, function($table) {
        return stripos($table, 'news') !== false || stripos($table, 'article') !== false;
    });

    foreach ($otherTables as $table) {
        if (!in_array($table, $wechatTables)) {
            echo "--- 表: {$table} ---\n";
            $count = $pdo->query("SELECT COUNT(*) as count FROM {$table}")->fetch()['count'];
            echo "记录数: {$count}\n";

            if ($count > 0 && $count <= 10) {
                $sample = $pdo->query("SELECT * FROM {$table} LIMIT 3")->fetchAll();
                echo "样本数据:\n";
                foreach ($sample as $row) {
                    echo "  " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
                }
            }
            echo "\n";
        }
    }

    echo "=== 数据库检查完成 ===\n";

} catch (Exception $e) {
    echo "数据库连接失败: " . $e->getMessage() . "\n";
    echo "请检查数据库配置:\n";
    echo "Host: {$dbHost}\n";
    echo "Port: {$dbPort}\n";
    echo "Database: {$dbName}\n";
    echo "User: {$dbUser}\n";
}
