<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;

$kernel = new Kernel('dev', true);
$kernel->boot();

$entityManager = $kernel->getContainer()->get('doctrine.orm.entity_manager');

echo "=== 测试实体修复 ===\n\n";

try {
    // 1. 验证实体映射
    echo "1. 验证实体映射:\n";
    $classMetadata = $entityManager->getClassMetadata('App\Entity\DistributedLock');

    foreach ($classMetadata->getFieldNames() as $fieldName) {
        $fieldMapping = $classMetadata->getFieldMapping($fieldName);
        $columnName = $fieldMapping['columnName'] ?? 'N/A';
        $type = $fieldMapping['type'] ?? 'N/A';

        echo "  - $fieldName -> $columnName ($type)\n";
    }

    // 2. 验证数据库表结构
    echo "\n2. 验证数据库表结构:\n";
    $connection = $entityManager->getConnection();
    $structure = $connection->executeQuery("DESCRIBE distributed_locks");

    $dbColumns = [];
    while ($row = $structure->fetchAssociative()) {
        $dbColumns[] = $row['Field'];
        echo "  - {$row['Field']}: {$row['Type']}\n";
    }

    // 3. 检查列是否匹配
    echo "\n3. 检查列匹配情况:\n";
    $requiredColumns = ['expire_time', 'created_at', 'lock_key', 'lock_id', 'id'];

    foreach ($requiredColumns as $column) {
        if (in_array($column, $dbColumns)) {
            echo "  ✅ $column - 存在\n";
        } else {
            echo "  ❌ $column - 缺失\n";
        }
    }

    // 4. 测试实体操作
    echo "\n4. 测试实体操作:\n";

    // 创建测试锁
    $lock = new \App\Entity\DistributedLock();
    $lock->setLockKey('test_key_' . time());
    $lock->setLockId('test_id_' . uniqid());
    $lock->setExpireTime(new \DateTime('+1 hour'));

    $entityManager->persist($lock);
    $entityManager->flush();

    echo "  ✅ 实体保存成功，ID: " . $lock->getId() . "\n";

    // 查询测试
    $savedLock = $entityManager->getRepository(\App\Entity\DistributedLock::class)->find($lock->getId());
    if ($savedLock && $savedLock->getExpireTime()) {
        echo "  ✅ 实体查询成功，expire_time: " . $savedLock->getExpireTime()->format('Y-m-d H:i:s') . "\n";
    }

    // 清理测试数据
    $entityManager->remove($lock);
    $entityManager->flush();
    echo "  ✅ 测试数据清理完成\n";

    echo "\n=== 修复验证完成 ===\n";
    echo "如果所有测试都通过，现在应该可以成功运行 doctrine:migrations:diff\n";

} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    echo "错误详情: " . $e->getTraceAsString() . "\n";
}

$kernel->shutdown();
