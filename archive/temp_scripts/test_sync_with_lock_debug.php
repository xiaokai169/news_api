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
$syncService = $container->get(\App\Service\WechatArticleSyncService::class);
$logger = $container->get('monolog.logger');

echo "=== 微信同步锁调试测试 ===\n\n";

$accountId = 'gh_27a426f64edbef94';

echo "测试参数:\n";
echo "- 公众号ID: {$accountId}\n";
echo "- 当前时间: " . date('Y-m-d H:i:s') . "\n\n";

// 1. 测试获取同步状态
echo "1. 测试获取同步状态:\n";
try {
    $status = $syncService->getSyncStatus($accountId);
    echo "   同步状态结果:\n";
    echo "   - 账户ID: " . $status['account_id'] . "\n";
    echo "   - 账户名称: " . ($status['account_name'] ?? 'N/A') . "\n";
    echo "   - 是否同步中: " . ($status['is_syncing'] ? '是' : '否') . "\n";
    echo "   - 错误信息: " . ($status['error'] ?? '无') . "\n\n";
} catch (Exception $e) {
    echo "   ❌ 获取同步状态失败: " . $e->getMessage() . "\n\n";
}

// 2. 测试锁获取逻辑
echo "2. 测试锁获取逻辑:\n";
try {
    $lockService = $container->get(\App\Service\DistributedLockService::class);
    $lockKey = 'wechat_sync_' . $accountId;

    echo "   - 锁键名: {$lockKey}\n";
    echo "   - 锁ID: " . md5($lockKey) . "\n";

    // 检查当前锁状态
    $isLocked = $lockService->isLocked($lockKey);
    echo "   - 当前锁状态: " . ($isLocked ? '被锁定' : '未锁定') . "\n";

    // 尝试获取锁
    $acquired = $lockService->acquireLock($lockKey, 60);
    echo "   - 锁获取结果: " . ($acquired ? '成功' : '失败') . "\n";

    // 再次检查锁状态
    $isLockedAfter = $lockService->isLocked($lockKey);
    echo "   - 获取后锁状态: " . ($isLockedAfter ? '被锁定' : '未锁定') . "\n";

    // 释放锁
    $released = $lockService->releaseLock($lockKey);
    echo "   - 锁释放结果: " . ($released ? '成功' : '失败') . "\n";

    // 最终锁状态
    $isLockedFinal = $lockService->isLocked($lockKey);
    echo "   - 最终锁状态: " . ($isLockedFinal ? '被锁定' : '未锁定') . "\n\n";

} catch (Exception $e) {
    echo "   ❌ 锁测试失败: " . $e->getMessage() . "\n";
    echo "   错误堆栈: " . $e->getTraceAsString() . "\n\n";
}

// 3. 模拟同步过程（不实际执行同步）
echo "3. 模拟同步过程:\n";
try {
    // 这里我们只测试锁获取部分，不执行实际的同步
    echo "   - 开始模拟同步流程...\n";

    // 检查锁状态
    $status = $syncService->getSyncStatus($accountId);
    if (isset($status['error'])) {
        echo "   ❌ 同步状态检查失败: " . $status['error'] . "\n";
    } else if ($status['is_syncing']) {
        echo "   ⚠️  同步任务正在进行中\n";
    } else {
        echo "   ✅ 同步状态检查通过，可以开始同步\n";
    }

} catch (Exception $e) {
    echo "   ❌ 模拟同步失败: " . $e->getMessage() . "\n";
    echo "   错误堆栈: " . $e->getTraceAsString() . "\n\n";
}

// 4. 检查日志输出
echo "4. 检查最近的日志:\n";
try {
    $logFile = __DIR__ . '/../var/log/prod.log';
    if (file_exists($logFile)) {
        $logs = file_get_contents($logFile);
        $recentLogs = substr($logs, -2000); // 获取最后2000字符
        echo "   最近日志内容:\n";
        echo "   " . str_replace("\n", "\n   ", $recentLogs) . "\n";
    } else {
        echo "   📝 日志文件不存在: {$logFile}\n";
    }
} catch (Exception $e) {
    echo "   ❌ 读取日志失败: " . $e->getMessage() . "\n";
}

echo "\n=== 测试完成 ===\n";
