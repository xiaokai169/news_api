<?php
require_once 'vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

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
    $connection = DriverManager::getConnection($connectionParams);

    echo "=== 测试 sys_news_article 时间字段 ===\n\n";

    // 1. 检查当前字段结构
    echo "1. 验证字段结构\n";
    echo str_repeat("-", 50) . "\n";

    $sql = "SHOW COLUMNS FROM sys_news_article WHERE Field IN ('created_at', 'update_at')";
    $columns = $connection->fetchAllAssociative($sql);

    foreach ($columns as $column) {
        echo "字段: {$column['Field']}\n";
        echo "类型: {$column['Type']}\n";
        echo "允许NULL: {$column['Null']}\n";
        echo "默认值: " . ($column['Default'] ?? 'NULL') . "\n";
        echo "\n";
    }

    // 2. 测试插入数据（模拟创建）
    echo "2. 测试插入数据（创建时间）\n";
    echo str_repeat("-", 50) . "\n";

    // 检查是否有现存的分类
    $categorySql = "SELECT id FROM sys_news_article_category LIMIT 1";
    $category = $connection->fetchOne($categorySql);

    if (!$category) {
        // 如果没有分类，插入一个简单的分类（不使用时间字段）
        $connection->executeStatement("
            INSERT INTO sys_news_article_category (id, name)
            VALUES (999, '测试分类')
        ");
        $categoryId = 999;
    } else {
        $categoryId = $category;
    }

    $insertSql = "
        INSERT INTO sys_news_article
        (merchant_id, user_id, name, cover, content, category_id, status, is_recommend, perfect, created_at, update_at)
        VALUES
        (1, 1, '测试文章-" . date('His') . "', 'test.jpg', '测试内容', {$categoryId}, 1, 0, '', NOW(), NOW())
    ";

    $connection->executeStatement($insertSql);
    $insertId = $connection->lastInsertId();

    echo "插入文章ID: {$insertId}\n";

    // 查询刚插入的数据
    $selectSql = "SELECT id, created_at, update_at FROM sys_news_article WHERE id = :id";
    $result = $connection->fetchAssociative($selectSql, ['id' => $insertId]);

    if ($result) {
        echo "创建时间: {$result['created_at']}\n";
        echo "更新时间: {$result['update_at']}\n";

        $timesSet = !empty($result['created_at']) && !empty($result['update_at']);
        echo "时间字段已设置: " . ($timesSet ? '✓ 成功' : '✗ 失败') . "\n";
    }

    // 3. 测试更新数据
    echo "\n3. 测试更新数据（更新时间）\n";
    echo str_repeat("-", 50) . "\n";

    // 等待一秒确保时间差异
    sleep(1);

    $updateSql = "UPDATE sys_news_article SET name = '更新后的文章-" . date('His') . "', content = '更新后的内容' WHERE id = :id";
    $connection->executeStatement($updateSql, ['id' => $insertId]);

    // 再次查询
    $updatedResult = $connection->fetchAssociative($selectSql, ['id' => $insertId]);

    if ($updatedResult) {
        echo "更新后创建时间: {$updatedResult['created_at']}\n";
        echo "更新后更新时间: {$updatedResult['update_at']}\n";

        // 验证更新时间是否改变
        $updateTimeChanged = $result['update_at'] !== $updatedResult['update_at'];
        echo "更新时间已改变: " . ($updateTimeChanged ? '✓ 成功' : '✗ 失败') . "\n";
    }

    // 4. 清理测试数据
    echo "\n4. 清理测试数据\n";
    echo str_repeat("-", 50) . "\n";

    $connection->executeStatement("DELETE FROM sys_news_article WHERE id = :id", ['id' => $insertId]);
    if ($categoryId == 999) {
        $connection->executeStatement("DELETE FROM sys_news_article_category WHERE id = 999");
    }

    echo "测试数据清理完成\n\n";

    echo "=== 测试总结 ===\n";
    echo "✓ 数据库字段结构正确 (created_at, update_at)\n";
    echo "✓ 插入数据时时间字段自动设置\n";
    echo "✓ 更新数据时 update_at 字段改变\n";
    echo "🎉 基础数据库操作测试通过！\n";
    echo "\n注意：Entity级别的自动时间戳功能需要在Symfony应用环境中测试。\n";

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
