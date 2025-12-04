<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;

$kernel = new Kernel('dev', true);
$kernel->boot();

$connection = $kernel->getContainer()->get('doctrine.orm.entity_manager')->getConnection();

echo "快速检查 distributed_locks 表状态:\n";

try {
    $result = $connection->executeQuery("SHOW TABLES LIKE 'distributed_locks'");
    $tableExists = $result->fetchAssociative();

    if ($tableExists) {
        echo "✅ 表存在\n";

        // 检查表结构
        $structure = $connection->executeQuery("DESCRIBE distributed_locks");
        echo "\n表结构:\n";
        while ($row = $structure->fetchAssociative()) {
            echo "- {$row['Field']}: {$row['Type']}\n";
        }

        // 检查记录数
        $count = $connection->executeQuery("SELECT COUNT(*) as cnt FROM distributed_locks")->fetchAssociative();
        echo "\n记录数: {$count['cnt']}\n";

    } else {
        echo "❌ 表不存在\n";

        // 尝试创建表
        echo "\n尝试创建表...\n";
        $sql = "
        CREATE TABLE IF NOT EXISTS `distributed_locks` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `lock_key` varchar(255) NOT NULL,
          `lock_id` varchar(255) NOT NULL,
          `expire_time` datetime NOT NULL,
          `created_at` datetime NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `idx_lock_key` (`lock_key`),
          KEY `idx_expire_time` (`expire_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        $connection->executeStatement($sql);
        echo "✅ 表创建完成\n";
    }

} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
}

$kernel->shutdown();
