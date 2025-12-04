<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;

$kernel = new Kernel('dev', true);
$kernel->boot();

$connection = $kernel->getContainer()->get('doctrine.orm.entity_manager')->getConnection();

echo "=== 迁移冲突诊断脚本 ===\n\n";

try {
    // 1. 检查 distributed_locks 表是否存在
    echo "1. 检查 distributed_locks 表状态...\n";
    $result = $connection->executeQuery("SHOW TABLES LIKE 'distributed_locks'");
    $tableExists = $result->fetchAssociative();

    if ($tableExists) {
        echo "✅ 表存在\n";

        // 检查表结构
        echo "\n2. 检查表结构...\n";
        $structure = $connection->executeQuery("DESCRIBE distributed_locks");
        $columns = [];
        while ($row = $structure->fetchAssociative()) {
            $columns[] = $row['Field'];
            echo "   - {$row['Field']}: {$row['Type']} {$row['Null']} {$row['Key']}\n";
        }

        // 检查是否缺少 expire_time 列
        if (!in_array('expire_time', $columns)) {
            echo "\n❌ 关键问题：缺少 'expire_time' 列！\n";
            echo "当前列: " . implode(', ', $columns) . "\n";

            // 检查是否有类似的列名
            $similarColumns = array_filter($columns, function($col) {
                return strpos($col, 'expire') !== false || strpos($col, 'time') !== false;
            });
            if (!empty($similarColumns)) {
                echo "发现相似列名: " . implode(', ', $similarColumns) . "\n";
            }
        } else {
            echo "\n✅ 'expire_time' 列存在\n";
        }
    } else {
        echo "❌ 表不存在\n";
    }

    // 3. 检查迁移状态
    echo "\n3. 检查迁移表状态...\n";
    $result = $connection->executeQuery("SHOW TABLES LIKE 'doctrine_migration_versions'");
    $migrationTableExists = $result->fetchAssociative();

    if ($migrationTableExists) {
        echo "✅ 迁移表存在\n";

        // 检查相关迁移记录
        $relevantMigrations = ['20251127015843', '20251204075200'];

        foreach ($relevantMigrations as $version) {
            $result = $connection->executeQuery(
                "SELECT version, executed_at FROM doctrine_migration_versions WHERE version = ?",
                [$version]
            );
            $migration = $result->fetchAssociative();

            if ($migration) {
                echo "   - 迁移 $version: ✅ 已执行 ({$migration['executed_at']})\n";
            } else {
                echo "   - 迁移 $version: ❌ 未执行\n";
            }
        }

        // 检查所有分布式锁相关的迁移
        echo "\n   所有分布式锁相关迁移:\n";
        $result = $connection->executeQuery("
            SELECT version, executed_at
            FROM doctrine_migration_versions
            WHERE version LIKE '%distributed%' OR version IN ('20251127015843', '20251204075200')
            ORDER BY version
        ");
        while ($row = $result->fetchAssociative()) {
            echo "     - {$row['version']}: {$row['executed_at']}\n";
        }
    } else {
        echo "❌ 迁移表不存在\n";
    }

    // 4. 检查是否有其他相关表
    echo "\n4. 检查其他可能的表...\n";
    $result = $connection->executeQuery("SHOW TABLES LIKE '%lock%'");
    $lockTables = [];
    while ($row = $result->fetchAssociative()) {
        $tableName = array_values($row)[0];
        $lockTables[] = $tableName;
        echo "   - $tableName\n";
    }

    if (empty($lockTables)) {
        echo "   (无锁相关表)\n";
    }

    // 5. 诊断结论
    echo "\n=== 诊断结论 ===\n";

    if (!$tableExists) {
        echo "问题：distributed_locks 表不存在\n";
        echo "原因：迁移 20251127015843 删除了表，但迁移 20251204075200 可能没有正确执行\n";
        echo "建议：重新执行迁移 20251204075200\n";
    } elseif (!in_array('expire_time', $columns)) {
        echo "问题：distributed_locks 表缺少 expire_time 列\n";
        echo "原因：表可能是手动创建的，或者迁移执行不完整\n";
        echo "建议：删除现有表，重新执行迁移\n";
    } else {
        echo "表结构看起来正确，可能是实体映射或其他问题\n";
    }

} catch (\Exception $e) {
    echo "❌ 诊断过程中发生错误: " . $e->getMessage() . "\n";
    echo "错误详情: " . $e->getTraceAsString() . "\n";
}

$kernel->shutdown();
