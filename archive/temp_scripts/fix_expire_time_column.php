<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;

$kernel = new Kernel('dev', true);
$kernel->boot();

$connection = $kernel->getContainer()->get('doctrine.orm.entity_manager')->getConnection();

echo "=== 修复 expire_time 列问题 ===\n\n";

try {
    // 1. 检查表是否存在
    echo "1. 检查 distributed_locks 表状态...\n";
    $result = $connection->executeQuery("SHOW TABLES LIKE 'distributed_locks'");
    $tableExists = $result->fetchAssociative();

    if (!$tableExists) {
        echo "❌ 表不存在，正在创建...\n";

        // 创建表
        $sql = "
        CREATE TABLE `distributed_locks` (
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
        echo "✅ 表创建成功\n";

    } else {
        echo "✅ 表存在\n";

        // 2. 检查列是否存在
        echo "\n2. 检查列结构...\n";
        $structure = $connection->executeQuery("DESCRIBE distributed_locks");
        $columns = [];
        while ($row = $structure->fetchAssociative()) {
            $columns[] = $row['Field'];
            echo "   - {$row['Field']}: {$row['Type']}\n";
        }

        if (!in_array('expire_time', $columns)) {
            echo "\n❌ expire_time 列不存在，正在添加...\n";

            // 添加缺失的列
            $connection->executeStatement(
                "ALTER TABLE distributed_locks ADD COLUMN expire_time datetime NOT NULL"
            );

            // 添加索引
            $connection->executeStatement(
                "ALTER TABLE distributed_locks ADD INDEX idx_expire_time (expire_time)"
            );

            echo "✅ expire_time 列添加成功\n";

        } else {
            echo "\n✅ expire_time 列已存在\n";
        }

        // 检查其他必需的列
        $requiredColumns = ['id', 'lock_key', 'lock_id', 'created_at'];
        $missingColumns = array_diff($requiredColumns, $columns);

        if (!empty($missingColumns)) {
            echo "\n⚠️  缺少其他列: " . implode(', ', $missingColumns) . "\n";

            // 重建表以确保结构正确
            echo "正在重建表...\n";
            $connection->executeStatement("DROP TABLE distributed_locks");

            $sql = "
            CREATE TABLE `distributed_locks` (
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
            echo "✅ 表重建成功\n";
        }
    }

    // 3. 修复迁移状态
    echo "\n3. 修复迁移状态...\n";
    $result = $connection->executeQuery("SHOW TABLES LIKE 'doctrine_migration_versions'");
    $migrationTableExists = $result->fetchAssociative();

    if ($migrationTableExists) {
        // 清理冲突的迁移记录
        $problematicMigrations = ['20251127015843', '20251204075200'];

        foreach ($problematicMigrations as $version) {
            $result = $connection->executeQuery(
                "SELECT COUNT(*) as cnt FROM doctrine_migration_versions WHERE version = ?",
                [$version]
            );
            $exists = $result->fetchAssociative();

            if ($exists['cnt'] > 0) {
                echo "删除迁移记录: $version\n";
                $connection->executeStatement(
                    "DELETE FROM doctrine_migration_versions WHERE version = ?",
                    [$version]
                );
            }
        }

        // 标记正确的迁移为已执行
        echo "标记迁移 20251204075200 为已执行\n";
        $connection->executeStatement(
            "INSERT INTO doctrine_migration_versions (version, executed_at) VALUES (?, NOW())",
            ['20251204075200']
        );
        echo "✅ 迁移状态修复完成\n";
    }

    // 4. 测试实体操作
    echo "\n4. 测试实体操作...\n";
    $entityManager = $kernel->getContainer()->get('doctrine.orm.entity_manager');

    try {
        // 创建测试锁
        $lock = new \App\Entity\DistributedLock();
        $lock->setLockKey('test_key_' . time());
        $lock->setLockId('test_id_' . uniqid());
        $lock->setExpireTime(new \DateTime('+1 hour'));

        $entityManager->persist($lock);
        $entityManager->flush();

        echo "✅ 实体保存成功，ID: " . $lock->getId() . "\n";

        // 清理测试数据
        $entityManager->remove($lock);
        $entityManager->flush();
        echo "✅ 测试数据清理完成\n";

    } catch (\Exception $e) {
        echo "❌ 实体操作失败: " . $e->getMessage() . "\n";
    }

    echo "\n=== 修复完成 ===\n";
    echo "distributed_locks 表现在应该包含正确的 expire_time 列。\n";

} catch (\Exception $e) {
    echo "❌ 修复过程中发生错误: " . $e->getMessage() . "\n";
    echo "错误详情: " . $e->getTraceAsString() . "\n";
}

$kernel->shutdown();
