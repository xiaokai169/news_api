<?php
/**
 * 调试分布式锁问题的脚本
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use Doctrine\DBAL\Connection;

try {
    // 创建Symfony应用实例
    $kernel = new Kernel('dev', true);
    $kernel->boot();

    // 获取数据库连接
    $connection = $kernel->getContainer()->get('doctrine.dbal.default_connection');

    echo "=== 分布式锁调试报告 ===\n\n";

    // 1. 检查表是否存在
    echo "1. 检查 distributed_locks 表是否存在:\n";
    try {
        $result = $connection->executeQuery("SHOW TABLES LIKE 'distributed_locks'");
        $tableExists = $result->fetchAssociative();

        if ($tableExists) {
            echo "✅ 表存在\n";
        } else {
            echo "❌ 表不存在 - 这可能是问题的根源！\n";
        }
    } catch (\Exception $e) {
        echo "❌ 检查表时发生错误: " . $e->getMessage() . "\n";
    }

    echo "\n";

    // 2. 检查表结构
    if ($tableExists ?? false) {
        echo "2. 检查表结构:\n";
        try {
            $structure = $connection->executeQuery("DESCRIBE distributed_locks");
            echo "字段信息:\n";
            while ($row = $structure->fetchAssociative()) {
                echo sprintf("  - %s: %s %s %s %s\n",
                    $row['Field'],
                    $row['Type'],
                    $row['Null'],
                    $row['Key'],
                    $row['Default']
                );
            }
        } catch (\Exception $e) {
            echo "❌ 检查表结构时发生错误: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }

    // 3. 检查当前锁状态
    echo "3. 检查当前锁状态:\n";
    if ($tableExists ?? false) {
        try {
            $result = $connection->executeQuery("SELECT * FROM distributed_locks");
            $locks = $result->fetchAllAssociative();

            if (empty($locks)) {
                echo "✅ 当前没有活跃的锁\n";
            } else {
                echo "⚠️  发现 " . count($locks) . " 个锁记录:\n";
                foreach ($locks as $lock) {
                    $isExpired = new \DateTime($lock['expire_time']) < new \DateTime();
                    $status = $isExpired ? "已过期" : "活跃";
                    echo sprintf("  - 锁键: %s, 锁ID: %s, 过期时间: %s, 状态: %s\n",
                        $lock['lock_key'],
                        $lock['lock_id'],
                        $lock['expire_time'],
                        $status
                    );
                }
            }
        } catch (\Exception $e) {
            echo "❌ 检查锁状态时发生错误: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ 无法检查锁状态，表不存在\n";
    }

    echo "\n";

    // 4. 测试分布式锁服务
    echo "4. 测试分布式锁服务:\n";
    try {
        $distributedLockService = $kernel->getContainer()->get(\App\Service\DistributedLockService::class);

        $testLockKey = 'test_lock_' . time();
        echo "测试获取锁: {$testLockKey}\n";

        $acquired = $distributedLockService->acquireLock($testLockKey, 60);
        if ($acquired) {
            echo "✅ 成功获取测试锁\n";

            // 检查锁状态
            $isLocked = $distributedLockService->isLocked($testLockKey);
            echo "锁状态检查: " . ($isLocked ? "已锁定" : "未锁定") . "\n";

            // 释放锁
            $released = $distributedLockService->releaseLock($testLockKey);
            echo "锁释放: " . ($released ? "成功" : "失败") . "\n";
        } else {
            echo "❌ 获取测试锁失败\n";
        }

    } catch (\Exception $e) {
        echo "❌ 测试分布式锁服务时发生错误: " . $e->getMessage() . "\n";
        echo "错误堆栈: " . $e->getTraceAsString() . "\n";
    }

    echo "\n=== 调试完成 ===\n";

    $kernel->shutdown();

} catch (\Exception $e) {
    echo "❌ 调试脚本执行失败: " . $e->getMessage() . "\n";
    echo "错误堆栈: " . $e->getTraceAsString() . "\n";
    exit(1);
}
