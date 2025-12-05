<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;

try {
    $kernel = new Kernel('dev', true);
    $kernel->boot();
    $entityManager = $kernel->getContainer()->get('doctrine.orm.entity_manager');
    $connection = $entityManager->getConnection();

    echo "=== 检查 distributed_locks 表状态 ===\n";
    $sql = 'SELECT * FROM distributed_locks ORDER BY created_at DESC';
    $stmt = $connection->prepare($sql);
    $result = $stmt->executeQuery();
    $locks = $result->fetchAllAssociative();

    if (empty($locks)) {
        echo "没有找到任何锁记录\n";
    } else {
        echo "找到 " . count($locks) . " 个锁记录:\n\n";
        foreach ($locks as $lock) {
            echo "Lock Key: " . $lock['lock_key'] . "\n";
            echo "Lock ID: " . $lock['lock_id'] . "\n";
            echo "Expire Time: " . $lock['expire_time'] . "\n";
            echo "Created At: " . $lock['created_at'] . "\n";
            echo "Is Expired: " . (strtotime($lock['expire_time']) < time() ? 'YES' : 'NO') . "\n";
            echo "---\n";
        }
    }

    echo "\n=== 特别检查 wechat_sync_gh_27a426f64edbef94 锁 ===\n";
    $specificSql = 'SELECT * FROM distributed_locks WHERE lock_key = ?';
    $specificStmt = $connection->prepare($specificSql);
    $specificResult = $specificStmt->executeQuery(['wechat_sync_gh_27a426f64edbef94']);
    $specificLocks = $specificResult->fetchAllAssociative();

    if (empty($specificLocks)) {
        echo "没有找到 wechat_sync_gh_27a426f64edbef94 锁\n";
    } else {
        foreach ($specificLocks as $lock) {
            echo "Lock Key: " . $lock['lock_key'] . "\n";
            echo "Lock ID: " . $lock['lock_id'] . "\n";
            echo "Expire Time: " . $lock['expire_time'] . "\n";
            echo "Created At: " . $lock['created_at'] . "\n";
            echo "Is Expired: " . (strtotime($lock['expire_time']) < time() ? 'YES' : 'NO') . "\n";
        }
    }

    echo "\n=== 检查所有微信相关锁 ===\n";
    $wechatSql = "SELECT * FROM distributed_locks WHERE lock_key LIKE 'wechat_%' ORDER BY created_at DESC";
    $wechatStmt = $connection->prepare($wechatSql);
    $wechatResult = $wechatStmt->executeQuery();
    $wechatLocks = $wechatResult->fetchAllAssociative();

    if (empty($wechatLocks)) {
        echo "没有找到微信相关的锁\n";
    } else {
        echo "找到 " . count($wechatLocks) . " 个微信相关锁:\n\n";
        foreach ($wechatLocks as $lock) {
            echo "Lock Key: " . $lock['lock_key'] . "\n";
            echo "Lock ID: " . $lock['lock_id'] . "\n";
            echo "Expire Time: " . $lock['expire_time'] . "\n";
            echo "Created At: " . $lock['created_at'] . "\n";
            echo "Is Expired: " . (strtotime($lock['expire_time']) < time() ? 'YES' : 'NO') . "\n";
            echo "---\n";
        }
    }

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪: " . $e->getTraceAsString() . "\n";
}
