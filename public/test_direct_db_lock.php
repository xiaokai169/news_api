<?php

// 直接测试数据库连接和SQL查询
try {
    // 数据库连接参数
    $host = '127.0.0.1';
    $port = '3306';
    $dbname = 'official_website';
    $username = 'root';
    $password = 'qwe147258..';

    // 创建PDO连接
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== 直接数据库分布式锁测试 ===\n\n";

    // 测试1: 检查表结构
    echo "1. 检查 distributed_locks 表结构:\n";
    $stmt = $pdo->query("DESCRIBE distributed_locks");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "  - {$column['Field']}: {$column['Type']}\n";
    }
    echo "\n";

    // 测试2: 尝试插入锁记录
    echo "2. 测试插入锁记录:\n";
    $lockKey = 'wechat_sync_test_account_001';
    $lockId = md5($lockKey);
    $expireTime = date('Y-m-d H:i:s', time() + 60);

    $sql = "INSERT INTO distributed_locks (lockKey, lockId, expire_time, created_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            lockId = IF(expire_time < NOW(), VALUES(lockId), lockId),
            expire_time = IF(expire_time < NOW(), VALUES(expire_time), expire_time)";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$lockKey, $lockId, $expireTime]);
    echo "插入结果: " . ($result ? "成功" : "失败") . "\n";
    echo "影响行数: " . $stmt->rowCount() . "\n\n";

    // 测试3: 检查锁状态
    echo "3. 检查锁状态:\n";
    $checkSql = "SELECT lockId, expire_time FROM distributed_locks WHERE lockKey = ? AND lockId = ? AND expire_time > NOW()";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$lockKey, $lockId]);
    $currentLock = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($currentLock) {
        echo "锁状态: 已锁定\n";
        echo "锁ID: {$currentLock['lockId']}\n";
        echo "过期时间: {$currentLock['expire_time']}\n";
        $acquired = $currentLock['lockId'] === $lockId;
        echo "锁获取: " . ($acquired ? "成功" : "失败") . "\n";
    } else {
        echo "锁状态: 未锁定\n";
    }
    echo "\n";

    // 测试4: 释放锁
    echo "4. 释放锁:\n";
    $deleteSql = "DELETE FROM distributed_locks WHERE lockKey = ?";
    $deleteStmt = $pdo->prepare($deleteSql);
    $deleteResult = $deleteStmt->execute([$lockKey]);
    echo "释放结果: " . ($deleteResult ? "成功" : "失败") . "\n";
    echo "删除行数: " . $deleteStmt->rowCount() . "\n\n";

    // 测试5: 再次检查锁状态
    echo "5. 释放后检查锁状态:\n";
    $checkStmt->execute([$lockKey, $lockId]);
    $lockAfter = $checkStmt->fetch(PDO::FETCH_ASSOC);
    echo "释放后锁状态: " . ($lockAfter ? "仍存在" : "已清除") . "\n\n";

    echo "=== 测试完成 ===\n";
    echo "✅ 所有SQL查询执行成功，字段映射问题已修复！\n";

} catch (PDOException $e) {
    echo "❌ 数据库错误: " . $e->getMessage() . "\n";
    echo "错误代码: " . $e->getCode() . "\n";
} catch (Exception $e) {
    echo "❌ 一般错误: " . $e->getMessage() . "\n";
}
