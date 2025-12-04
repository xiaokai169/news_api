<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;

// 创建Symfony应用实例
$kernel = new Kernel('dev', true);
$kernel->boot();

// 获取Doctrine实体管理器
$entityManager = $kernel->getContainer()->get('doctrine.orm.entity_manager');

// 获取数据库连接
$connection = $entityManager->getConnection();

try {
    echo "正在创建 distributed_locks 表...\n";

    // 创建表的SQL
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

    echo "✅ distributed_locks 表创建成功！\n";

    // 验证表是否创建成功
    $result = $connection->executeQuery("SHOW TABLES LIKE 'distributed_locks'");
    $tableExists = $result->fetchAssociative();

    if ($tableExists) {
        echo "✅ 表验证成功，distributed_locks 表已存在\n";

        // 显示表结构
        echo "\n表结构信息：\n";
        $structure = $connection->executeQuery("DESCRIBE distributed_locks");
        while ($row = $structure->fetchAssociative()) {
            echo sprintf("- %s: %s %s %s %s\n",
                $row['Field'],
                $row['Type'],
                $row['Null'],
                $row['Key'],
                $row['Default']
            );
        }
    } else {
        echo "❌ 表验证失败\n";
    }

} catch (\Exception $e) {
    echo "❌ 创建表时发生错误: " . $e->getMessage() . "\n";
    echo "错误详情: " . $e->getTraceAsString() . "\n";
} finally {
    $kernel->shutdown();
}
