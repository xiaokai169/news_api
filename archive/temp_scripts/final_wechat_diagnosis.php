<?php
echo "=== 最终微信同步诊断 ===\n\n";

// 1. 检查真实出口IP
echo "1. 检查真实出口IP:\n";
$ipServices = [
    'https://api.ipify.org',
    'https://ipinfo.io/json',
    'https://httpbin.org/ip'
];

foreach ($ipServices as $service) {
    echo "   检查 $service ...\n";
    $context = stream_context_create([
        'http' => ['timeout' => 5]
    ]);

    $response = @file_get_contents($service, false, $context);
    if ($response) {
        if (strpos($service, 'ipinfo.io') !== false) {
            $data = json_decode($response, true);
            if (isset($data['ip'])) {
                echo "   ✅ 检测到IP: " . $data['ip'] . "\n";
                if (isset($data['org'])) {
                    echo "      运营商: " . $data['org'] . "\n";
                }
                if (isset($data['country'])) {
                    echo "      国家: " . $data['country'] . "\n";
                }
            }
        } elseif (strpos($service, 'httpbin.org') !== false) {
            $data = json_decode($response, true);
            if (isset($data['origin'])) {
                echo "   ✅ 检测到IP: " . $data['origin'] . "\n";
            }
        } else {
            echo "   ✅ 检测到IP: " . trim($response) . "\n";
        }
    } else {
        echo "   ❌ 无法获取IP\n";
    }
}

echo "\n";

// 2. 直接测试微信API
echo "2. 直接测试微信API:\n";
$testAccounts = [
    [
        'appid' => 'test_appid',
        'secret' => 'test_secret',
        'name' => '测试账户'
    ]
];

// 从数据库读取真实配置（如果可能）
try {
    require_once __DIR__ . '/../vendor/autoload.php';
    $kernel = new App\Kernel('dev', true);
    $kernel->boot();
    $entityManager = $kernel->getContainer()->get('doctrine.orm.entity_manager');

    $connection = $entityManager->getConnection();
    $sql = "SELECT id, name, app_id, app_secret, is_active FROM wechat_public_account WHERE is_active = 1 LIMIT 5";
    $stmt = $connection->prepare($sql);
    $result = $stmt->executeQuery();
    $accounts = $result->fetchAllAssociative();

    if (!empty($accounts)) {
        echo "   从数据库读取到 " . count($accounts) . " 个公众号账户:\n\n";
        $testAccounts = [];

        foreach ($accounts as $account) {
            $testAccounts[] = [
                'appid' => $account['app_id'],
                'secret' => $account['app_secret'],
                'name' => $account['name'],
                'id' => $account['id']
            ];
        }
    }

    $kernel->shutdown();
} catch (Exception $e) {
    echo "   ⚠️ 无法读取数据库配置: " . $e->getMessage() . "\n";
    echo "   使用测试配置继续...\n";
}

foreach ($testAccounts as $account) {
    echo "   测试账户: " . $account['name'] . "\n";
    if (isset($account['id'])) {
        echo "   ID: " . $account['id'] . "\n";
    }

    $url = 'https://api.weixin.qq.com/cgi-bin/token';
    $params = [
        'grant_type' => 'client_credential',
        'appid' => $account['appid'],
        'secret' => $account['secret']
    ];

    $fullUrl = $url . '?' . http_build_query($params);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $fullUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'WeChat-API-Diagnostic/1.0'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "     HTTP状态: $httpCode\n";

    if ($error) {
        echo "     ❌ Curl错误: $error\n";
    } elseif ($response) {
        $data = json_decode($response, true);
        if (isset($data['access_token'])) {
            echo "     ✅ access_token获取成功\n";
        } else {
            echo "     ❌ access_token获取失败\n";
            echo "     错误码: " . ($data['errcode'] ?? '未知') . "\n";
            echo "     错误信息: " . ($data['errmsg'] ?? '未知') . "\n";

            // 分析错误
            $errcode = $data['errcode'] ?? 0;
            switch ($errcode) {
                case 40001:
                    echo "     💡 提示: AppSecret可能错误\n";
                    break;
                case 40013:
                    echo "     💡 提示: AppID无效\n";
                    break;
                case 40125:
                    echo "     💡 提示: AppSecret无效\n";
                    break;
                case 40009:
                    echo "     💡 提示: IP地址不在白名单中\n";
                    break;
                case 45009:
                    echo "     💡 提示: API调用次数超限\n";
                    break;
                case -1:
                    echo "     💡 提示: 微信服务器繁忙\n";
                    break;
            }
        }
    } else {
        echo "     ❌ 无响应\n";
    }
    echo "\n";
}

// 3. 网络连通性测试
echo "3. 网络连通性测试:\n";
$hosts = [
    'api.weixin.qq.com' => '微信API服务器',
    'mp.weixin.qq.com' => '微信公众平台'
];

foreach ($hosts as $host => $description) {
    echo "   测试 $description ($host)...\n";

    // DNS解析
    $ips = gethostbynamel($host);
    if ($ips) {
        echo "     ✅ DNS解析成功: " . implode(', ', $ips) . "\n";
    } else {
        echo "     ❌ DNS解析失败\n";
    }

    // 端口连通性
    $connection = @fsockopen('ssl://' . $host, 443, $errno, $errstr, 5);
    if ($connection) {
        echo "     ✅ 443端口连通\n";
        fclose($connection);
    } else {
        echo "     ❌ 443端口不通: $errstr\n";
    }
    echo "\n";
}

echo "=== 诊断完成 ===\n";
echo "\n建议:\n";
echo "1. 如果检测到的IP与配置的白名单IP不符，请更新白名单\n";
echo "2. 如果AppSecret相关错误，请检查微信公众平台配置\n";
echo "3. 如果网络连通性问题，请检查防火墙和网络设置\n";
echo "4. 如果IP白名单已配置但仍报错，可能需要等待24小时生效\n";
