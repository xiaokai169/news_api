<?php
require_once __DIR__.'/../vendor/autoload.php';

use App\Service\DatabaseMonitorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

// 模拟Symfony环境
$_SERVER['APP_ENV'] = 'prod';
$_SERVER['APP_DEBUG'] = false;

class DatabaseDiagnosis {
    private $entityManager;

    public function __construct() {
        // 加载Symfony内核
        $kernel = new App\Kernel('prod', false);
        $kernel->boot();
        $this->entityManager = $kernel->getContainer()->get('doctrine.orm.entity_manager');
    }

    public function diagnose() {
        $results = [];

        echo "=== 数据库综合诊断报告 ===\n\n";

        // 1. 检查关键表的存在和结构
        echo "1. 检查关键表的存在和结构\n";
        echo "================================\n";

        $tables_to_check = [
            'official' => '存储同步文章',
            'wechat_public_account' => '微信公众号配置',
            'sys_news_article_category' => '文章分类',
            'distributed_locks' => '同步锁状态'
        ];

        foreach ($tables_to_check as $table => $description) {
            echo "检查表: {$table} ({$description})\n";

            try {
                $sql = "SHOW TABLES LIKE :table";
                $stmt = $this->entityManager->getConnection()->prepare($sql);
                $stmt->bindValue('table', $table);
                $result = $stmt->executeQuery()->fetchAllAssociative();

                if (!empty($result)) {
                    echo "  ✓ 表存在\n";

                    // 获取表结构
                    $structure = $this->entityManager->getConnection()->fetchAllAssociative("DESCRIBE {$table}");
                    echo "  字段信息:\n";
                    foreach ($structure as $field) {
                        echo "    - {$field['Field']}: {$field['Type']} ({$field['Null']}, {$field['Key']})\n";
                    }
                    $results[$table]['exists'] = true;
                    $results[$table]['structure'] = $structure;
                } else {
                    echo "  ✗ 表不存在\n";
                    $results[$table]['exists'] = false;
                }
            } catch (Exception $e) {
                echo "  ✗ 检查失败: " . $e->getMessage() . "\n";
                $results[$table]['error'] = $e->getMessage();
            }
            echo "\n";
        }

        // 2. 验证数据完整性
        echo "2. 验证数据完整性\n";
        echo "==================\n";

        // 检查official表数据
        if ($results['official']['exists'] ?? false) {
            try {
                $count = $this->entityManager->getConnection()->fetchOne("SELECT COUNT(*) FROM official");
                echo "official表记录数量: {$count}\n";
                $results['official']['count'] = $count;

                if ($count > 0) {
                    // 获取最新几条记录
                    $latest = $this->entityManager->getConnection()->fetchAllAssociative(
                        "SELECT * FROM official ORDER BY id DESC LIMIT 5"
                    );
                    echo "最新5条记录:\n";
                    foreach ($latest as $record) {
                        echo "  ID: {$record['id']}, 标题: {$record['title']}, 创建时间: {$record['created_at']}\n";
                    }
                    $results['official']['latest_records'] = $latest;
                }
            } catch (Exception $e) {
                echo "检查official表数据失败: " . $e->getMessage() . "\n";
            }
        }
        echo "\n";

        // 检查sys_news_article_category表中ID=18的分类
        if ($results['sys_news_article_category']['exists'] ?? false) {
            try {
                $category = $this->entityManager->getConnection()->fetchAssociative(
                    "SELECT * FROM sys_news_article_category WHERE id = 18"
                );
                if ($category) {
                    echo "✓ 找到ID=18的分类: {$category['name']}\n";
                    $results['category_18'] = $category;
                } else {
                    echo "✗ 未找到ID=18的分类\n";
                    $results['category_18'] = null;
                }
            } catch (Exception $e) {
                echo "检查分类失败: " . $e->getMessage() . "\n";
            }
        }
        echo "\n";

        // 检查wechat_public_account表
        if ($results['wechat_public_account']['exists'] ?? false) {
            try {
                $accounts = $this->entityManager->getConnection()->fetchAllAssociative(
                    "SELECT * FROM wechat_public_account WHERE status = 'active'"
                );
                echo "活跃微信公众号数量: " . count($accounts) . "\n";
                foreach ($accounts as $account) {
                    echo "  账户ID: {$account['id']}, 名称: {$account['name']}, AppId: {$account['app_id']}\n";
                }
                $results['wechat_accounts'] = $accounts;
            } catch (Exception $e) {
                echo "检查微信公众号配置失败: " . $e->getMessage() . "\n";
            }
        }
        echo "\n";

        // 检查分布式锁
        if ($results['distributed_locks']['exists'] ?? false) {
            try {
                $locks = $this->entityManager->getConnection()->fetchAllAssociative(
                    "SELECT * FROM distributed_locks WHERE expire_time > NOW()"
                );
                echo "活跃分布式锁数量: " . count($locks) . "\n";
                foreach ($locks as $lock) {
                    echo "  锁键: {$lock['lock_key']}, 过期时间: {$lock['expire_time']}\n";
                }
                $results['active_locks'] = $locks;
            } catch (Exception $e) {
                echo "检查分布式锁失败: " . $e->getMessage() . "\n";
            }
        }
        echo "\n";

        // 3. 检查可能的约束问题
        echo "3. 检查可能的约束问题\n";
        echo "====================\n";

        // 检查外键约束
        try {
            $constraints = $this->entityManager->getConnection()->fetchAllAssociative("
                SELECT
                    TABLE_NAME,
                    COLUMN_NAME,
                    CONSTRAINT_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE REFERENCED_TABLE_SCHEMA = 'official_website'
                AND TABLE_NAME IN ('official', 'wechat_public_account', 'sys_news_article_category')
            ");

            echo "外键约束检查:\n";
            foreach ($constraints as $constraint) {
                echo "  {$constraint['TABLE_NAME']}.{$constraint['COLUMN_NAME']} -> {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}\n";
            }
            $results['constraints'] = $constraints;
        } catch (Exception $e) {
            echo "检查外键约束失败: " . $e->getMessage() . "\n";
        }
        echo "\n";

        // 4. 分析同步状态
        echo "4. 分析同步状态\n";
        echo "==============\n";

        // 检查最近的同步时间戳
        if ($results['official']['exists'] ?? false) {
            try {
                $latest_sync = $this->entityManager->getConnection()->fetchAssociative("
                    SELECT MAX(created_at) as latest_sync, COUNT(*) as total_count
                    FROM official
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ");
                echo "最近24小时同步:\n";
                echo "  最新同步时间: " . ($latest_sync['latest_sync'] ?? '无') . "\n";
                echo "  同步文章数量: " . ($latest_sync['total_count'] ?? 0) . "\n";
                $results['recent_sync'] = $latest_sync;
            } catch (Exception $e) {
                echo "检查同步状态失败: " . $e->getMessage() . "\n";
            }
        }
        echo "\n";

        // 5. 问题分析
        echo "5. 问题分析\n";
        echo "==========\n";

        $issues = [];

        if (!($results['official']['exists'] ?? false)) {
            $issues[] = "official表不存在 - 这是存储同步文章的核心表";
        }

        if (!($results['sys_news_article_category']['exists'] ?? false)) {
            $issues[] = "sys_news_article_category表不存在 - 文章分类表缺失";
        } elseif (!($results['category_18'] ?? null)) {
            $issues[] = "ID=18的文章分类不存在 - 同步需要这个分类";
        }

        if (!($results['wechat_public_account']['exists'] ?? false)) {
            $issues[] = "wechat_public_account表不存在 - 微信公众号配置表缺失";
        } elseif (empty($results['wechat_accounts'])) {
            $issues[] = "没有活跃的微信公众号配置";
        }

        if (($results['official']['count'] ?? 0) == 0) {
            $issues[] = "official表中没有任何数据 - 同步可能从未成功";
        }

        if (!empty($results['active_locks'])) {
            $issues[] = "存在活跃的分布式锁 - 可能阻塞同步进程";
        }

        if (empty($issues)) {
            echo "✓ 未发现明显问题\n";
        } else {
            echo "发现以下问题:\n";
            foreach ($issues as $i => $issue) {
                echo "  " . ($i + 1) . ". {$issue}\n";
            }
        }

        $results['issues'] = $issues;

        // 保存诊断结果到文件
        file_put_contents(__DIR__ . '/database_diagnosis_report.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        echo "\n=== 诊断完成 ===\n";
        echo "详细报告已保存到: database_diagnosis_report.json\n";

        return $results;
    }
}

// 执行诊断
try {
    $diagnosis = new DatabaseDiagnosis();
    $results = $diagnosis->diagnose();
} catch (Exception $e) {
    echo "诊断过程中发生错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
