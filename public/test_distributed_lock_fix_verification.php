<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use App\Service\DistributedLockService;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * 分布式锁修复验证测试脚本
 *
 * 测试内容：
 * 1. 实体字段映射修复验证
 * 2. SQL语句字段名修复验证
 * 3. 分布式锁获取和释放功能测试
 * 4. 微信日志文件创建验证
 */

class DistributedLockFixVerification
{
    private $kernel;
    private $container;
    private $lockService;
    private $logger;

    public function __construct()
    {
        echo "=== 分布式锁修复验证测试 ===\n\n";

        // 初始化Symfony内核
        $this->kernel = new Kernel('test', true);
        $this->kernel->boot();

        $this->container = $this->kernel->getContainer();
        $this->lockService = $this->container->get(DistributedLockService::class);
        $this->logger = $this->container->get('logger');
    }

    /**
     * 运行所有测试
     */
    public function runAllTests(): void
    {
        $this->testWechatLogFile();
        $this->testBasicLockOperations();
        $this->testConcurrentLockAcquisition();
        $this->testLockExpiration();
        $this->testSqlFieldMapping();

        echo "\n=== 测试总结 ===\n";
        echo "所有测试已完成！请查看上述结果。\n";
    }

    /**
     * 测试微信日志文件
     */
    private function testWechatLogFile(): void
    {
        echo "1. 测试微信日志文件创建...\n";

        $logFile = __DIR__ . '/../var/log/wechat.log';

        if (file_exists($logFile)) {
            echo "   ✓ 微信日志文件存在: {$logFile}\n";
            echo "   ✓ 文件大小: " . filesize($logFile) . " 字节\n";
            echo "   ✓ 文件权限: " . substr(sprintf('%o', fileperms($logFile)), -4) . "\n";
        } else {
            echo "   ✗ 微信日志文件不存在\n";
        }

        // 测试写入日志
        $testMessage = "[" . date('Y-m-d H:i:s') . "] INFO: 测试微信日志写入\n";
        if (file_put_contents($logFile, $testMessage, FILE_APPEND)) {
            echo "   ✓ 日志写入测试成功\n";
        } else {
            echo "   ✗ 日志写入测试失败\n";
        }

        echo "\n";
    }

    /**
     * 测试基本锁操作
     */
    private function testBasicLockOperations(): void
    {
        echo "2. 测试基本分布式锁操作...\n";

        $testKey = 'test_basic_lock_' . time();

        try {
            // 测试获取锁
            $acquired = $this->lockService->acquireLock($testKey, 30);
            if ($acquired) {
                echo "   ✓ 成功获取锁: {$testKey}\n";
            } else {
                echo "   ✗ 获取锁失败: {$testKey}\n";
                return;
            }

            // 测试锁状态检查
            $isLocked = $this->lockService->isLocked($testKey);
            if ($isLocked) {
                echo "   ✓ 锁状态检查正确: 已锁定\n";
            } else {
                echo "   ✗ 锁状态检查错误: 应该锁定但显示未锁定\n";
            }

            // 测试锁释放
            $released = $this->lockService->releaseLock($testKey);
            if ($released) {
                echo "   ✓ 成功释放锁: {$testKey}\n";
            } else {
                echo "   ✗ 释放锁失败: {$testKey}\n";
            }

            // 验证锁已释放
            $isLockedAfter = $this->lockService->isLocked($testKey);
            if (!$isLockedAfter) {
                echo "   ✓ 锁释放验证成功: 已解锁\n";
            } else {
                echo "   ✗ 锁释放验证失败: 仍然锁定\n";
            }

        } catch (Exception $e) {
            echo "   ✗ 基本锁操作测试异常: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    /**
     * 测试并发锁获取
     */
    private function testConcurrentLockAcquisition(): void
    {
        echo "3. 测试并发锁获取...\n";

        $testKey = 'test_concurrent_lock_' . time();

        try {
            // 第一次获取锁
            $firstAcquired = $this->lockService->acquireLock($testKey, 30);
            if ($firstAcquired) {
                echo "   ✓ 第一次获取锁成功\n";
            } else {
                echo "   ✗ 第一次获取锁失败\n";
                return;
            }

            // 第二次获取同一个锁（应该失败）
            $secondAcquired = $this->lockService->acquireLock($testKey, 30);
            if (!$secondAcquired) {
                echo "   ✓ 第二次获取锁正确失败（防止并发）\n";
            } else {
                echo "   ✗ 第二次获取锁不应该成功\n";
            }

            // 释放锁
            $this->lockService->releaseLock($testKey);

            // 释放后重新获取（应该成功）
            $thirdAcquired = $this->lockService->acquireLock($testKey, 30);
            if ($thirdAcquired) {
                echo "   ✓ 释放后重新获取锁成功\n";
            } else {
                echo "   ✗ 释放后重新获取锁失败\n";
            }

            // 清理
            $this->lockService->releaseLock($testKey);

        } catch (Exception $e) {
            echo "   ✗ 并发锁测试异常: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    /**
     * 测试锁过期
     */
    private function testLockExpiration(): void
    {
        echo "4. 测试锁过期机制...\n";

        $testKey = 'test_expiration_lock_' . time();

        try {
            // 获取一个短时间的锁（2秒）
            $acquired = $this->lockService->acquireLock($testKey, 2);
            if ($acquired) {
                echo "   ✓ 获取短期锁成功（2秒）\n";
            } else {
                echo "   ✗ 获取短期锁失败\n";
                return;
            }

            // 立即检查应该锁定
            $isLocked = $this->lockService->isLocked($testKey);
            if ($isLocked) {
                echo "   ✓ 锁状态正确：已锁定\n";
            } else {
                echo "   ✗ 锁状态错误：应该锁定但显示未锁定\n";
            }

            // 等待3秒让锁过期
            echo "   ⏳ 等待3秒让锁过期...\n";
            sleep(3);

            // 再次检查应该未锁定
            $isLockedAfter = $this->lockService->isLocked($testKey);
            if (!$isLockedAfter) {
                echo "   ✓ 锁过期机制正常：已自动解锁\n";
            } else {
                echo "   ✗ 锁过期机制异常：仍然锁定\n";
            }

        } catch (Exception $e) {
            echo "   ✗ 锁过期测试异常: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    /**
     * 测试SQL字段映射
     */
    private function testSqlFieldMapping(): void
    {
        echo "5. 测试SQL字段映射修复...\n";

        try {
            $connection = $this->container->get('doctrine.orm.entity_manager')->getConnection();

            // 测试表结构
            $describeSql = "DESCRIBE distributed_locks";
            $stmt = $connection->prepare($describeSql);
            $result = $stmt->executeQuery();
            $columns = $result->fetchAllAssociative();

            $expectedColumns = ['id', 'lock_key', 'lock_id', 'expire_time', 'created_at'];
            $actualColumns = array_column($columns, 'Field');

            echo "   数据库表字段: " . implode(', ', $actualColumns) . "\n";

            $missingColumns = array_diff($expectedColumns, $actualColumns);
            if (empty($missingColumns)) {
                echo "   ✓ 数据库表字段结构正确\n";
            } else {
                echo "   ✗ 缺少字段: " . implode(', ', $missingColumns) . "\n";
            }

            // 测试INSERT语句（使用正确的字段名）
            $testKey = 'test_sql_mapping_' . time();
            $testId = md5($testKey);
            $expireTime = date('Y-m-d H:i:s', time() + 60);

            $insertSql = "INSERT INTO distributed_locks (lock_key, lock_id, expire_time, created_at)
                          VALUES (?, ?, ?, NOW())";
            $stmt = $connection->prepare($insertSql);
            $result = $stmt->executeStatement([$testKey, $testId, $expireTime]);

            if ($result > 0) {
                echo "   ✓ INSERT语句使用正确字段名成功\n";
            } else {
                echo "   ✗ INSERT语句执行失败\n";
            }

            // 测试SELECT语句（使用正确的字段名）
            $selectSql = "SELECT lock_id, expire_time FROM distributed_locks WHERE lock_key = ?";
            $stmt = $connection->prepare($selectSql);
            $result = $stmt->executeQuery([$testKey]);
            $lock = $result->fetchAssociative();

            if ($lock && $lock['lock_id'] === $testId) {
                echo "   ✓ SELECT语句使用正确字段名成功\n";
            } else {
                echo "   ✗ SELECT语句执行失败或数据不匹配\n";
            }

            // 测试DELETE语句（使用正确的字段名）
            $deleteSql = "DELETE FROM distributed_locks WHERE lock_key = ?";
            $stmt = $connection->prepare($deleteSql);
            $result = $stmt->executeStatement([$testKey]);

            if ($result > 0) {
                echo "   ✓ DELETE语句使用正确字段名成功\n";
            } else {
                echo "   ✗ DELETE语句执行失败\n";
            }

        } catch (Exception $e) {
            echo "   ✗ SQL字段映射测试异常: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    /**
     * 清理资源
     */
    public function __destruct()
    {
        if ($this->kernel) {
            $this->kernel->shutdown();
        }
    }
}

// 运行测试
try {
    $test = new DistributedLockFixVerification();
    $test->runAllTests();
} catch (Exception $e) {
    echo "测试执行异常: " . $e->getMessage() . "\n";
    echo "堆栈跟踪: " . $e->getTraceAsString() . "\n";
}
