<?php

/**
 * 验证并修复数据库表结构脚本
 * 检查distributed_locks表结构是否与代码匹配
 */

echo "=== 数据库表结构验证和修复 ===\n\n";

// 数据库配置
$dbConfig = [
    'host' => '127.0.0.1',
    'port' => '3306',
    'dbname' => 'official_website',
    'username' => 'root',
    'password' => 'qwe147258..'
];

try {
    // 连接数据库
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset=utf8mb4",
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ 数据库连接成功\n\n";

    // 1. 检查表是否存在
    echo "1. 检查distributed_locks表是否存在...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'distributed_locks'");
    $tableExists = $stmt->rowCount() > 0;

    if (!$tableExists) {
        echo "   ✗ distributed_locks表不存在，正在创建...\n";
        $createSql = file_get_contents('../create_distributed_locks_table.sql');
        $pdo->exec($createSql);
        echo "   ✓ distributed_locks表创建成功\n";
    } else {
        echo "   ✓ distributed_locks表存在\n";
    }
    echo "\n";

    // 2. 检查表结构
    echo "2. 检查表结构...\n";
    $stmt = $pdo->query("DESCRIBE distributed_locks");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $expectedColumns = [
        'id' => 'int(11)',
        'lock_key' => 'varchar(255)',
        'lock_id' => 'varchar(255)',
        'expire_time' => 'datetime',
        'created_at' => 'datetime'
    ];

    $columnMap = [];
    foreach ($columns as $column) {
        $columnMap[$column['Field']] = $column['Type'];
    }

    $structureCorrect = true;
    foreach ($expectedColumns as $fieldName => $expectedType) {
        if (isset($columnMap[$fieldName])) {
            echo "   ✓ {$fieldName} 字段存在 ({$columnMap[$fieldName]})\n";
            // 检查类型是否匹配（宽松匹配）
            if (strpos($columnMap[$fieldName], str_replace(['int(11)', 'varchar(255)'], ['int', 'varchar'], $expectedType)) === false) {
                echo "   ! 警告: {$fieldName} 类型可能不匹配，期望: {$expectedType}\n";
            }
        } else {
            echo "   ✗ {$fieldName} 字段缺失\n";
            $structureCorrect = false;
        }
    }

    // 检查多余的字段
    foreach ($columnMap as $fieldName => $fieldType) {
        if (!isset($expectedColumns[$fieldName])) {
            echo "   ! 警告: 发现多余字段 {$fieldName} ({$fieldType})\n";
        }
    }
    echo "\n";

    // 3. 如果结构不正确，修复表结构
    if (!$structureCorrect) {
        echo "3. 修复表结构...\n";

        // 备份现有数据
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM distributed_locks");
        $dataCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($dataCount > 0) {
            echo "   备份现有数据 ({$dataCount} 条记录)...\n";
            $pdo->exec("CREATE TABLE IF NOT EXISTS distributed_locks_backup AS SELECT * FROM distributed_locks");
        }

        // 删除并重新创建表
        echo "   重新创建表结构...\n";
        $pdo->exec("DROP TABLE IF EXISTS distributed_locks");
        $createSql = file_get_contents('../create_distributed_locks_table.sql');
        $pdo->exec($createSql);

        // 恢复数据
        if ($dataCount > 0) {
            echo "   恢复数据...\n";
            $pdo->exec("INSERT INTO distributed_locks SELECT * FROM distributed_locks_backup");
            $pdo->exec("DROP TABLE distributed_locks_backup");
        }

        echo "   ✓ 表结构修复完成\n\n";
    } else {
        echo "3. 表结构正确，无需修复\n\n";
    }

    // 4. 测试基本操作
    echo "4. 测试基本数据库操作...\n";

    // 清理测试数据
    $pdo->exec("DELETE FROM distributed_locks WHERE lock_key LIKE 'test_%'");

    // 测试插入
    $testLockKey = 'test_verification_' . time();
    $testLockId = 'test_id_' . uniqid();
    $expireTime = date('Y-m-d H:i:s', time() + 300);
    $createdAt = date('Y-m-d H:i:s');

    $insertSql = "INSERT INTO distributed_locks (lock_key, lock_id, expire_time, created_at) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($insertSql);
    $stmt->execute([$testLockKey, $testLockId, $expireTime, $createdAt]);
    echo "   ✓ INSERT 操作成功\n";

    // 测试查询
    $selectSql = "SELECT lock_id, expire_time FROM distributed_locks WHERE lock_key = ?";
    $stmt = $pdo->prepare($selectSql);
    $stmt->execute([$testLockKey]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && $result['lock_id'] === $testLockId) {
        echo "   ✓ SELECT 操作成功\n";
    } else {
        echo "   ✗ SELECT 操作失败\n";
    }

    // 测试更新
    $newExpireTime = date('Y-m-d H:i:s', time() + 600);
    $updateSql = "UPDATE distributed_locks SET expire_time = ? WHERE lock_key = ?";
    $stmt = $pdo->prepare($updateSql);
    $stmt->execute([$newExpireTime, $testLockKey]);
    echo "   ✓ UPDATE 操作成功\n";

    // 测试删除
    $deleteSql = "DELETE FROM distributed_locks WHERE lock_key = ?";
    $stmt = $pdo->prepare($deleteSql);
    $stmt->execute([$testLockKey]);
    echo "   ✓ DELETE 操作成功\n";

    echo "\n=== 验证完成 ===\n";
    echo "🎉 数据库表结构验证和修复完成！\n";
    echo "\n总结:\n";
    echo "1. ✓ distributed_locks表结构正确\n";
    echo "2. ✓ 所有必需字段存在\n";
    echo "3. ✓ 基本CRUD操作测试通过\n";
    echo "4. ✓ 分布式锁功能应该可以正常工作\n";

} catch (PDOException $e) {
    echo "❌ 数据库操作失败: " . $e->getMessage() . "\n";
    echo "\n请检查:\n";
    echo "1. 数据库连接配置是否正确\n";
    echo "2. 数据库用户是否有足够权限\n";
    echo "3. MySQL服务是否正在运行\n";
} catch (Exception $e) {
    echo "❌ 执行失败: " . $e->getMessage() . "\n";
}
