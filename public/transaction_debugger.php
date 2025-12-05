<?php

/**
 * 事务和错误处理调试器
 * 专门用于检查同步过程中的事务处理和错误处理问题
 *
 * 使用方法:
 * php public/transaction_debugger.php [account_id] [test_type]
 * account_id: 公众号ID
 * test_type: 测试类型 (dry_run|real_test|rollback_test)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use App\Service\WechatArticleSyncService;
use App\Service\DistributedLockService;
use App\Repository\OfficialRepository;
use App\Repository\WechatPublicAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class TransactionDebugger
{
    private Kernel $kernel;
    private EntityManagerInterface $entityManager;
    private Connection $connection;
    private WechatArticleSyncService $syncService;
    private DistributedLockService $lockService;
    private OfficialRepository $officialRepository;
    private WechatPublicAccountRepository $accountRepository;

    private array $testResults = [];
    private string $testType = 'dry_run';

    public function __construct()
    {
        $this->kernel = new Kernel($_ENV['APP_ENV'] ?? 'dev', (bool)($_ENV['APP_DEBUG'] ?? true));
        $this->kernel->boot();

        $container = $this->kernel->getContainer();

        $this->entityManager = $container->get('doctrine.orm.default_entity_manager');
        $this->connection = $this->entityManager->getConnection();
        $this->syncService = $container->get(WechatArticleSyncService::class);
        $this->lockService = $container->get(DistributedLockService::class);
        $this->officialRepository = $container->get(OfficialRepository::class);
        $this->accountRepository = $container->get(WechatPublicAccountRepository::class);
    }

    public function debug(string $accountId = '', string $testType = 'dry_run'): void
    {
        $this->testType = $testType;

        echo "=== 事务和错误处理调试器 ===\n";
        echo "测试类型: {$testType}\n";
        echo "目标账号: " . ($accountId ?: '未指定') . "\n";
        echo "开始时间: " . date('Y-m-d H:i:s') . "\n\n";

        // 1. 检查事务隔离级别
        $this->checkTransactionIsolation();

        // 2. 检查自动提交状态
        $this->checkAutoCommit();

        // 3. 测试基础事务操作
        $this->testBasicTransaction();

        // 4. 测试同步服务事务
        if ($accountId) {
            $this->testSyncTransaction($accountId);
        }

        // 5. 测试错误处理
        $this->testErrorHandling();

        // 6. 测试回滚机制
        $this->testRollbackMechanism();

        // 7. 检查死锁情况
        $this->checkDeadlocks();

        // 8. 生成调试报告
        $this->generateDebugReport();
    }

    private function checkTransactionIsolation(): void
    {
        echo "=== 1. 事务隔离级别检查 ===\n";

        try {
            $isolation = $this->connection->fetchOne('SELECT @@tx_isolation');
            echo "✓ 事务隔离级别: {$isolation}\n";
            $this->testResults['transaction_isolation'] = $isolation;

            // 检查是否为合适的隔离级别
            if (strpos($isolation, 'READ-COMMITTED') !== false || strpos($isolation, 'REPEATABLE-READ') !== false) {
                echo "✓ 隔离级别适合同步操作\n";
            } else {
                echo "⚠️  隔离级别可能不适合高并发场景\n";
            }

        } catch (Exception $e) {
            echo "✗ 检查事务隔离级别失败: " . $e->getMessage() . "\n";
            $this->testResults['transaction_isolation'] = 'error: ' . $e->getMessage();
        }

        echo "\n";
    }

    private function checkAutoCommit(): void
    {
        echo "=== 2. 自动提交状态检查 ===\n";

        try {
            $autoCommit = $this->connection->fetchOne('SELECT @@autocommit');
            echo "✓ 自动提交状态: " . ($autoCommit ? '开启' : '关闭') . "\n";
            $this->testResults['autocommit'] = $autoCommit;

            if ($autoCommit) {
                echo "⚠️  自动提交已开启，建议在事务中手动控制\n";
            } else {
                echo "✓ 自动提交已关闭，适合事务处理\n";
            }

        } catch (Exception $e) {
            echo "✗ 检查自动提交失败: " . $e->getMessage() . "\n";
            $this->testResults['autocommit'] = 'error: ' . $e->getMessage();
        }

        echo "\n";
    }

    private function testBasicTransaction(): void
    {
        echo "=== 3. 基础事务操作测试 ===\n";

        try {
            // 开始事务
            $this->connection->beginTransaction();
            echo "✓ 事务开始成功\n";

            // 插入测试数据
            $testId = 'test_' . time();
            $this->connection->insert('official', [
                'title' => 'Test Transaction ' . $testId,
                'content' => 'Test content for transaction',
                'article_id' => $testId,
                'status' => 2,
                'create_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            echo "✓ 测试数据插入成功\n";

            // 查询验证
            $count = $this->connection->fetchOne("SELECT COUNT(*) FROM official WHERE article_id = ?", [$testId]);
            echo "✓ 事务内查询验证: 找到 {$count} 条记录\n";

            if ($this->testType === 'dry_run') {
                // 回滚事务
                $this->connection->rollBack();
                echo "✓ 事务回滚成功\n";

                // 验证回滚结果
                $countAfterRollback = $this->connection->fetchOne("SELECT COUNT(*) FROM official WHERE article_id = ?", [$testId]);
                echo "✓ 回滚后验证: 找到 {$countAfterRollback} 条记录\n";

                if ($countAfterRollback == 0) {
                    echo "✓ 事务回滚正常工作\n";
                    $this->testResults['basic_transaction'] = 'success';
                } else {
                    echo "✗ 事务回滚未生效\n";
                    $this->testResults['basic_transaction'] = 'rollback_failed';
                }
            } else {
                // 提交事务
                $this->connection->commit();
                echo "✓ 事务提交成功\n";

                // 清理测试数据
                $this->connection->delete('official', ['article_id' => $testId]);
                echo "✓ 测试数据清理完成\n";
                $this->testResults['basic_transaction'] = 'success';
            }

        } catch (Exception $e) {
            try {
                $this->connection->rollBack();
            } catch (Exception $rollbackEx) {
                echo "✗ 回滚也失败: " . $rollbackEx->getMessage() . "\n";
            }

            echo "✗ 基础事务测试失败: " . $e->getMessage() . "\n";
            $this->testResults['basic_transaction'] = 'error: ' . $e->getMessage();
        }

        echo "\n";
    }

    private function testSyncTransaction(string $accountId): void
    {
        echo "=== 4. 同步服务事务测试 ===\n";

        try {
            // 检查账号是否存在
            $account = $this->accountRepository->find($accountId);
            if (!$account) {
                echo "✗ 公众号账号不存在: {$accountId}\n";
                $this->testResults['sync_transaction'] = 'account_not_found';
                return;
            }

            echo "✓ 找到公众号账号: " . ($account->getName() ?: $accountId) . "\n";

            // 记录同步前的数据量
            $countBefore = $this->connection->fetchOne("SELECT COUNT(*) FROM official");
            echo "✓ 同步前数据量: {$countBefore}\n";

            // 执行同步测试
            if ($this->testType === 'dry_run') {
                echo "⚠️  干运行模式，不执行实际同步\n";
                $this->testResults['sync_transaction'] = 'dry_run';
            } else {
                // 监控事务状态
                $originalLogLevel = $this->enableTransactionLogging();

                try {
                    // 绕过锁进行同步测试
                    $result = $this->syncService->syncArticles($accountId, false, true);

                    echo "✓ 同步服务调用完成\n";
                    echo "✓ 同步结果: " . ($result['success'] ? '成功' : '失败') . "\n";
                    echo "✓ 同步消息: " . ($result['message'] ?? '无消息') . "\n";

                    if (isset($result['stats'])) {
                        $stats = $result['stats'];
                        echo "✓ 统计信息: 创建{$stats['created']}, 更新{$stats['updated']}, 跳过{$stats['skipped']}, 失败{$stats['failed']}\n";
                    }

                    $this->testResults['sync_transaction'] = $result;

                } catch (Exception $e) {
                    echo "✗ 同步服务异常: " . $e->getMessage() . "\n";
                    $this->testResults['sync_transaction'] = 'error: ' . $e->getMessage();
                } finally {
                    $this->restoreTransactionLogging($originalLogLevel);
                }
            }

            // 记录同步后的数据量
            $countAfter = $this->connection->fetchOne("SELECT COUNT(*) FROM official");
            echo "✓ 同步后数据量: {$countAfter}\n";
            echo "✓ 数据变化: " . ($countAfter - $countBefore) . " 条\n";

        } catch (Exception $e) {
            echo "✗ 同步事务测试失败: " . $e->getMessage() . "\n";
            $this->testResults['sync_transaction'] = 'error: ' . $e->getMessage();
        }

        echo "\n";
    }

    private function testErrorHandling(): void
    {
        echo "=== 5. 错误处理测试 ===\n";

        try {
            // 测试违反约束的错误
            $this->connection->beginTransaction();

            try {
                // 尝试插入重复ID（如果表有唯一约束）
                $this->connection->insert('official', [
                    'title' => 'Error Test',
                    'content' => 'Error test content',
                    'article_id' => 'duplicate_test',
                    'status' => 2,
                    'create_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                $this->connection->insert('official', [
                    'title' => 'Error Test Duplicate',
                    'content' => 'Error test content duplicate',
                    'article_id' => 'duplicate_test', // 重复的article_id
                    'status' => 2,
                    'create_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                $this->connection->commit();
                echo "⚠️  重复插入未报错，可能无唯一约束\n";

            } catch (Exception $e) {
                $this->connection->rollBack();
                echo "✓ 重复插入被正确阻止: " . $e->getMessage() . "\n";
                $this->testResults['error_handling']['duplicate'] = 'handled';
            }

            // 测试空值约束
            try {
                $this->connection->beginTransaction();

                $this->connection->insert('official', [
                    'title' => '', // 空标题
                    'content' => 'Test content',
                    'article_id' => 'empty_title_test',
                    'status' => 2,
                    'create_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                $this->connection->commit();
                echo "⚠️  空标题插入成功，可能无NOT NULL约束\n";

            } catch (Exception $e) {
                $this->connection->rollBack();
                echo "✓ 空标题被正确阻止: " . $e->getMessage() . "\n";
                $this->testResults['error_handling']['null_constraint'] = 'handled';
            }

            $this->testResults['error_handling']['status'] = 'completed';

        } catch (Exception $e) {
            echo "✗ 错误处理测试失败: " . $e->getMessage() . "\n";
            $this->testResults['error_handling'] = 'error: ' . $e->getMessage();
        }

        echo "\n";
    }

    private function testRollbackMechanism(): void
    {
        echo "=== 6. 回滚机制测试 ===\n";

        try {
            // 记录测试前状态
            $countBefore = $this->connection->fetchOne("SELECT COUNT(*) FROM official");

            // 开始事务并执行多个操作
            $this->connection->beginTransaction();

            $testIds = [];
            for ($i = 1; $i <= 3; $i++) {
                $testId = 'rollback_test_' . $i . '_' . time();
                $testIds[] = $testId;

                $this->connection->insert('official', [
                    'title' => "Rollback Test {$i}",
                    'content' => "Test content for rollback test {$i}",
                    'article_id' => $testId,
                    'status' => 2,
                    'create_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }

            // 验证事务内数据
            $countInTransaction = $this->connection->fetchOne("SELECT COUNT(*) FROM official WHERE article_id LIKE 'rollback_test_%'");
            echo "✓ 事务内插入了 {$countInTransaction} 条测试数据\n";

            // 故意触发错误并回滚
            try {
                // 尝试插入违反约束的数据
                $this->connection->insert('official', [
                    'title' => str_repeat('x', 1000), // 超长标题
                    'content' => 'Test',
                    'article_id' => 'trigger_rollback',
                    'status' => 2,
                    'create_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                $this->connection->commit();
                echo "⚠️  未触发回滚，手动回滚测试\n";
                $this->connection->rollBack();

            } catch (Exception $e) {
                $this->connection->rollBack();
                echo "✓ 触发异常并回滚: " . $e->getMessage() . "\n";
            }

            // 验证回滚结果
            $countAfterRollback = $this->connection->fetchOne("SELECT COUNT(*) FROM official");
            $rollbackTestCount = $this->connection->fetchOne("SELECT COUNT(*) FROM official WHERE article_id LIKE 'rollback_test_%'");

            echo "✓ 回滚后总数据量: {$countAfterRollback}\n";
            echo "✓ 回滚后测试数据量: {$rollbackTestCount}\n";

            if ($countBefore == $countAfterRollback && $rollbackTestCount == 0) {
                echo "✓ 回滚机制正常工作\n";
                $this->testResults['rollback_mechanism'] = 'success';
            } else {
                echo "✗ 回滚机制存在问题\n";
                $this->testResults['rollback_mechanism'] = 'failed';
            }

        } catch (Exception $e) {
            try {
                $this->connection->rollBack();
            } catch (Exception $rollbackEx) {
                echo "✗ 回滚失败: " . $rollbackEx->getMessage() . "\n";
            }

            echo "✗ 回滚机制测试失败: " . $e->getMessage() . "\n";
            $this->testResults['rollback_mechanism'] = 'error: ' . $e->getMessage();
        }

        echo "\n";
    }

    private function checkDeadlocks(): void
    {
        echo "=== 7. 死锁检查 ===\n";

        try {
            // 检查最近的死锁
            $deadlocks = $this->connection->fetchOne("SHOW STATUS LIKE 'Innodb_deadlocks'");
            echo "✓ 历史死锁次数: " . ($deadlocks['Value'] ?? '0') . "\n";

            // 检查锁等待
            $lockWaits = $this->connection->fetchOne("SHOW STATUS LIKE 'Innodb_row_lock_waits'");
            echo "✓ 锁等待次数: " . ($lockWaits['Value'] ?? '0') . "\n";

            // 检查锁等待时间
            $lockTime = $this->connection->fetchOne("SHOW STATUS LIKE 'Innodb_row_lock_time'");
            echo "✓ 锁等待总时间: " . ($lockTime['Value'] ?? '0') . " ms\n";

            // 检查当前锁
            $currentLocks = $this->connection->fetchAllAssociative("
                SELECT object_name, lock_type, lock_mode, lock_status, owner_thread_id
                FROM performance_schema.data_locks
                WHERE object_name IS NOT NULL
                LIMIT 10
            ");

            if (!empty($currentLocks)) {
                echo "⚠️  发现当前活跃锁:\n";
                foreach ($currentLocks as $lock) {
                    echo "  - {$lock['object_name']}: {$lock['lock_type']} {$lock['lock_mode']} ({$lock['lock_status']})\n";
                }
            } else {
                echo "✓ 当前无活跃锁\n";
            }

            $this->testResults['deadlocks'] = [
                'total_deadlocks' => $deadlocks['Value'] ?? 0,
                'lock_waits' => $lockWaits['Value'] ?? 0,
                'lock_time' => $lockTime['Value'] ?? 0,
                'current_locks' => count($currentLocks)
            ];

        } catch (Exception $e) {
            echo "✗ 死锁检查失败: " . $e->getMessage() . "\n";
            $this->testResults['deadlocks'] = 'error: ' . $e->getMessage();
        }

        echo "\n";
    }

    private function enableTransactionLogging(): array
    {
        // 保存当前日志级别
        $originalLevel = $this->connection->fetchOne('SELECT @@log_error_verbosity') ?? 2;

        try {
            // 启用详细日志（如果权限允许）
            $this->connection->executeStatement('SET GLOBAL log_error_verbosity = 3');
        } catch (Exception $e) {
            // 忽略权限错误
        }

        return $originalLevel;
    }

    private function restoreTransactionLogging(int $originalLevel): void
    {
        try {
            $this->connection->executeStatement('SET GLOBAL log_error_verbosity = ?', [$originalLevel]);
        } catch (Exception $e) {
            // 忽略权限错误
        }
    }

    private function generateDebugReport(): void
    {
        echo "=== 调试报告 ===\n";

        $issues = [];
        $recommendations = [];

        // 分析测试结果
        if (isset($this->testResults['basic_transaction']) && $this->testResults['basic_transaction'] !== 'success') {
            $issues[] = "基础事务操作存在问题";
            $recommendations[] = "检查数据库事务配置和权限";
        }

        if (isset($this->testResults['sync_transaction']) && is_string($this->testResults['sync_transaction'])) {
            $issues[] = "同步服务事务处理失败";
            $recommendations[] = "检查同步服务逻辑和错误处理";
        }

        if (isset($this->testResults['rollback_mechanism']) && $this->testResults['rollback_mechanism'] !== 'success') {
            $issues[] = "事务回滚机制异常";
            $recommendations[] = "检查事务隔离级别和锁机制";
        }

        if (isset($this->testResults['deadlocks']['current_locks']) && $this->testResults['deadlocks']['current_locks'] > 0) {
            $issues[] = "存在活跃锁，可能影响并发";
            $recommendations[] = "检查长时间运行的查询和锁等待";
        }

        // 输出问题和建议
        if (empty($issues)) {
            echo "✅ 事务和错误处理正常\n";
        } else {
            echo "❌ 发现问题:\n";
            foreach ($issues as $issue) {
                echo "  - {$issue}\n";
            }
        }

        if (!empty($recommendations)) {
            echo "\n💡 建议:\n";
            foreach ($recommendations as $rec) {
                echo "  - {$rec}\n";
            }
        }

        // 保存详细报告
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'test_type' => $this->testType,
            'test_results' => $this->testResults,
            'issues' => $issues,
            'recommendations' => $recommendations
        ];

        $reportFile = __DIR__ . '/transaction_debug_report_' . date('Ymd_His') . '.json';
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "\n详细报告已保存到: {$reportFile}\n";
    }
}

// 主执行逻辑
$accountId = $argv[1] ?? '';
$testType = $argv[2] ?? 'dry_run';

try {
    $debugger = new TransactionDebugger();
    $debugger->debug($accountId, $testType);
} catch (\Exception $e) {
    echo "事务调试失败: " . $e->getMessage() . "\n";
    echo "堆栈跟踪: " . $e->getTraceAsString() . "\n";
    exit(1);
}
