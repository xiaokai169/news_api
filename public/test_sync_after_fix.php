<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;

try {
    $kernel = new Kernel('dev', true);
    $kernel->boot();

    $container = $kernel->getContainer();
    $syncService = $container->get('App\Service\WechatArticleSyncService');

    echo "=== 测试微信同步接口修复效果 ===\n";

    // 测试账号ID
    $accountId = 'gh_27a426f64edbef94';

    echo "测试账号ID: $accountId\n";
    echo "开始时间: " . date('Y-m-d H:i:s') . "\n\n";

    // 首先检查同步状态
    echo "1. 检查同步状态...\n";
    $status = $syncService->getSyncStatus($accountId);
    echo "同步状态: " . json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    // 尝试执行同步（不绕过锁）
    echo "2. 尝试执行同步（正常模式）...\n";
    $result = $syncService->syncArticles($accountId, false, false);
    echo "同步结果: " . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    // 再次检查同步状态
    echo "3. 再次检查同步状态...\n";
    $statusAfter = $syncService->getSyncStatus($accountId);
    echo "同步状态: " . json_encode($statusAfter, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    echo "=== 测试完成 ===\n";
    echo "结束时间: " . date('Y-m-d H:i:s') . "\n";

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪: " . $e->getTraceAsString() . "\n";
}
