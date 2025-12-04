<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;

$kernel = new Kernel('dev', true);
$kernel->boot();

$entityManager = $kernel->getContainer()->get('doctrine.orm.entity_manager');

echo "=== 实体映射调试 ===\n\n";

try {
    $classMetadata = $entityManager->getClassMetadata('App\Entity\DistributedLock');

    echo "表名: " . $classMetadata->getTableName() . "\n";
    echo "字段映射:\n";

    foreach ($classMetadata->getFieldNames() as $fieldName) {
        $fieldMapping = $classMetadata->getFieldMapping($fieldName);
        $columnName = $fieldMapping['columnName'] ?? 'N/A';
        $type = $fieldMapping['type'] ?? 'N/A';

        echo "  - $fieldName -> $columnName ($type)\n";
    }

    echo "\n索引信息:\n";
    if (isset($classMetadata->table['indexes'])) {
        foreach ($classMetadata->table['indexes'] as $indexName => $indexConfig) {
            echo "  - $indexName: " . implode(', ', $indexConfig['columns']) . "\n";
        }
    }

    echo "\n=== 验证数据库表结构 ===\n";

    $connection = $entityManager->getConnection();
    $structure = $connection->executeQuery("DESCRIBE distributed_locks");

    echo "实际表结构:\n";
    while ($row = $structure->fetchAssociative()) {
        echo "  - {$row['Field']}: {$row['Type']}\n";
    }

} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪: " . $e->getTraceAsString() . "\n";
}

$kernel->shutdown();
