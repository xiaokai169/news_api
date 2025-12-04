<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use App\Service\WechatApiService;
use App\Repository\WechatPublicAccountRepository;

echo "=== 高级微信同步诊断 ===\n\n";

try {
    $kernel = new Kernel('dev', true);
    $kernel->boot();
    $container = $kernel->getContainer();
    $entityManager = $container->get('doctrine.orm.entity_manager');
    $accountRepository = $entityManager->getRepository(\App\Entity\WechatPublicAccount::class);

    echo "1. 检查公众号账户配置:\n";
    $accounts = $accountRepository->findAll();

    if (empty($accounts)) {
        echo "   ❌ 没有找到任何公众号账户\n";
        exit(1);
    }

    foreach ($accounts as $account) {
        echo "   公众号ID: " . $account->getId() . "\n";
        echo "   名称: " . $account->getName() . "\n";
        echo "   AppID: " . $account->getAppId() . "\n";
        echo "   AppSecret: " . (empty($account->getAppSecret()) ? '❌ 未配置' : '✅ 已配置') . "\n";
        echo "   状态: " . ($account->isActive() ? '✅ 激活' : '❌ 未激活') . "\n";
        echo "   创建时间: " . $account->getCreatedAt()->format('Y-m-d H:i:s') . "\n";
        echo "\n";

        // 测试access_token获取
        echo "   测试access_token获取...\n";

        // 直接测试微信API调用
        $appId = $account->getAppId();
        $appSecret = $account->getAppSecret();

        if (empty($appId) || empty($appSecret)) {
            echo "   ❌ AppID或AppSecret为空\n";
            continue;
        }

        // 使用原生curl进行详细测试
        $ch = curl_init();
        $url = 'https://api.weixin.qq.com/cgi-bin/token';
        $params = [
            'grant_type' => 'client_credential',
            'appid' => $appId,
            'secret' => $appSecret
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url . '?' . http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; WeChatAPI/1.0)',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        echo "   HTTP状态码: $httpCode\n";
        echo "   请求URL: " . $info['url'] . "\n";
        echo "   总时间: " . $info['total_time'] . "s\n";
        echo "   连接时间: " . $info['connect_time'] . "s\n";

        if ($error) {
            echo "   ❌ Curl错误: $error\n";
        } else {
            echo "   响应内容: " . substr($response, 0, 200) . "...\n";

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "   ❌ JSON解析错误: " . json_last_error_msg() . "\n";
            } else {
                if (isset($data['access_token'])) {
                    echo "   ✅ access_token获取成功\n";
                    echo "   token长度: " . strlen($data['access_token']) . "\n";
                    echo "   过期时间: " . ($data['expires_in'] ?? '未知') . "秒\n";
                } else {
                    echo "   ❌ access_token获取失败\n";
                    echo "   错误码: " . ($data['errcode'] ?? '未知') . "\n";
                    echo "   错误信息: " . ($data['errmsg'] ?? '未知') . "\n";

                    // 分析具体错误
                    $errcode = $data['errcode'] ?? 0;
                    switch ($errcode) {
                        case 40001:
                            echo "   💡 提示: AppSecret可能错误或已过期\n";
                            break;
                        case 40013:
                            echo "   💡 提示: AppID无效\n";
                            break;
                        case 40125:
                            echo "   💡 提示: AppSecret无效\n";
                            break;
                        case 40009:
                            echo "   💡 提示: IP地址不在白名单中（即使配置了也可能有延迟）\n";
                            break;
                        case 45009:
                            echo "   💡 提示: API调用次数超限\n";
                            break;
                        case -1:
                            echo "   💡 提示: 微信服务器繁忙，请稍后重试\n";
                            break;
                        default:
                            echo "   💡 提示: 未知错误码 $errcode\n";
                    }
                }
            }
        }

        echo "\n";
    }

    echo "2. 检查服务器网络环境:\n";

    // 检查DNS解析
    $dns = dns_get_record('api.weixin.qq.com', DNS_A);
    if ($dns) {
        echo "   ✅ DNS解析正常:\n";
        foreach ($dns as $record) {
            echo "     - " . $record['ip'] . "\n";
        }
    } else {
        echo "   ❌ DNS解析失败\n";
    }

    // 检查网络连通性
    $ping = exec('ping -c 1 api.weixin.qq.com 2>&1', $output, $returnCode);
    if ($returnCode === 0) {
        echo "   ✅ 网络连通正常\n";
    } else {
        echo "   ⚠️ 网络连通可能有问题\n";
    }

    echo "\n3. 检查服务器时间同步:\n";
    $serverTime = time();
    $ntpTime = file_get_contents('http://worldtimeapi.org/api/timezone/Asia/Shanghai');
    if ($ntpTime) {
        $ntpData = json_decode($ntpTime, true);
        if (isset($ntpData['unixtime'])) {
            $timeDiff = abs($serverTime - $ntpData['unixtime']);
            if ($timeDiff < 300) { // 5分钟内
                echo "   ✅ 时间同步正常 (差值: {$timeDiff}秒)\n";
            } else {
                echo "   ⚠️ 时间可能不同步 (差值: {$timeDiff}秒)\n";
            }
        }
    }

    echo "\n4. 检查SSL证书:\n";
    $stream = stream_context_create([
        'ssl' => [
            'capture_peer_cert' => true,
            'capture_peer_cert_chain' => true,
        ]
    ]);

    $socket = stream_socket_client('ssl://api.weixin.qq.com:443', $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $stream);
    if ($socket) {
        echo "   ✅ SSL连接正常\n";
        fclose($socket);
    } else {
        echo "   ❌ SSL连接失败: $errstr\n";
    }

    $kernel->shutdown();

} catch (Exception $e) {
    echo "❌ 诊断过程中发生异常: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== 高级诊断完成 ===\n";
