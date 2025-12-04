<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;

// 创建Symfony应用实例
$kernel = new Kernel('dev', true);
$kernel->boot();

// 获取Doctrine实体管理器和数据库连接
$entityManager = $kernel->getContainer()->get('doctrine.orm.entity_manager');
$connection = $entityManager->getConnection();

echo "=== DistributedLock 表诊断报告 ===\n\n";

// 1. 检查表是否存在
echo "1. 检查 distributed_locks 表是否存在:\n";
try {
    $result = $connection->executeQuery("SHOW TABLES LIKE 'distributed_locks'");
    $tableExists = $result->fetchAssociative();

    if ($tableExists) {
        echo "✅ distributed_locks 表存在\n";

        // 显示表结构
        echo "\n表结构信息:\n";
        $structure = $connection->executeQuery("DESCRIBE distributed_locks");
        while ($row = $structure->fetchAssociative()) {
            echo sprintf("  - %s: %s %s %s %s\n",
                $row['Field'],
                $row['Type'],
                $row['Null'],
                $row['Key'],
                $row['Default'] ?? 'NULL'
            );
        }

        // 检查索引
        echo "\n索引信息:\n";
        $indexes = $connection->executeQuery("SHOW INDEX FROM distributed_locks");
        while ($row = $indexes->fetchAssociative()) {
            echo sprintf("  - %s: %s (%s)\n",
                $row['Key_name'],
                $row['Column_name'],
                $row['Index_type']
            );
        }

        // 检查数据
        $count = $connection->executeQuery("SELECT COUNT(*) as count FROM distributed_locks")->fetchAssociative();
        echo "\n表中的记录数: " . $count['count'] . "\n";

    } else {
        echo "❌ distributed_locks 表不存在\n";
    }
} catch (\Exception $e) {
    echo "❌ 检查表时发生错误: " . $e->getMessage() . "\n";
}

echo "\n";

// 2. 检查迁移表
echo "2. 检查迁移表状态:\n";
try {
    $result = $connection->executeQuery("SHOW TABLES LIKE 'doctrine_migration_versions'");
    $migrationTableExists = $result->fetchAssociative();

    if ($migrationTableExists) {
        echo "✅ doctrine_migration_versions 表存在\n";

        // 检查相关的迁移版本
        $migrations = $connection->executeQuery("
            SELECT version, executed_at FROM doctrine_migration_versions
            WHERE version LIKE '%20251204075200%' OR version LIKE '%20251127015843%'
            ORDER BY version
        ");

        echo "\n相关迁移记录:\n";
        while ($row = $migrations->fetchAssociative()) {
            echo sprintf("  - %s: 执行于 %s\n",
                $row['version'],
                $row['executed_at'] ?? '未执行'
            );
        }

        if ($migrations->rowCount() === 0) {
            echo "  (没有找到相关的迁移记录)\n";
        }
    } else {
        echo "❌ doctrine_migration_versions 表不存在\n";
    }
} catch (\Exception $e) {
    echo "❌ 检查迁移表时发生错误: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. 检查实体映射
echo "3. 检查实体映射:\n";
try {
    $classMetadata = $entityManager->getClassMetadata('App\Entity\DistributedLock');
    echo "✅ DistributedLock 实体映射正常\n";
    echo "  - 表名: " . $classMetadata->getTableName() . "\n";
    echo "  - 字段数: " . count($classMetadata->getFieldNames()) . "\n";

    echo "\n字段映射:\n";
    foreach ($classMetadata->getFieldNames() as $fieldName) {
        $fieldMapping = $classMetadata->getFieldMapping($fieldName);
        echo sprintf("  - %s: %s\n", $fieldName, $fieldMapping['type'] ?? 'unknown');
    }
} catch (\Exception $e) {
    echo "❌ 检查实体映射时发生错误: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. 尝试创建表（如果不存在）
echo "4. 尝试创建 distributed_locks 表:\n";
try {
    $schemaManager = $connection->createSchemaManager();
    $tables = $schemaManager->listTableNames();

    if (!in_array('distributed_locks', $tables)) {
        echo "表不存在，正在创建...\n";

        // 从实体生成Schema
        $schema = $schemaManager->createSchema();
        $table = $schema->createTable('distributed_locks');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('lock_key', 'string', ['length' => 255]);
        $table->addColumn('lock_id', 'string', ['length' => 255]);
        $table->addColumn('expire_time', 'datetime');
        $table->addColumn('created_at', 'datetime');
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['lock_key'], 'idx_lock_key');
        $table->addIndex(['expire_time'], 'idx_expire_time');

        $schemaManager->createTable($table);
        echo "✅ 表创建成功\n";
    } else {
        echo "✅ 表已存在，无需创建\n";
    }
} catch (\Exception $e) {
    echo "❌ 创建表时发生错误: " . $e->getMessage() . "\n";
}

echo "\n=== 诊断完成 ===\n";

$kernel->shutdown();
