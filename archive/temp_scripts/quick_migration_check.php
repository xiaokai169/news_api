<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;

$kernel = new Kernel('dev', true);
$kernel->boot();

$connection = $kernel->getContainer()->get('doctrine.orm.entity_manager')->getConnection();

echo "=== 快速迁移检查 ===\n";

try {
    // 1. 检查表是否存在
    $result = $connection->executeQuery("SHOW TABLES LIKE 'distributed_locks'");
    $tableExists = $result->fetchAssociative();

    if ($tableExists) {
        echo "✅ distributed_locks 表存在\n";

        // 2. 检查列
        $structure = $connection->executeQuery("DESCRIBE distributed_locks");
        $columns = [];
        while ($row = $structure->fetchAssociative()) {
            $columns[] = $row['Field'];
        }

        echo "列: " . implode(', ', $columns) . "\n";

        if (in_array('expire_time', $columns)) {
            echo "✅ expire_time 列存在\n";
        } else {
            echo "❌ expire_time 列不存在\n";
        }
    } else {
        echo "❌ distributed_locks 表不存在\n";
    }

    // 3. 检查迁移状态
    $result = $connection->executeQuery("SHOW TABLES LIKE 'doctrine_migration_versions'");
    $migrationTableExists = $result->fetchAssociative();

    if ($migrationTableExists) {
        $result = $connection->executeQuery("
            SELECT version, executed_at
            FROM doctrine_migration_versions
            WHERE version IN ('20251127015843', '20251204075200')
            ORDER BY version
        ");

        echo "\n迁移状态:\n";
        while ($row = $result->fetchAssociative()) {
            echo "  {$row['version']}: {$row['executed_at']}\n";
        }
    }

} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

$kernel->shutdown();
