<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use App\Service\WechatApiService;
use App\Service\DistributedLockService;
use App\Service\WechatArticleSyncService;
use App\Repository\WechatPublicAccountRepository;

echo "=== 微信同步错误综合诊断 ===\n\n";

try {
    // 创建 Symfony 应用实例
    $kernel = new Kernel('dev', true);
    $kernel->boot();

    // 获取服务
    $container = $kernel->getContainer();
    $wechatApiService = $container->get('App\Service\WechatApiService');
    $lockService = $container->get('App\Service\DistributedLockService');
    $syncService = $container->get('App\Service\WechatArticleSyncService');
    $accountRepository = $container->get('App\Repository\WechatPublicAccountRepository');

    // 1. 检查服务器IP信息
    echo "=== 步骤1: 检查服务器IP信息 ===\n";

    // 获取服务器外网IP
    $externalIp = file_get_contents('https://api.ipify.org');
    if ($externalIp) {
        echo "✅ 服务器外网IP: $externalIp\n";
    } else {
        echo "❌ 无法获取服务器外网IP\n";
    }

    // 检查IPv6地址
    $ipv6Info = file_get_contents('https://api6.ipify.org?format=json');
    if ($ipv6Info) {
        $ipv6Data = json_decode($ipv6Info, true);
        if (isset($ipv6Data['ip'])) {
            echo "✅ 服务器IPv6: " . $ipv6Data['ip'] . "\n";
        }
    }

    // 获取本地网络接口信息
    echo "本地网络接口:\n";
    $interfaces = net_get_interfaces();
    foreach ($interfaces as $name => $data) {
        if (isset($data['unicast'])) {
            foreach ($data['unicast'] as $addr) {
                if ($addr['family'] === AF_INET) {
                    echo "  - $name: " . $addr['address'] . "\n";
                }
            }
        }
    }
    echo "\n";

    // 2. 测试微信API连接（不使用access_token）
    echo "=== 步骤2: 测试微信API连接 ===\n";

    $client = new \Symfony\Component\HttpClient\HttpClient();
    try {
        $response = $client->request('GET', 'https://api.weixin.qq.com/cgi-bin/token', [
            'query' => [
                'grant_type' => 'client_credential',
                'appid' => 'test_appid',
                'secret' => 'test_secret',
            ],
            'timeout' => 10,
        ]);

        echo "HTTP状态码: " . $response->getStatusCode() . "\n";
        $content = $response->getContent();
        echo "响应内容: " . substr($content, 0, 200) . "...\n";

        $result = json_decode($content, true);
        if (isset($result['errcode']) && $result['errcode'] === -1) {
            echo "❌ 微信API服务器内部错误\n";
        } elseif (isset($result['errcode']) && $result['errcode'] === 40013) {
            echo "✅ 微信API连接正常（无效AppID是预期的）\n";
        } elseif (isset($result['errmsg'])) {
            echo "⚠️ 微信API响应: " . $result['errmsg'] . "\n";
        }

    } catch (\Exception $e) {
        echo "❌ 微信API连接失败: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 3. 测试分布式锁服务
    echo "=== 步骤3: 测试分布式锁服务 ===\n";

    $testLockKey = 'diagnostic_test_lock_' . time();

    // 检查数据库连接
    try {
        $connection = $container->get('doctrine.orm.entity_manager')->getConnection();
        $connection->connect();
        echo "✅ 数据库连接正常\n";
    } catch (\Exception $e) {
        echo "❌ 数据库连接失败: " . $e->getMessage() . "\n";
    }

    // 检查分布式锁表
    try {
        $sql = "SELECT COUNT(*) as count FROM distributed_locks";
        $stmt = $connection->prepare($sql);
        $result = $stmt->executeQuery();
        $count = $result->fetchAssociative()['count'];
        echo "✅ distributed_locks表存在，当前锁数量: $count\n";
    } catch (\Exception $e) {
        echo "❌ distributed_locks表检查失败: " . $e->getMessage() . "\n";
    }

    // 测试锁操作
    echo "测试锁获取...\n";
    $acquired = $lockService->acquireLock($testLockKey, 30);
    echo "锁获取结果: " . ($acquired ? "✅ 成功" : "❌ 失败") . "\n";

    if ($acquired) {
        echo "测试锁状态检查...\n";
        $isLocked = $lockService->isLocked($testLockKey);
        echo "锁状态检查: " . ($isLocked ? "✅ 已锁定" : "❌ 未锁定") . "\n";

        echo "测试锁释放...\n";
        $released = $lockService->releaseLock($testLockKey);
        echo "锁释放结果: " . ($released ? "✅ 成功" : "❌ 失败") . "\n";
    }
    echo "\n";

    // 4. 检查公众号账户配置
    echo "=== 步骤4: 检查公众号账户配置 ===\n";

    $accounts = $accountRepository->findAll();
    echo "公众号账户数量: " . count($accounts) . "\n";

    foreach ($accounts as $account) {
        echo "\n公众号信息:\n";
        echo "  ID: " . $account->getId() . "\n";
        echo "  名称: " . $account->getName() . "\n";
        echo "  AppID: " . $account->getAppId() . "\n";
        echo "  状态: " . ($account->isActive() ? '激活' : '未激活') . "\n";

        // 测试access_token获取
        echo "  测试access_token获取...\n";
        $accessToken = $wechatApiService->getAccessToken($account);
        if ($accessToken) {
            echo "  ✅ access_token获取成功\n";
        } else {
            echo "  ❌ access_token获取失败\n";
        }
    }
    echo "\n";

    // 5. 模拟完整的同步流程（带详细日志）
    echo "=== 步骤5: 模拟完整同步流程 ===\n";

    if (!empty($accounts)) {
        $testAccount = $accounts[0];
        $accountId = $testAccount->getId();

        echo "使用公众号: " . $testAccount->getName() . " ($accountId)\n";

        // 清理可能存在的锁
        $lockKey = 'wechat_sync_' . $accountId;
        if ($lockService->isLocked($lockKey)) {
            echo "清理已存在的锁...\n";
            $lockService->releaseLock($lockKey);
        }

        // 测试同步状态
        echo "获取同步状态...\n";
        $status = $syncService->getSyncStatus($accountId);
        if (isset($status['error'])) {
            echo "❌ 获取同步状态失败: " . $status['error'] . "\n";
        } else {
            echo "✅ 同步状态获取成功\n";
            echo "  正在同步: " . ($status['is_syncing'] ? '是' : '否') . "\n";
        }

        // 尝试执行同步（使用绕过锁模式）
        echo "执行同步测试（绕过锁检查）...\n";
        $syncResult = $syncService->syncArticles($accountId, false, true);

        if ($syncResult['success']) {
            echo "✅ 同步操作成功\n";
            echo "消息: " . $syncResult['message'] . "\n";
        } else {
            echo "❌ 同步操作失败: " . $syncResult['message'] . "\n";
            if (!empty($syncResult['errors'])) {
                echo "详细错误:\n";
                foreach ($syncResult['errors'] as $error) {
                    echo "  - $error\n";
                }
            }
        }
    }
    echo "\n";

    // 6. 诊断结论和建议
    echo "=== 步骤6: 诊断结论和建议 ===\n";

    echo "基于以上测试，可能的问题原因:\n\n";

    echo "1. IP白名单问题:\n";
    echo "   - 如果看到 'invalid ip' 错误，需要在微信公众号后台添加服务器IP到白名单\n";
    echo "   - 服务器IP: $externalIp\n";
    echo "   - 登录微信公众平台 -> 基本配置 -> IP白名单\n\n";

    echo "2. 分布式锁问题:\n";
    echo "   - 如果锁测试失败，检查distributed_locks表结构\n";
    echo "   - 确保数据库连接正常\n";
    echo "   - 检查是否有未释放的锁\n\n";

    echo "3. 微信API配置问题:\n";
    echo "   - 检查AppID和AppSecret是否正确\n";
    echo "   - 确认公众号类型和权限\n\n";

    echo "4. 网络连接问题:\n";
    echo "   - 检查服务器是否能访问微信API\n";
    echo "   - 检查防火墙设置\n\n";

    $kernel->shutdown();

} catch (Exception $e) {
    echo "❌ 诊断过程中发生异常: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== 诊断完成 ===\n";
