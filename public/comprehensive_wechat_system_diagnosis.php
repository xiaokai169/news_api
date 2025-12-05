<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;
use Doctrine\ORM\Tools\SchemaValidator;

echo "=== 微信同步系统综合诊断 ===<br>\n";
echo "诊断时间: " . date('Y-m-d H:i:s') . "<br>\n";

// 初始化Symfony容器
$kernel = new \App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

$diagnosis = [
    'timestamp' => date('Y-m-d H:i:s'),
    'results' => [],
    'errors' => [],
    'warnings' => [],
    'recommendations' => []
];

// 1. 检查数据库连接
echo "<h2>1. 数据库连接检查</h2>\n";
try {
    $entityManager = $container->get('doctrine.orm.entity_manager');
    $connection = $entityManager->getConnection();
    $connection->connect();

    echo "✅ 数据库连接成功<br>\n";
    $diagnosis['results']['database_connection'] = 'success';

    // 检查数据库版本
    $version = $connection->fetchOne('SELECT VERSION()');
    echo "📊 MySQL版本: " . $version . "<br>\n";

} catch (\Exception $e) {
    echo "❌ 数据库连接失败: " . $e->getMessage() . "<br>\n";
    $diagnosis['errors']['database_connection'] = $e->getMessage();
}

// 2. 检查必要的数据库表
echo "<h2>2. 数据库表结构检查</h2>\n";
try {
    $tables = $connection->fetchAllAssociative("SHOW TABLES");
    $tableNames = array_map(function($table) {
        return array_values($table)[0];
    }, $tables);

    $requiredTables = [
        'wechat_public_account',
        'official',
        'distributed_locks',
        'sys_news_article_category'
    ];

    foreach ($requiredTables as $table) {
        if (in_array($table, $tableNames)) {
            echo "✅ 表 {$table} 存在<br>\n";
            $diagnosis['results']['table_' . $table] = 'exists';
        } else {
            echo "❌ 表 {$table} 不存在<br>\n";
            $diagnosis['errors']['table_' . $table] = 'missing';
        }
    }

} catch (\Exception $e) {
    echo "❌ 检查数据库表失败: " . $e->getMessage() . "<br>\n";
    $diagnosis['errors']['table_check'] = $e->getMessage();
}

// 3. 检查微信公众号账户数据
echo "<h2>3. 微信公众号账户检查</h2>\n";
try {
    $wechatAccountRepo = $entityManager->getRepository(\App\Entity\WechatPublicAccount::class);
    $accounts = $wechatAccountRepo->findAll();

    echo "📊 找到 " . count($accounts) . " 个公众号账户<br>\n";
    $diagnosis['results']['wechat_accounts_count'] = count($accounts);

    if (empty($accounts)) {
        echo "❌ 没有配置任何公众号账户<br>\n";
        $diagnosis['warnings']['no_wechat_accounts'] = '没有配置公众号账户';
    } else {
        foreach ($accounts as $account) {
            $accountId = $account->getId();
            $appId = $account->getAppId();
            $isActive = $account->isActive() ? '是' : '否';

            echo "📱 账户ID: {$accountId}, APPID: {$appId}, 激活: {$isActive}<br>\n";

            // 检查关键配置
            if (!$account->getAppId()) {
                echo "⚠️ 账户 {$accountId} 缺少APPID<br>\n";
                $diagnosis['warnings']['account_' . $accountId . '_no_appid'] = '缺少APPID';
            }

            if (!$account->getAppSecret()) {
                echo "⚠️ 账户 {$accountId} 缺少APPSECRET<br>\n";
                $diagnosis['warnings']['account_' . $accountId . '_no_secret'] = '缺少APPSECRET';
            }

            // 测试获取access_token
            if ($account->getAppId() && $account->getAppSecret()) {
                echo "🔑 测试账户 {$accountId} 的access_token获取...<br>\n";
                $tokenResult = testAccessToken($account->getAppId(), $account->getAppSecret());
                if ($tokenResult['success']) {
                    echo "✅ 账户 {$accountId} access_token获取成功<br>\n";
                    $diagnosis['results']['account_' . $accountId . '_token'] = 'success';
                } else {
                    echo "❌ 账户 {$accountId} access_token获取失败: " . $tokenResult['error'] . "<br>\n";
                    $diagnosis['errors']['account_' . $accountId . '_token'] = $tokenResult['error'];
                }
            }
        }
    }

} catch (\Exception $e) {
    echo "❌ 检查公众号账户失败: " . $e->getMessage() . "<br>\n";
    $diagnosis['errors']['wechat_account_check'] = $e->getMessage();
}

// 4. 检查已同步的文章数据
echo "<h2>4. 已同步文章数据检查</h2>\n";
try {
    $officialRepo = $entityManager->getRepository(\App\Entity\Official::class);
    $totalArticles = $officialRepo->count([]);
    $activeArticles = $officialRepo->countActivePublicArticles();

    echo "📊 总文章数: {$totalArticles}<br>\n";
    echo "📊 活跃文章数: {$activeArticles}<br>\n";

    $diagnosis['results']['total_articles'] = $totalArticles;
    $diagnosis['results']['active_articles'] = $activeArticles;

    if ($totalArticles === 0) {
        echo "⚠️ 没有同步任何文章<br>\n";
        $diagnosis['warnings']['no_articles'] = '没有同步的文章';
    } else {
        // 检查最近的文章
        $recentArticles = $officialRepo->findBy([], ['createAt' => 'DESC'], 5);
        echo "<br>📝 最近5篇文章:<br>\n";
        foreach ($recentArticles as $article) {
            $title = substr($article->getTitle(), 0, 50);
            $articleId = $article->getArticleId() ?: '无';
            $createTime = $article->getCreateAt()->format('Y-m-d H:i:s');
            echo "- {$title}... (ID: {$articleId}, 创建: {$createTime})<br>\n";
        }
    }

} catch (\Exception $e) {
    echo "❌ 检查文章数据失败: " . $e->getMessage() . "<br>\n";
    $diagnosis['errors']['article_check'] = $e->getMessage();
}

// 5. 检查分布式锁系统
echo "<h2>5. 分布式锁系统检查</h2>\n";
try {
    $lockService = $container->get(\App\Service\DistributedLockService::class);

    // 测试锁的获取和释放
    $testLockKey = 'diagnosis_test_' . time();
    $lockAcquired = $lockService->acquireLock($testLockKey, 60);

    if ($lockAcquired) {
        echo "✅ 分布式锁获取成功<br>\n";
        $diagnosis['results']['distributed_lock_acquire'] = 'success';

        $lockReleased = $lockService->releaseLock($testLockKey);
        if ($lockReleased) {
            echo "✅ 分布式锁释放成功<br>\n";
            $diagnosis['results']['distributed_lock_release'] = 'success';
        } else {
            echo "❌ 分布式锁释放失败<br>\n";
            $diagnosis['errors']['distributed_lock_release'] = 'failed';
        }
    } else {
        echo "❌ 分布式锁获取失败<br>\n";
        $diagnosis['errors']['distributed_lock_acquire'] = 'failed';
    }

    // 检查是否有卡住的锁
    $stuckLocks = checkStuckLocks($connection);
    if (!empty($stuckLocks)) {
        echo "⚠️ 发现 " . count($stuckLocks) . " 个可能卡住的锁<br>\n";
        $diagnosis['warnings']['stuck_locks'] = $stuckLocks;
    } else {
        echo "✅ 没有发现卡住的锁<br>\n";
    }

} catch (\Exception $e) {
    echo "❌ 检查分布式锁失败: " . $e->getMessage() . "<br>\n";
    $diagnosis['errors']['distributed_lock_check'] = $e->getMessage();
}

// 6. 检查日志系统
echo "<h2>6. 日志系统检查</h2>\n";
try {
    $logger = $container->get('monolog.logger.wechat');

    // 测试日志记录
    $logger->info('诊断脚本日志测试 - ' . date('Y-m-d H:i:s'));
    echo "✅ 微信日志记录正常<br>\n";
    $diagnosis['results']['wechat_logger'] = 'success';

    // 检查日志文件
    $logPath = __DIR__ . '/../var/log/dev.log';
    if (file_exists($logPath)) {
        $logSize = filesize($logPath);
        echo "📊 日志文件大小: " . round($logSize / 1024 / 1024, 2) . " MB<br>\n";
        $diagnosis['results']['log_file_size'] = $logSize;

        // 检查最近的错误日志
        $recentLogs = getRecentErrorLogs($logPath, 10);
        if (!empty($recentLogs)) {
            echo "⚠️ 发现最近的错误日志:<br>\n";
            foreach ($recentLogs as $log) {
                echo "- " . htmlspecialchars(substr($log, 0, 100)) . "...<br>\n";
            }
            $diagnosis['warnings']['recent_errors'] = $recentLogs;
        }
    } else {
        echo "⚠️ 日志文件不存在<br>\n";
        $diagnosis['warnings']['no_log_file'] = '日志文件不存在';
    }

} catch (\Exception $e) {
    echo "❌ 检查日志系统失败: " . $e->getMessage() . "<br>\n";
    $diagnosis['errors']['logger_check'] = $e->getMessage();
}

// 7. 生成诊断报告
echo "<h2>7. 诊断总结</h2>\n";

$totalErrors = count($diagnosis['errors']);
$totalWarnings = count($diagnosis['warnings']);
$totalSuccess = count($diagnosis['results']);

echo "📊 诊断统计:<br>\n";
echo "- 成功项目: {$totalSuccess}<br>\n";
echo "- 警告项目: {$totalWarnings}<br>\n";
echo "- 错误项目: {$totalErrors}<br>\n";

if ($totalErrors === 0 && $totalWarnings === 0) {
    echo "<br>🎉 系统运行正常，没有发现问题！<br>\n";
    $diagnosis['overall_status'] = 'excellent';
} elseif ($totalErrors === 0) {
    echo "<br>✅ 系统基本正常，但有一些建议优化的项目<br>\n";
    $diagnosis['overall_status'] = 'good';
} else {
    echo "<br>⚠️ 系统存在问题，需要立即处理<br>\n";
    $diagnosis['overall_status'] = 'needs_attention';
}

// 保存诊断报告
$reportFile = __DIR__ . '/wechat_system_diagnosis_report_' . date('Ymd_His') . '.json';
file_put_contents($reportFile, json_encode($diagnosis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "<br>📄 详细诊断报告已保存到: " . basename($reportFile) . "<br>\n";

// 辅助函数
function testAccessToken($appId, $appSecret) {
    try {
        $client = HttpClient::create();
        $response = $client->request('GET', 'https://api.weixin.qq.com/cgi-bin/token', [
            'query' => [
                'grant_type' => 'client_credential',
                'appid' => $appId,
                'secret' => $appSecret,
            ]
        ]);

        $data = $response->toArray();

        if (isset($data['access_token'])) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => $data['errmsg'] ?? '未知错误'];
        }

    } catch (\Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function checkStuckLocks($connection) {
    try {
        $locks = $connection->fetchAllAssociative("
            SELECT lock_key, expire_time
            FROM distributed_locks
            WHERE expire_time < NOW() + INTERVAL 1 HOUR
        ");

        return $locks;
    } catch (\Exception $e) {
        return [];
    }
}

function getRecentErrorLogs($logPath, $limit = 10) {
    if (!file_exists($logPath)) {
        return [];
    }

    $logs = [];
    $lines = file($logPath);
    $lines = array_reverse(array_slice($lines, -$limit * 3));

    foreach ($lines as $line) {
        if (strpos($line, 'ERROR') !== false) {
            $logs[] = trim($line);
            if (count($logs) >= $limit) {
                break;
            }
        }
    }

    return $logs;
}

echo "<br>=== 诊断完成 ===<br>\n";
