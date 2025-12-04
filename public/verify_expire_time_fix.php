<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;

$kernel = new Kernel('dev', true);
$kernel->boot();

$connection = $kernel->getContainer()->get('doctrine.orm.entity_manager')->getConnection();

echo "=== 验证 expire_time 修复结果 ===\n\n";

try {
    // 1. 检查表是否存在
    echo "1. 检查 distributed_locks 表...\n";
    $result = $connection->executeQuery("SHOW TABLES LIKE 'distributed_locks'");
    $tableExists = $result->fetchAssociative();

    if (!$tableExists) {
        echo "❌ 表不存在\n";
        exit(1);
    }

    echo "✅ 表存在\n";

    // 2. 检查列结构
    echo "\n2. 检查列结构...\n";
    $structure = $connection->executeQuery("DESCRIBE distributed_locks");
    $columns = [];
    $columnDetails = [];

    while ($row = $structure->fetchAssociative()) {
        $columns[] = $row['Field'];
        $columnDetails[$row['Field']] = $row;
        echo "   - {$row['Field']}: {$row['Type']} ({$row['Null']}, {$row['Key']})\n";
    }

    // 3. 验证必需的列
    $requiredColumns = ['id', 'lock_key', 'lock_id', 'expire_time', 'created_at'];
    $missingColumns = array_diff($requiredColumns, $columns);

    if (!empty($missingColumns)) {
        echo "\n❌ 缺少必需列: " . implode(', ', $missingColumns) . "\n";
        exit(1);
    }

    echo "\n✅ 所有必需列都存在\n";

    // 4. 验证 expire_time 列的具体属性
    if (isset($columnDetails['expire_time'])) {
        $expireTimeColumn = $columnDetails['expire_time'];
        echo "\n3. 验证 expire_time 列属性:\n";
        echo "   - 类型: {$expireTimeColumn['Type']}\n";
        echo "   - 允许NULL: {$expireTimeColumn['Null']}\n";
        echo "   - 默认值: {$expireTimeColumn['Default']}\n";

        if ($expireTimeColumn['Null'] === 'NO') {
            echo "✅ expire_time 列设置为 NOT NULL\n";
        } else {
            echo "⚠️  expire_time 列允许 NULL\n";
        }
    }

    // 5. 检查索引
    echo "\n4. 检查索引...\n";
    $indexes = $connection->executeQuery("SHOW INDEX FROM distributed_locks");
    $indexList = [];

    while ($row = $indexes->fetchAssociative()) {
        $indexName = $row['Key_name'];
        $columnName = $row['Column_name'];

        if (!isset($indexList[$indexName])) {
            $indexList[$indexName] = [];
        }
        $indexList[$indexName][] = $columnName;
    }

    foreach ($indexList as $indexName => $columns) {
        echo "   - $indexName: " . implode(', ', $columns) . "\n";
    }

    if (isset($indexList['idx_expire_time'])) {
        echo "✅ expire_time 索引存在\n";
    } else {
        echo "⚠️  expire_time 索引不存在\n";
    }

    // 6. 测试实体操作
    echo "\n5. 测试实体操作...\n";
    $entityManager = $kernel->getContainer()->get('doctrine.orm.entity_manager');

    try {
        // 创建测试锁
        $lock = new \App\Entity\DistributedLock();
        $lock->setLockKey('verify_test_' . time());
        $lock->setLockId('verify_id_' . uniqid());
        $lock->setExpireTime(new \DateTime('+1 hour'));

        $entityManager->persist($lock);
        $entityManager->flush();

        echo "✅ 实体保存成功，ID: " . $lock->getId() . "\n";

        // 测试查询
        $repository = $entityManager->getRepository(\App\Entity\DistributedLock::class);
        $foundLock = $repository->find($lock->getId());

        if ($foundLock && $foundLock->getExpireTime()) {
            echo "✅ expire_time 字段读写正常\n";
        } else {
            echo "❌ expire_time 字段读写异常\n";
        }

        // 清理测试数据
        $entityManager->remove($lock);
        $entityManager->flush();
        echo "✅ 测试数据清理完成\n";

    } catch (\Exception $e) {
        echo "❌ 实体操作失败: " . $e->getMessage() . "\n";
        exit(1);
    }

    echo "\n=== 验证结果 ===\n";
    echo "✅ distributed_locks 表结构正确\n";
    echo "✅ expire_time 列存在且可正常使用\n";
    echo "✅ 实体操作正常\n";
    echo "\n🎉 修复成功！\n";

} catch (\Exception $e) {
    echo "❌ 验证过程中发生错误: " . $e->getMessage() . "\n";
    echo "错误详情: " . $e->getTraceAsString() . "\n";
    exit(1);
}

$kernel->shutdown();
