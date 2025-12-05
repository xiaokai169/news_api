<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use App\Service\WechatArticleSyncService;

echo "=== 测试微信同步修复结果 ===\n\n";

try {
    // 创建 Symfony 应用实例
    $kernel = new Kernel('dev', true);
    $kernel->boot();

    // 获取服务
    $container = $kernel->getContainer();
    $syncService = $container->get('App\Service\WechatArticleSyncService');

    $accountId = 'gh_27a426f64edbef94';

    echo "测试公众号ID: $accountId\n\n";

    // 1. 测试获取同步状态
    echo "=== 步骤1: 测试获取同步状态 ===\n";
    $status = $syncService->getSyncStatus($accountId);

    if (isset($status['error'])) {
        echo "❌ 获取同步状态失败: " . $status['error'] . "\n";
    } else {
        echo "✅ 获取同步状态成功\n";
        echo "  公众号ID: " . $status['account_id'] . "\n";
        echo "  公众号名称: " . $status['account_name'] . "\n";
        echo "  是否正在同步: " . ($status['is_syncing'] ? '是' : '否') . "\n";
    }
    echo "\n";

    // 2. 测试分布式锁服务
    echo "=== 步骤2: 测试分布式锁服务 ===\n";
    $lockService = $container->get('App\Service\DistributedLockService');
    $lockKey = 'wechat_sync_' . $accountId;

    // 检查锁状态
    $isLocked = $lockService->isLocked($lockKey);
    echo "当前锁状态: " . ($isLocked ? "🔒 已锁定" : "🔓 未锁定") . "\n";

    // 尝试获取锁
    echo "尝试获取锁...\n";
    $acquired = $lockService->acquireLock($lockKey, 60);
    echo "获取锁结果: " . ($acquired ? "✅ 成功" : "❌ 失败") . "\n";

    if ($acquired) {
        echo "释放锁...\n";
        $released = $lockService->releaseLock($lockKey);
        echo "释放锁结果: " . ($released ? "✅ 成功" : "❌ 失败") . "\n";
    }
    echo "\n";

    // 3. 测试实际的同步操作（短时间测试）
    echo "=== 步骤3: 测试实际同步操作 ===\n";
    echo "开始同步测试（使用 --bypass-lock 避免锁问题）...\n";

    $result = $syncService->syncArticles($accountId, false, true); // 使用 bypass-lock

    if ($result['success']) {
        echo "✅ 同步操作成功启动\n";
        echo "消息: " . $result['message'] . "\n";

        if (!empty($result['stats'])) {
            echo "统计信息:\n";
            foreach ($result['stats'] as $key => $value) {
                echo "  $key: $value\n";
            }
        }

        if (!empty($result['errors'])) {
            echo "错误信息:\n";
            foreach ($result['errors'] as $error) {
                echo "  - $error\n";
            }
        }
    } else {
        echo "❌ 同步操作失败\n";
        echo "错误: " . $result['message'] . "\n";

        if (!empty($result['errors'])) {
            echo "详细错误:\n";
            foreach ($result['errors'] as $error) {
                echo "  - $error\n";
            }
        }
    }

    echo "\n";

    // 4. 最终锁状态检查
    echo "=== 步骤4: 最终锁状态检查 ===\n";
    $finalLockStatus = $lockService->isLocked($lockKey);
    echo "最终锁状态: " . ($finalLockStatus ? "🔒 已锁定" : "🔓 未锁定") . "\n";

    if ($finalLockStatus) {
        echo "⚠️ 警告: 锁仍然存在，可能需要手动清理\n";
        echo "可以运行以下命令清理:\n";
        echo "curl http://127.0.0.1:8084/cleanup_expired_locks.php\n";
    } else {
        echo "✅ 锁状态正常\n";
    }

    $kernel->shutdown();

} catch (Exception $e) {
    echo "❌ 测试过程中发生异常: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== 测试完成 ===\n";
echo "\n如果测试通过，现在可以正常运行:\n";
echo "php bin/console app:wechat:sync $accountId\n";
echo "\n如果仍有锁问题，可以使用:\n";
echo "php bin/console app:wechat:sync $accountId --bypass-lock\n";
