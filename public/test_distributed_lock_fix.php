<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Service\DistributedLockService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

// 简单的测试环境设置
$container = new ContainerBuilder();

// 模拟 Logger
class TestLogger implements LoggerInterface
{
    public function emergency($message, array $context = []): void
    {
        $this->log('EMERGENCY', $message, $context);
    }
    public function alert($message, array $context = []): void
    {
        $this->log('ALERT', $message, $context);
    }
    public function critical($message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }
    public function error($message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }
    public function warning($message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }
    public function notice($message, array $context = []): void
    {
        $this->log('NOTICE', $message, $context);
    }
    public function info($message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }
    public function debug($message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }
    public function log($level, $message, array $context = []): void
    {
        echo "[$level] $message\n";
        if (!empty($context)) {
            echo "Context: " . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
        echo "---\n";
    }
}

// 获取 Doctrine 实体管理器
require_once __DIR__ . '/../src/Kernel.php';
$kernel = new App\Kernel('dev', true);
$kernel->boot();

$entityManager = $kernel->getContainer()->get('doctrine.orm.entity_manager');
$logger = new TestLogger();

// 创建分布式锁服务
$lockService = new DistributedLockService($entityManager, $logger);

echo "=== 分布式锁修复测试 ===\n\n";

$testLockKey = 'wechat_sync_test_account_001';

echo "1. 测试获取锁...\n";
$acquired = $lockService->acquireLock($testLockKey, 60);
echo "锁获取结果: " . ($acquired ? "成功" : "失败") . "\n\n";

echo "2. 测试检查锁状态...\n";
$isLocked = $lockService->isLocked($testLockKey);
echo "锁状态: " . ($isLocked ? "已锁定" : "未锁定") . "\n\n";

echo "3. 测试释放锁...\n";
$released = $lockService->releaseLock($testLockKey);
echo "锁释放结果: " . ($released ? "成功" : "失败") . "\n\n";

echo "4. 再次检查锁状态...\n";
$isLockedAfter = $lockService->isLocked($testLockKey);
echo "释放后锁状态: " . ($isLockedAfter ? "已锁定" : "未锁定") . "\n\n";

echo "=== 测试完成 ===\n";
