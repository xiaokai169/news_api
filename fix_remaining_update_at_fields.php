<?php
require_once 'vendor/autoload.php';

use Doctrine\ORM\Tools\SchemaValidator;
use Doctrine\ORM\EntityManager;
use App\Kernel;

// 创建 Symfony 内核
$kernel = new Kernel('dev', true);
$kernel->boot();

// 获取 EntityManager
$entityManager = $kernel->getContainer()->get('doctrine.orm.entity_manager');
$connection = $entityManager->getConnection();

echo "=== 修复剩余的 update_at 字段问题 ===\n\n";

try {
    // 1. 修复 article_read_statistics 表
    echo "1. 修复 article_read_statistics 表...\n";
    $result = $connection->fetchAllAssociative("SHOW COLUMNS FROM article_read_statistics LIKE 'update_at'");

    if (!empty($result)) {
        echo "   发现 update_at 字段，开始修复...\n";

        // 检查是否已有 updated_at 字段
        $result = $connection->fetchAllAssociative("SHOW COLUMNS FROM article_read_statistics LIKE 'updated_at'");
        if (empty($result)) {
            // 添加 updated_at 字段
            $connection->executeStatement("ALTER TABLE article_read_statistics ADD COLUMN updated_at DATETIME NULL COMMENT '更新时间'");
            echo "   添加 updated_at 字段成功\n";
        }

        // 复制数据
        $connection->executeStatement("UPDATE article_read_statistics SET updated_at = update_at WHERE update_at IS NOT NULL");
        echo "   数据复制完成\n";

        // 删除 update_at 字段
        $connection->executeStatement("ALTER TABLE article_read_statistics DROP COLUMN update_at");
        echo "   删除 update_at 字段成功\n";
    } else {
        echo "   未发现 update_at 字段\n";
    }

    // 2. 修复 sys_news_article 表
    echo "\n2. 修复 sys_news_article 表...\n";
    $result = $connection->fetchAllAssociative("SHOW COLUMNS FROM sys_news_article LIKE 'update_at'");

    if (!empty($result)) {
        echo "   发现 update_at 字段，开始修复...\n";

        // 检查是否已有 updated_at 字段
        $result = $connection->fetchAllAssociative("SHOW COLUMNS FROM sys_news_article LIKE 'updated_at'");
        if (empty($result)) {
            // 添加 updated_at 字段
            $connection->executeStatement("ALTER TABLE sys_news_article ADD COLUMN updated_at DATETIME NULL COMMENT '更新时间'");
            echo "   添加 updated_at 字段成功\n";
        }

        // 复制数据
        $connection->executeStatement("UPDATE sys_news_article SET updated_at = update_at WHERE update_at IS NOT NULL");
        echo "   数据复制完成\n";

        // 删除 update_at 字段
        $connection->executeStatement("ALTER TABLE sys_news_article DROP COLUMN update_at");
        echo "   删除 update_at 字段成功\n";
    } else {
        echo "   未发现 update_at 字段\n";
    }

    echo "\n=== 数据库表修复完成 ===\n";

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

$kernel->shutdown();
