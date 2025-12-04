<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;

echo "=== 清理过期的分布式锁 ===\n\n";

try {
    // 创建 Symfony 应用实例
    $kernel = new Kernel('dev', true);
    $kernel->boot();

    // 获取分布式锁服务
    $container = $kernel->getContainer();
    $lockService = $container->get('App\Service\DistributedLockService');
    $connection = $container->get('doctrine.orm.entity_manager')->getConnection();

    // 1. 显示当前锁状态
    echo "1. 检查当前锁状态:\n";
    $sql = "SELECT lock_key, lock_id, expire_time, created_at FROM distributed_locks ORDER BY created_at DESC";
    $stmt = $connection->prepare($sql);
    $result = $stmt->executeQuery();
    $locks = $result->fetchAllAssociative();

    if (empty($locks)) {
        echo "   没有找到任何锁记录\n\n";
    } else {
        echo "   当前锁数量: " . count($locks) . "\n";
        foreach ($locks as $lock) {
            $status = strtotime($lock['expire_time']) > time() ? '🔒 有效' : '⏰ 已过期';
            echo "   - {$lock['lock_key']} ({$status}, 过期时间: {$lock['expire_time']})\n";
        }
        echo "\n";
    }

    // 2. 清理过期锁
    echo "2. 清理过期锁:\n";
    $cleanupSql = "DELETE FROM distributed_locks WHERE expire_time < NOW()";
    $cleanupStmt = $connection->prepare($cleanupSql);
    $cleanedCount = $cleanupStmt->executeStatement();

    echo "   清理了 $cleanedCount 个过期锁\n\n";

    // 3. 强制清理微信同步相关的锁（可选）
    echo "3. 检查微信同步相关锁:\n";
    $wechatLocksSql = "SELECT lock_key FROM distributed_locks WHERE lock_key LIKE 'wechat_sync%'";
    $wechatStmt = $connection->prepare($wechatLocksSql);
    $wechatResult = $wechatStmt->executeQuery();
    $wechatLocks = $wechatResult->fetchAllAssociative();

    if (!empty($wechatLocks)) {
        echo "   发现微信同步相关锁:\n";
        foreach ($wechatLocks as $lock) {
            echo "   - {$lock['lock_key']}\n";
        }

        echo "\n   是否强制清理所有微信同步锁？(y/N): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);

        if (trim(strtolower($line)) === 'y') {
            $forceCleanupSql = "DELETE FROM distributed_locks WHERE lock_key LIKE 'wechat_sync%'";
            $forceStmt = $connection->prepare($forceCleanupSql);
            $forceCleanedCount = $forceStmt->executeStatement();
            echo "   强制清理了 $forceCleanedCount 个微信同步锁\n";
        } else {
            echo "   跳过强制清理\n";
        }
    } else {
        echo "   没有发现微信同步相关锁\n";
    }

    // 4. 最终状态
    echo "\n4. 最终锁状态:\n";
    $finalSql = "SELECT COUNT(*) as count FROM distributed_locks";
    $finalStmt = $connection->prepare($finalSql);
    $finalResult = $finalStmt->executeQuery();
    $finalCount = $finalResult->fetchAssociative()['count'];

    echo "   剩余锁数量: $finalCount\n";

    $kernel->shutdown();

    echo "\n=== 清理完成 ===\n";
    echo "现在可以重新运行微信同步任务了。\n";

} catch (Exception $e) {
    echo "❌ 清理过程中发生异常: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
