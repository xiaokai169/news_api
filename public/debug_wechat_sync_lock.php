<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use App\Service\DistributedLockService;

// 创建 Symfony 应用实例
$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

// 获取容器和服务
$container = $kernel->getContainer();
$entityManager = $container->get('doctrine.orm.entity_manager');
$logger = $container->get('logger');

// 创建分布式锁服务
$lockService = new DistributedLockService($entityManager, $logger);

$accountId = 'gh_27a426f64edbef94';
$lockKey = 'wechat_sync_' . $accountId;

echo "=== 微信同步分布式锁调试 ===\n\n";

echo "公众号ID: $accountId\n";
echo "锁键名: $lockKey\n\n";

// 1. 检查表是否存在
echo "1. 检查 distributed_locks 表结构:\n";
try {
    $connection = $entityManager->getConnection();
    $schemaManager = $connection->createSchemaManager();

    if ($schemaManager->tablesExist(['distributed_locks'])) {
        echo "✅ distributed_locks 表存在\n";

        // 检查表结构
        $table = $schemaManager->introspectTable('distributed_locks');
        echo "表字段:\n";
        foreach ($table->getColumns() as $column) {
            echo "  - {$column->getName()}: {$column->getType()->getName()}\n";
        }
    } else {
        echo "❌ distributed_locks 表不存在\n";
    }
} catch (Exception $e) {
    echo "❌ 检查表结构时出错: " . $e->getMessage() . "\n";
}
echo "\n";

// 2. 检查当前锁状态
echo "2. 检查当前锁状态:\n";
try {
    $isLocked = $lockService->isLocked($lockKey);
    echo "锁状态: " . ($isLocked ? "🔒 已锁定" : "🔓 未锁定") . "\n";

    // 直接查询数据库中的锁记录
    $connection = $entityManager->getConnection();
    $sql = "SELECT * FROM distributed_locks WHERE lock_key = ?";
    $stmt = $connection->prepare($sql);
    $result = $stmt->executeQuery([$lockKey]);
    $lockRecord = $result->fetchAssociative();

    if ($lockRecord) {
        echo "锁记录详情:\n";
        echo "  ID: {$lockRecord['id']}\n";
        echo "  锁键: {$lockRecord['lock_key']}\n";
        echo "  锁ID: {$lockRecord['lock_id']}\n";
        echo "  过期时间: {$lockRecord['expire_time']}\n";
        echo "  创建时间: {$lockRecord['created_at']}\n";

        // 检查是否过期
        $now = new DateTime();
        $expireTime = new DateTime($lockRecord['expire_time']);
        $isExpired = $expireTime < $now;
        echo "  是否过期: " . ($isExpired ? "⚠️ 已过期" : "✅ 有效") . "\n";

        if ($isExpired) {
            echo "  ⚠️ 锁已过期但未清理，这可能是问题原因！\n";
        }
    } else {
        echo "❌ 数据库中没有找到锁记录\n";
    }
} catch (Exception $e) {
    echo "❌ 检查锁状态时出错: " . $e->getMessage() . "\n";
}
echo "\n";

// 3. 检查所有活跃的锁
echo "3. 检查所有活跃的锁:\n";
try {
    $connection = $entityManager->getConnection();
    $sql = "SELECT lock_key, lock_id, expire_time, created_at FROM distributed_locks WHERE expire_time > NOW() ORDER BY created_at DESC";
    $stmt = $connection->prepare($sql);
    $result = $stmt->executeQuery();
    $activeLocks = $result->fetchAllAssociative();

    if (count($activeLocks) > 0) {
        echo "活跃锁数量: " . count($activeLocks) . "\n";
        foreach ($activeLocks as $lock) {
            echo "  - {$lock['lock_key']} (ID: {$lock['lock_id']}, 过期: {$lock['expire_time']})\n";
        }
    } else {
        echo "✅ 没有活跃的锁\n";
    }
} catch (Exception $e) {
    echo "❌ 检查活跃锁时出错: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. 尝试获取锁
echo "4. 尝试获取锁:\n";
try {
    $acquired = $lockService->acquireLock($lockKey, 60);
    echo "获取锁结果: " . ($acquired ? "✅ 成功" : "❌ 失败") . "\n";

    if ($acquired) {
        echo "✅ 成功获取锁，现在尝试释放...\n";
        $released = $lockService->releaseLock($lockKey);
        echo "释放锁结果: " . ($released ? "✅ 成功" : "❌ 失败") . "\n";
    }
} catch (Exception $e) {
    echo "❌ 尝试获取锁时出错: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. 检查是否有过期的锁
echo "5. 清理过期锁:\n";
try {
    $connection = $entityManager->getConnection();

    // 查看过期锁数量
    $sql = "SELECT COUNT(*) as count FROM distributed_locks WHERE expire_time < NOW()";
    $stmt = $connection->prepare($sql);
    $result = $stmt->executeQuery();
    $expiredCount = $result->fetchOne();

    echo "过期锁数量: $expiredCount\n";

    if ($expiredCount > 0) {
        echo "清理过期锁...\n";
        $sql = "DELETE FROM distributed_locks WHERE expire_time < NOW()";
        $stmt = $connection->prepare($sql);
        $deletedCount = $stmt->executeStatement();
        echo "✅ 清理了 $deletedCount 个过期锁\n";
    } else {
        echo "✅ 没有过期锁需要清理\n";
    }
} catch (Exception $e) {
    echo "❌ 清理过期锁时出错: " . $e->getMessage() . "\n";
}

echo "\n=== 调试完成 ===\n";
