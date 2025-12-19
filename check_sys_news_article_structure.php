<?php
require_once 'vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\EntityManager;

// 数据库配置
$connectionParams = [
    'dbname' => 'official_website',
    'user' => 'root',
    'password' => 'qwe147258..',
    'host' => '127.0.0.1',
    'port' => '3306',
    'driver' => 'pdo_mysql',
    'charset' => 'utf8mb4',
];

try {
    // 创建数据库连接
    $connection = DriverManager::getConnection($connectionParams);

    echo "=== 检查 sys_news_article 表结构 ===\n\n";

    // 获取表结构
    $sql = "DESCRIBE sys_news_article";
    $result = $connection->fetchAllAssociative($sql);

    echo "字段信息：\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-20s %-15s %-10s %-10s %-15s %-20s\n",
        "字段名", "数据类型", "允许NULL", "键", "默认值", "额外信息");
    echo str_repeat("-", 80) . "\n";

    foreach ($result as $row) {
        printf("%-20s %-15s %-10s %-10s %-15s %-20s\n",
            $row['Field'],
            $row['Type'],
            $row['Null'],
            $row['Key'],
            $row['Default'] ?? 'NULL',
            $row['Extra']
        );
    }

    echo "\n" . str_repeat("-", 80) . "\n";

    // 检查时间相关字段
    echo "\n=== 时间字段详细信息 ===\n";
    $timeFields = ['create_at', 'update_at', 'updated_at', 'created_at'];

    foreach ($timeFields as $field) {
        $sql = "SHOW COLUMNS FROM sys_news_article LIKE :field";
        $stmt = $connection->executeQuery($sql, ['field' => $field]);
        $column = $stmt->fetchAssociative();

        if ($column) {
            echo "字段 '{$field}':\n";
            echo "  - 类型: {$column['Type']}\n";
            echo "  - 允许NULL: {$column['Null']}\n";
            echo "  - 默认值: " . ($column['Default'] ?? 'NULL') . "\n";
            echo "  - 额外信息: {$column['Extra']}\n\n";
        } else {
            echo "字段 '{$field}': 不存在\n\n";
        }
    }

    // 检查索引
    echo "=== 索引信息 ===\n";
    $sql = "SHOW INDEX FROM sys_news_article";
    $indexes = $connection->fetchAllAssociative($sql);

    foreach ($indexes as $index) {
        echo "索引名: {$index['Key_name']}, 字段: {$index['Column_name']}, 唯一: " . ($index['Non_unique'] == 0 ? '是' : '否') . "\n";
    }

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
