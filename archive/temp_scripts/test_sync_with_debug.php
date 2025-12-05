<?php
/**
 * 测试微信同步并添加详细调试日志
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use App\Service\WechatArticleSyncService;
use App\Service\DistributedLockService;

try {
    // 创建Symfony应用实例
    $kernel = new Kernel('dev', true);
    $kernel->boot();

    // 获取服务
    $syncService = $kernel->getContainer()->get(WechatArticleSyncService::class);
    $distributedLockService = $kernel->getContainer()->get(DistributedLockService::class);

    $accountId = 'gh_e4b07b2a992e6669';

    echo "=== 微信同步调试测试 ===\n\n";

    // 1. 首先测试分布式锁服务
    echo "1. 测试分布式锁服务:\n";
    $lockKey = 'wechat_sync_' . $accountId;

    echo "尝试获取锁: {$lockKey}\n";
    $lockResult = $distributedLockService->acquireLock($lockKey, 1800);
    echo "锁获取结果: " . ($lockResult ? "成功" : "失败") . "\n";

    if (!$lockResult) {
        echo "检查锁状态: " . ($distributedLockService->isLocked($lockKey) ? "已锁定" : "未锁定") . "\n";
    }

    echo "\n";

    // 2. 测试同步服务状态检查
    echo "2. 测试同步服务状态检查:\n";
    $status = $syncService->getSyncStatus($accountId);
    echo "同步状态: " . json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

    echo "\n";

    // 3. 尝试执行同步（使用绕过锁选项）
    echo "3. 尝试执行同步（绕过锁检查）:\n";
    $syncResult = $syncService->syncArticles($accountId, false, true);
    echo "同步结果: " . json_encode($syncResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

    echo "\n=== 测试完成 ===\n";

    $kernel->shutdown();

} catch (\Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo "错误堆栈: " . $e->getTraceAsString() . "\n";
    exit(1);
}
