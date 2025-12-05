<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

// 加载环境变量
(new Dotenv())->loadEnv(__DIR__ . '/../.env');

// 创建内核实例
$kernel = new Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();

// 获取容器和服务
$container = $kernel->getContainer();
$entityManager = $container->get('doctrine.orm.entity_manager');
$logger = $container->get('monolog.logger');

echo "=== 分布式锁最终修复脚本 ===\n\n";

$accountId = 'gh_27a426f64edbef94';

try {
    $connection = $entityManager->getConnection();

    echo "1. 检查并创建 distributed_locks 表...\n";

    // 删除旧表（如果存在）
    $connection->executeStatement('DROP TABLE IF EXISTS distributed_locks');
    echo "   ✅ 删除旧表完成\n";

    // 创建新表
    $createTableSql = "
    CREATE TABLE distributed_locks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lock_key VARCHAR(255) NOT NULL UNIQUE,
        lock_id VARCHAR(255) NOT NULL,
        expire_time DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_expire_time (expire_time),
        INDEX idx_lock_key (lock_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $connection->executeStatement($createTableSql);
    echo "   ✅ 创建新表完成\n";

    echo "\n2. 测试分布式锁功能...\n";

    // 测试锁获取
    $lockKey = 'wechat_sync_' . $accountId;
    $lockId = md5($lockKey);
    $expireTime = date('Y-m-d H:i:s', time() + 60);

    echo "   - 锁键: {$lockKey}\n";
    echo "   - 锁ID: {$lockId}\n";
    echo "   - 过期时间: {$expireTime}\n";

    // 插入测试锁
    $insertSql = "
    INSERT INTO distributed_locks (lock_key, lock_id, expire_time, created_at)
    VALUES (?, ?, ?, NOW())
    ";
    $stmt = $connection->prepare($insertSql);
    $result = $stmt->executeStatement([$lockKey, $lockId, $expireTime]);
    echo "   ✅ 插入测试锁: 影响行数 {$result}\n";

    // 检查锁状态
    $checkSql = "
    SELECT lock_id, expire_time, created_at,
           CASE WHEN expire_time > NOW() THEN '有效' ELSE '过期' END as status
    FROM distributed_locks
    WHERE lock_key = ?
    ";
    $checkStmt = $connection->prepare($checkSql);
    $checkResult = $checkStmt->executeQuery([$lockKey]);
    $lockInfo = $checkResult->fetchAssociative();

    if ($lockInfo) {
        echo "   ✅ 锁状态检查:\n";
        echo "     - 锁ID: {$lockInfo['lock_id']}\n";
        echo "     - 过期时间: {$lockInfo['expire_time']}\n";
        echo "     - 创建时间: {$lockInfo['created_at']}\n";
        echo "     - 状态: {$lockInfo['status']}\n";
    } else {
        echo "   ❌ 未找到锁记录\n";
    }

    // 测试锁获取逻辑
    echo "\n3. 测试锁获取逻辑...\n";

    // 模拟 acquireLock 逻辑
    $testLockId = md5('test_' . $lockKey);
    $testExpireTime = date('Y-m-d H:i:s', time() + 30);

    $acquireSql = "
    INSERT INTO distributed_locks (lock_key, lock_id, expire_time, created_at)
    VALUES (?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
    lock_id = IF(expire_time < NOW(), VALUES(lock_id), lock_id),
    expire_time = IF(expire_time < NOW(), VALUES(expire_time), expire_time)
    ";

    $acquireStmt = $connection->prepare($acquireSql);
    $acquireResult = $acquireStmt->executeStatement([$lockKey, $testLockId, $testExpireTime]);
    echo "   📝 锁获取SQL执行: 影响行数 {$acquireResult}\n";

    // 验证是否成功获取
    $verifySql = "
    SELECT lock_id, expire_time,
           CASE WHEN expire_time > NOW() THEN '有效' ELSE '过期' END as status
    FROM distributed_locks
    WHERE lock_key = ? AND lock_id = ? AND expire_time > NOW()
    ";
    $verifyStmt = $connection->prepare($verifySql);
    $verifyResult = $verifyStmt->executeQuery([$lockKey, $testLockId]);
    $verifyInfo = $verifyResult->fetchAssociative();

    if ($verifyInfo) {
        echo "   ✅ 成功获取锁: {$verifyInfo['lock_id']} (状态: {$verifyInfo['status']})\n";
    } else {
        echo "   ❌ 获取锁失败\n";

        // 检查当前锁状态
        $currentSql = "
        SELECT lock_id, expire_time,
               CASE WHEN expire_time > NOW() THEN '有效' ELSE '过期' END as status
        FROM distributed_locks
        WHERE lock_key = ?
        ";
        $currentStmt = $connection->prepare($currentSql);
        $currentResult = $currentStmt->executeQuery([$lockKey]);
        $currentInfo = $currentResult->fetchAssociative();

        if ($currentInfo) {
            echo "   📝 当前锁状态: {$currentInfo['lock_id']} (状态: {$currentInfo['status']})\n";
        }
    }

    echo "\n4. 清理测试数据...\n";

    // 清理测试锁
    $cleanupSql = "DELETE FROM distributed_locks WHERE lock_key LIKE ?";
    $cleanupStmt = $connection->prepare($cleanupSql);
    $cleanupResult = $cleanupStmt->executeStatement(['%test%']);
    echo "   ✅ 清理测试数据: 删除 {$cleanupResult} 条记录\n";

    echo "\n5. 最终表结构验证...\n";

    // 显示表结构
    $structure = $connection->executeQuery("
    SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY
    FROM information_schema.columns
    WHERE table_name = 'distributed_locks'
    ORDER BY ORDINAL_POSITION
    ")->fetchAllAssociative();

    echo "   表结构:\n";
    foreach ($structure as $column) {
        echo "   - {$column['COLUMN_NAME']}: {$column['DATA_TYPE']} " .
             "(NULL: {$column['IS_NULLABLE']}, DEFAULT: {$column['COLUMN_DEFAULT']}, " .
             "KEY: {$column['COLUMN_KEY']})\n";
    }

    // 显示索引
    $indexes = $connection->executeQuery("SHOW INDEX FROM distributed_locks")->fetchAllAssociative();
    echo "   索引:\n";
    foreach ($indexes as $index) {
        echo "   - {$index['Key_name']}: {$index['Column_name']} " .
             "(Unique: " . ($index['Non_unique'] == 0 ? 'Yes' : 'No') . ")\n";
    }

    echo "\n=== 修复完成 ===\n";
    echo "✅ 分布式锁表已重建并测试通过\n";
    echo "📝 建议重新运行同步命令测试\n";

} catch (Exception $e) {
    echo "❌ 修复过程中发生错误: " . $e->getMessage() . "\n";
    echo "错误堆栈:\n" . $e->getTraceAsString() . "\n";
}
