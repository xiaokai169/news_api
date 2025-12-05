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

echo "=== 分布式锁详细调试脚本 ===\n\n";

$accountId = 'gh_27a426f64edbef94';
$lockKey = 'wechat_sync_' . $accountId;

echo "测试参数:\n";
echo "- 公众号ID: {$accountId}\n";
echo "- 锁键名: {$lockKey}\n";
echo "- 锁ID: " . md5($lockKey) . "\n";
echo "- 当前时间: " . date('Y-m-d H:i:s') . "\n\n";

// 1. 检查数据库连接
echo "1. 检查数据库连接:\n";
try {
    $connection = $entityManager->getConnection();
    $pingResult = $connection->executeQuery('SELECT 1')->fetchOne();
    echo "   ✅ 数据库连接正常: " . ($pingResult ? '成功' : '失败') . "\n";
} catch (Exception $e) {
    echo "   ❌ 数据库连接失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. 检查 distributed_locks 表是否存在
echo "\n2. 检查 distributed_locks 表结构:\n";
try {
    $tableExists = $connection->executeQuery(
        "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'distributed_locks'"
    )->fetchOne();

    if ($tableExists) {
        echo "   ✅ 表存在\n";

        // 检查表结构
        $columns = $connection->executeQuery(
            "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.columns WHERE table_name = 'distributed_locks' ORDER BY ORDINAL_POSITION"
        )->fetchAllAssociative();

        echo "   表结构:\n";
        foreach ($columns as $column) {
            echo "   - {$column['COLUMN_NAME']}: {$column['DATA_TYPE']} (NULL: {$column['IS_NULLABLE']}, DEFAULT: {$column['COLUMN_DEFAULT']})\n";
        }

        // 检查索引
        $indexes = $connection->executeQuery(
            "SHOW INDEX FROM distributed_locks"
        )->fetchAllAssociative();

        echo "   索引:\n";
        foreach ($indexes as $index) {
            echo "   - {$index['Key_name']}: {$index['Column_name']} (Unique: " . ($index['Non_unique'] == 0 ? 'Yes' : 'No') . ")\n";
        }
    } else {
        echo "   ❌ 表不存在\n";
    }
} catch (Exception $e) {
    echo "   ❌ 检查表结构失败: " . $e->getMessage() . "\n";
}

// 3. 检查当前锁状态
echo "\n3. 检查当前锁状态:\n";
try {
    $currentLocks = $connection->executeQuery(
        "SELECT lock_key, lock_id, expire_time, created_at FROM distributed_locks WHERE lock_key = ? OR lock_key LIKE ?",
        [$lockKey, 'wechat_sync_%']
    )->fetchAllAssociative();

    if (empty($currentLocks)) {
        echo "   📝 没有找到相关锁记录\n";
    } else {
        echo "   📝 找到 " . count($currentLocks) . " 个锁记录:\n";
        foreach ($currentLocks as $lock) {
            $isExpired = strtotime($lock['expire_time']) < time();
            echo "   - 锁键: {$lock['lock_key']}\n";
            echo "     锁ID: {$lock['lock_id']}\n";
            echo "     过期时间: {$lock['expire_time']} " . ($isExpired ? "(已过期)" : "(有效)") . "\n";
            echo "     创建时间: {$lock['created_at']}\n\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ 检查锁状态失败: " . $e->getMessage() . "\n";
}

// 4. 测试锁获取SQL语句
echo "\n4. 测试锁获取SQL语句:\n";
try {
    $lockId = md5($lockKey);
    $expireTime = date('Y-m-d H:i:s', time() + 60);

    echo "   准备执行的SQL:\n";
    echo "   INSERT INTO distributed_locks (lock_key, lock_id, expire_time, created_at)\n";
    echo "   VALUES (?, ?, ?, NOW())\n";
    echo "   ON DUPLICATE KEY UPDATE\n";
    echo "   lock_id = IF(expire_time < NOW(), VALUES(lock_id), lock_id),\n";
    echo "   expire_time = IF(expire_time < NOW(), VALUES(expire_time), expire_time)\n\n";

    echo "   参数:\n";
    echo "   - lock_key: {$lockKey}\n";
    echo "   - lock_id: {$lockId}\n";
    echo "   - expire_time: {$expireTime}\n\n";

    $stmt = $connection->prepare($sql);
    $result = $stmt->executeStatement([$lockKey, $lockId, $expireTime]);

    echo "   📝 SQL执行结果: 影响行数 {$result}\n";

    // 检查是否成功获取锁
    $checkSql = "SELECT lock_id, expire_time FROM distributed_locks WHERE lock_key = ? AND lock_id = ? AND expire_time > NOW()";
    $checkStmt = $connection->prepare($checkSql);
    $checkResult = $checkStmt->executeQuery([$lockKey, $lockId]);
    $currentLock = $checkResult->fetchAssociative();

    if ($currentLock) {
        echo "   ✅ 成功获取锁\n";
        echo "   - 锁ID: {$currentLock['lock_id']}\n";
        echo "   - 过期时间: {$currentLock['expire_time']}\n";
    } else {
        echo "   ❌ 获取锁失败\n";

        // 检查当前的锁状态
        $currentStatus = $connection->executeQuery(
            "SELECT lock_id, expire_time FROM distributed_locks WHERE lock_key = ?",
            [$lockKey]
        )->fetchAssociative();

        if ($currentStatus) {
            echo "   当前锁状态:\n";
            echo "   - 锁ID: {$currentStatus['lock_id']}\n";
            echo "   - 过期时间: {$currentStatus['expire_time']}\n";
            echo "   - 是否过期: " . (strtotime($currentStatus['expire_time']) < time() ? '是' : '否') . "\n";
        }
    }

} catch (Exception $e) {
    echo "   ❌ 测试锁获取失败: " . $e->getMessage() . "\n";
    echo "   错误堆栈:\n" . $e->getTraceAsString() . "\n";
}

// 5. 测试锁检查逻辑
echo "\n5. 测试锁检查逻辑:\n";
try {
    $checkSql = "SELECT lock_id FROM distributed_locks WHERE lock_key = ? AND expire_time > NOW()";
    $stmt = $connection->prepare($checkSql);
    $result = $stmt->executeQuery([$lockKey]);
    $lock = $result->fetchAssociative();

    $isLocked = $lock !== false;

    echo "   检查SQL: {$checkSql}\n";
    echo "   参数: {$lockKey}\n";
    echo "   结果: " . ($isLocked ? "锁存在" : "锁不存在") . "\n";

    if ($lock) {
        echo "   锁信息: {$lock['lock_id']}\n";
    }

} catch (Exception $e) {
    echo "   ❌ 测试锁检查失败: " . $e->getMessage() . "\n";
}

// 6. 测试锁清理
echo "\n6. 测试过期锁清理:\n";
try {
    $sql = "DELETE FROM distributed_locks WHERE expire_time < NOW()";
    $stmt = $connection->prepare($sql);
    $result = $stmt->executeStatement();

    echo "   清理SQL: {$sql}\n";
    echo "   清理结果: 删除了 {$result} 个过期锁\n";

} catch (Exception $e) {
    echo "   ❌ 测试锁清理失败: " . $e->getMessage() . "\n";
}

// 7. 测试锁释放
echo "\n7. 测试锁释放:\n";
try {
    $sql = "DELETE FROM distributed_locks WHERE lock_key = ?";
    $stmt = $connection->prepare($sql);
    $result = $stmt->executeStatement([$lockKey]);

    echo "   释放SQL: {$sql}\n";
    echo "   参数: {$lockKey}\n";
    echo "   释放结果: 删除了 {$result} 个锁记录\n";

} catch (Exception $e) {
    echo "   ❌ 测试锁释放失败: " . $e->getMessage() . "\n";
}

// 8. 最终状态检查
echo "\n8. 最终状态检查:\n";
try {
    $finalLocks = $connection->executeQuery(
        "SELECT lock_key, lock_id, expire_time, created_at FROM distributed_locks WHERE lock_key = ?",
        [$lockKey]
    )->fetchAllAssociative();

    if (empty($finalLocks)) {
        echo "   ✅ 测试锁已完全清理\n";
    } else {
        echo "   ⚠️  仍有锁记录存在:\n";
        foreach ($finalLocks as $lock) {
            echo "   - {$lock['lock_key']}: {$lock['lock_id']} (过期: {$lock['expire_time']})\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ 最终状态检查失败: " . $e->getMessage() . "\n";
}

echo "\n=== 调试完成 ===\n";
