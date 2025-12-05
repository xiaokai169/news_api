<?php
/**
 * 分布式锁问题修复脚本
 * 这个脚本将：
 * 1. 创建分布式锁表
 * 2. 检查锁状态
 * 3. 测试同步功能
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

    echo "=== 分布式锁问题修复脚本 ===\n\n";

    // 步骤1: 检查并创建分布式锁表
    echo "步骤1: 检查并创建分布式锁表\n";
    echo str_repeat("-", 50) . "\n";

    try {
        $result = $connection->executeQuery("SHOW TABLES LIKE 'distributed_locks'");
        $tableExists = $result->fetchAssociative();

        if (!$tableExists) {
            echo "❌ distributed_locks 表不存在，正在创建...\n";

            $sql = "
            CREATE TABLE `distributed_locks` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `lock_key` varchar(255) NOT NULL,
              `lock_id` varchar(255) NOT NULL,
              `expire_time` datetime NOT NULL,
              `created_at` datetime NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `idx_lock_key` (`lock_key`),
              KEY `idx_expire_time` (`expire_time`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";

            $connection->executeStatement($sql);
            echo "✅ distributed_locks 表创建成功！\n";
        } else {
            echo "✅ distributed_locks 表已存在\n";
        }
    } catch (\Exception $e) {
        echo "❌ 创建表时发生错误: " . $e->getMessage() . "\n";
        exit(1);
    }

    echo "\n";

    // 步骤2: 检查当前锁状态
    echo "步骤2: 检查当前锁状态\n";
    echo str_repeat("-", 50) . "\n";

    try {
        $result = $connection->executeQuery("SELECT * FROM distributed_locks ORDER BY created_at DESC");
        $locks = $result->fetchAllAssociative();

        if (empty($locks)) {
            echo "✅ 当前没有锁记录\n";
        } else {
            echo "发现 " . count($locks) . " 个锁记录:\n";
            foreach ($locks as $lock) {
                $isExpired = new \DateTime($lock['expire_time']) < new \DateTime();
                $status = $isExpired ? "已过期" : "活跃";
                $statusIcon = $isExpired ? "⏰" : "🔒";
                echo sprintf("  %s %s: %s (过期时间: %s)\n",
                    $statusIcon,
                    $lock['lock_key'],
                    $status,
                    $lock['expire_time']
                );
            }
        }
    } catch (\Exception $e) {
        echo "❌ 检查锁状态时发生错误: " . $e->getMessage() . "\n";
    }

    echo "\n";

    // 步骤3: 清理过期锁
    echo "步骤3: 清理过期锁\n";
    echo str_repeat("-", 50) . "\n";

    try {
        $result = $connection->executeStatement("DELETE FROM distributed_locks WHERE expire_time < NOW()");
        echo "✅ 已清理 {$result} 个过期锁\n";
    } catch (\Exception $e) {
        echo "❌ 清理过期锁时发生错误: " . $e->getMessage() . "\n";
    }

    echo "\n";

    // 步骤4: 测试分布式锁服务
    echo "步骤4: 测试分布式锁服务\n";
    echo str_repeat("-", 50) . "\n";

    try {
        $distributedLockService = $kernel->getContainer()->get(\App\Service\DistributedLockService::class);

        $testLockKey = 'test_lock_' . time();
        echo "测试获取锁: {$testLockKey}\n";

        $acquired = $distributedLockService->acquireLock($testLockKey, 60);
        if ($acquired) {
            echo "✅ 成功获取测试锁\n";

            $isLocked = $distributedLockService->isLocked($testLockKey);
            echo "锁状态检查: " . ($isLocked ? "已锁定" : "未锁定") . "\n";

            $released = $distributedLockService->releaseLock($testLockKey);
            echo "锁释放: " . ($released ? "成功" : "失败") . "\n";
        } else {
            echo "❌ 获取测试锁失败\n";
        }

    } catch (\Exception $e) {
        echo "❌ 测试分布式锁服务时发生错误: " . $e->getMessage() . "\n";
    }

    echo "\n";

    // 步骤5: 测试微信同步服务
    echo "步骤5: 测试微信同步服务\n";
    echo str_repeat("-", 50) . "\n";

    try {
        $syncService = $kernel->getContainer()->get(\App\Service\WechatArticleSyncService::class);
        $accountId = 'gh_e4b07b2a992e6669';

        echo "测试同步状态检查...\n";
        $status = $syncService->getSyncStatus($accountId);
        echo "同步状态: " . json_encode($status, JSON_UNESCAPED_UNICODE) . "\n";

        if (!isset($status['error'])) {
            echo "✅ 同步服务状态正常\n";
        } else {
            echo "❌ 同步服务状态异常: " . $status['error'] . "\n";
        }

    } catch (\Exception $e) {
        echo "❌ 测试微信同步服务时发生错误: " . $e->getMessage() . "\n";
    }

    echo "\n";

    // 步骤6: 最终验证
    echo "步骤6: 最终验证\n";
    echo str_repeat("-", 50) . "\n";

    echo "现在可以尝试重新调用微信同步API:\n";
    echo "curl -X POST \"http://127.0.0.1:8084/official-api/wechat/sync\" \\\n";
    echo "  -H \"Content-Type: application/json\" \\\n";
    echo "  -d '{\"accountId\":\"gh_e4b07b2a992e6669\",\"force\":false}'\n";

    echo "\n=== 修复完成 ===\n";

    $kernel->shutdown();

} catch (\Exception $e) {
    echo "❌ 修复脚本执行失败: " . $e->getMessage() . "\n";
    echo "错误堆栈: " . $e->getTraceAsString() . "\n";
    exit(1);
}
