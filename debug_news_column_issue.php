<?php

require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// 加载环境变量
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

// 数据库连接配置
$host = $_ENV['DATABASE_HOST'] ?? '127.0.0.1';
$port = $_ENV['DATABASE_PORT'] ?? '3306';
$dbname = $_ENV['DATABASE_NAME'] ?? 'official_website';
$username = $_ENV['DATABASE_USER'] ?? 'root';
$password = $_ENV['DATABASE_PASSWORD'] ?? '';

echo "=== 调试 NewsColumn 问题 ===\n\n";

try {
    // 连接数据库
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "1. 检查 sys_news_article 表结构:\n";
    echo str_repeat("-", 50) . "\n";

    $stmt = $pdo->prepare("DESCRIBE sys_news_article");
    $stmt->execute();
    $columns = $stmt->fetchAll();

    $timeFields = [];
    foreach ($columns as $column) {
        echo sprintf("  %-20s %-20s %s\n",
            $column['Field'],
            $column['Type'],
            $column['Null'] === 'NO' ? 'NOT NULL' : 'NULL'
        );

        // 查找时间相关字段
        if (strpos(strtolower($column['Field']), 'time') !== false ||
            strpos(strtolower($column['Field']), 'create') !== false ||
            strpos(strtolower($column['Field']), 'update') !== false) {
            $timeFields[] = $column['Field'];
        }
    }

    echo "\n时间相关字段: " . implode(', ', $timeFields) . "\n\n";

    echo "2. 检查表中的实际列名:\n";
    echo str_repeat("-", 50) . "\n";

    // 检查是否存在 update_at 和 updated_at
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                           WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'sys_news_article'
                           AND COLUMN_NAME LIKE '%update%'");
    $stmt->execute([$dbname]);
    $updateColumns = $stmt->fetchAll();

    echo "包含 'update' 的列:\n";
    foreach ($updateColumns as $col) {
        echo "  - " . $col['COLUMN_NAME'] . "\n";
    }

    // 检查是否存在 create_at 和 created_at
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                           WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'sys_news_article'
                           AND COLUMN_NAME LIKE '%create%'");
    $stmt->execute([$dbname]);
    $createColumns = $stmt->fetchAll();

    echo "\n包含 'create' 的列:\n";
    foreach ($createColumns as $col) {
        echo "  - " . $col['COLUMN_NAME'] . "\n";
    }

    echo "\n3. 测试查询语句:\n";
    echo str_repeat("-", 50) . "\n";

    // 测试正确的列名
    try {
        $stmt = $pdo->prepare("SELECT id, title, update_at FROM sys_news_article LIMIT 1");
        $stmt->execute();
        echo "✓ 查询 'update_at' 列成功\n";
    } catch (Exception $e) {
        echo "✗ 查询 'update_at' 列失败: " . $e->getMessage() . "\n";
    }

    try {
        $stmt = $pdo->prepare("SELECT id, title, updated_at FROM sys_news_article LIMIT 1");
        $stmt->execute();
        echo "✓ 查询 'updated_at' 列成功\n";
    } catch (Exception $e) {
        echo "✗ 查询 'updated_at' 列失败: " . $e->getMessage() . "\n";
    }

    try {
        $stmt = $pdo->prepare("SELECT id, title, create_at FROM sys_news_article LIMIT 1");
        $stmt->execute();
        echo "✓ 查询 'create_at' 列成功\n";
    } catch (Exception $e) {
        echo "✗ 查询 'create_at' 列失败: " . $e->getMessage() . "\n";
    }

    try {
        $stmt = $pdo->prepare("SELECT id, title, created_at FROM sys_news_article LIMIT 1");
        $stmt->execute();
        echo "✓ 查询 'created_at' 列成功\n";
    } catch (Exception $e) {
        echo "✗ 查询 'created_at' 列失败: " . $e->getMessage() . "\n";
    }

    echo "\n4. 检查 Entity 映射文件:\n";
    echo str_repeat("-", 50) . "\n";

    // 读取 Entity 文件
    $entityFile = __DIR__ . '/src/Entity/SysNewsArticle.php';
    if (file_exists($entityFile)) {
        $entityContent = file_get_contents($entityFile);

        // 查找所有 ORM\Column 注解
        if (preg_match_all('/#\[\s*ORM\Column\s*\([^)]*name:\s*[\'"]([^\'"]+)[\'"][^)]*\)\s*\]/', $entityContent, $matches)) {
            echo "Entity 中定义的列名:\n";
            foreach ($matches[1] as $columnName) {
                echo "  - " . $columnName . "\n";
            }
        }

        // 查找时间相关字段
        if (preg_match_all('/#\[\s*ORM\Column\s*\([^)]*name:\s*[\'"]([^\'"]*[Tt]ime[^\'"]*)[\'"][^)]*\)\s*\]/', $entityContent, $timeMatches)) {
            echo "\nEntity 中时间相关列名:\n";
            foreach ($timeMatches[1] as $columnName) {
                echo "  - " . $columnName . "\n";
            }
        }
    }

} catch (Exception $e) {
    echo "数据库连接失败: " . $e->getMessage() . "\n";
    echo "\n请检查数据库配置:\n";
    echo "Host: $host\n";
    echo "Port: $port\n";
    echo "Database: $dbname\n";
    echo "Username: $username\n";
}

echo "\n=== 调试完成 ===\n";
