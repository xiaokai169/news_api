<?php
echo "=== 微信IP白名单修复验证 ===\n\n";

// 1. 检查当前出口IP
echo "1. 检查当前出口IP:\n";
$ipServices = [
    'https://api.ipify.org',
    'https://ipinfo.io/json'
];

$currentIP = null;
foreach ($ipServices as $service) {
    $context = stream_context_create(['http' => ['timeout' => 5]]);
    $response = @file_get_contents($service, false, $context);

    if ($response) {
        if (strpos($service, 'ipinfo.io') !== false) {
            $data = json_decode($response, true);
            if (isset($data['ip'])) {
                $currentIP = $data['ip'];
                echo "   ✅ 当前IP: " . $currentIP . "\n";
                if (isset($data['org'])) {
                    echo "   运营商: " . $data['org'] . "\n";
                }
                break;
            }
        } else {
            $ip = trim($response);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $currentIP = $ip;
                echo "   ✅ 当前IP: " . $currentIP . "\n";
                break;
            }
        }
    }
}

if (!$currentIP) {
    echo "   ❌ 无法获取当前IP\n";
    exit(1);
}

echo "\n";

// 2. 验证IP是否为目标IP
echo "2. IP验证:\n";
$targetIP = '1.92.157.200';
echo "   目标IP: $targetIP\n";
echo "   当前IP: $currentIP\n";

if ($currentIP === $targetIP) {
    echo "   ✅ IP匹配\n";
} else {
    echo "   ❌ IP不匹配\n";
    echo "   💡 请在微信公众平台白名单中添加: $currentIP\n";
}

echo "\n";

// 3. 测试微信API连通性
echo "3. 测试微信API连通性:\n";

// 先用测试凭据测试网络连通性
$url = 'https://api.weixin.qq.com/cgi-bin/token';
$params = [
    'grant_type' => 'client_credential',
    'appid' => 'test_appid',
    'secret' => 'test_secret'
];

$fullUrl = $url . '?' . http_build_query($params);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $fullUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_USERAGENT => 'WeChat-IP-Verify/1.0'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "   HTTP状态: $httpCode\n";

if ($error) {
    echo "   ❌ 网络错误: $error\n";
} elseif ($response) {
    $data = json_decode($response, true);
    if (isset($data['errcode'])) {
        echo "   ✅ 微信API可访问\n";
        echo "   错误码: " . $data['errcode'] . " - " . ($data['errmsg'] ?? '') . "\n";

        // 分析错误类型
        switch ($data['errcode']) {
            case 40001:
            case 40125:
                echo "   💡 这是正常的凭据错误，说明网络和IP白名单正常\n";
                break;
            case 40009:
                echo "   ❌ IP白名单问题: " . $data['errmsg'] . "\n";
                echo "   💡 需要在微信公众平台添加IP: $currentIP\n";
                break;
            case -1:
                echo "   ⚠️ 微信服务器繁忙，请稍后重试\n";
                break;
        }
    } else {
        echo "   ✅ 微信API响应正常\n";
    }
} else {
    echo "   ❌ 无响应\n";
}

echo "\n";

// 4. 读取真实配置进行测试
echo "4. 测试真实公众号配置:\n";
try {
    require_once __DIR__ . '/../vendor/autoload.php';
    $kernel = new App\Kernel('dev', true);
    $kernel->boot();
    $entityManager = $kernel->getContainer()->get('doctrine.orm.entity_manager');

    $connection = $entityManager->getConnection();
    $sql = "SELECT id, name, app_id, app_secret FROM wechat_public_account WHERE is_active = 1 LIMIT 1";
    $stmt = $connection->prepare($sql);
    $result = $stmt->executeQuery();
    $accounts = $result->fetchAllAssociative();

    if (!empty($accounts)) {
        $account = $accounts[0];
        echo "   测试账户: " . $account['name'] . " (ID: " . $account['id'] . ")\n";

        $realParams = [
            'grant_type' => 'client_credential',
            'appid' => $account['app_id'],
            'secret' => $account['app_secret']
        ];

        $realUrl = $url . '?' . http_build_query($realParams);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $realUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'WeChat-Real-Test/1.0'
        ]);

        $realResponse = curl_exec($ch);
        $realHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $realError = curl_error($ch);
        curl_close($ch);

        echo "   HTTP状态: $realHttpCode\n";

        if ($realError) {
            echo "   ❌ 网络错误: $realError\n";
        } elseif ($realResponse) {
            $realData = json_decode($realResponse, true);
            if (isset($realData['access_token'])) {
                echo "   🎉 ✅ access_token获取成功！\n";
                echo "   💡 IP白名单配置正确\n";
            } else {
                echo "   ❌ access_token获取失败\n";
                echo "   错误码: " . ($realData['errcode'] ?? '未知') . "\n";
                echo "   错误信息: " . ($realData['errmsg'] ?? '未知') . "\n";

                if (isset($realData['errcode']) && $realData['errcode'] === 40009) {
                    echo "   💡 这确认是IP白名单问题\n";
                    echo "   💡 请在微信公众平台添加IP: $currentIP\n";
                }
            }
        } else {
            echo "   ❌ 无响应\n";
        }
    } else {
        echo "   ⚠️ 数据库中没有活跃的公众号账户\n";
    }

    $kernel->shutdown();
} catch (Exception $e) {
    echo "   ❌ 无法测试真实配置: " . $e->getMessage() . "\n";
}

echo "\n=== 修复建议 ===\n";
echo "如果测试失败，请按以下步骤操作:\n";
echo "1. 登录微信公众平台: https://mp.weixin.qq.com\n";
echo "2. 进入: 开发 → 基本配置 → IP白名单\n";
echo "3. 添加IP地址: $currentIP\n";
echo "4. 保存配置，等待5-10分钟生效\n";
echo "5. 重新运行此验证脚本\n";

echo "\n=== 验证完成 ===\n";
