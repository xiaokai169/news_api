<?php
echo "=== 微信API app_id和app_secret位置诊断 ===\n\n";

// 1. 检查微信API文档要求的参数格式
echo "1. 微信API官方文档要求:\n";
echo "   GET https://api.weixin.qq.com/cgi-bin/token\n";
echo "   参数: grant_type=client_credential&appid=APPID&secret=APPSECRET\n";
echo "   注意: 参数名是 'appid' (小写) 和 'secret' (小写)\n\n";

// 2. 检查当前代码中的参数使用
echo "2. 检查WechatApiService中的参数使用:\n";
$wechatApiServiceFile = __DIR__ . '/../src/Service/WechatApiService.php';
if (file_exists($wechatApiServiceFile)) {
    $content = file_get_contents($wechatApiServiceFile);

    // 查找getAccessToken方法中的参数
    if (preg_match('/public function getAccessToken.*?{(.*?)}/s', $content, $matches)) {
        $methodContent = $matches[1];

        echo "   在getAccessToken方法中找到的参数:\n";

        // 查找query参数
        if (preg_match('/\'query\'\s*=>\s*\[(.*?)\]/s', $methodContent, $queryMatches)) {
            $queryContent = $queryMatches[1];

            // 查找appid参数
            if (preg_match("/'appid'\s*=>\s*\$account->getAppId\(\)/", $queryContent)) {
                echo "   ✅ appid参数: 'appid' => \$account->getAppId() (正确)\n";
            } else {
                echo "   ❌ 未找到正确的appid参数\n";
            }

            // 查找secret参数
            if (preg_match("/'secret'\s*=>\s*\$account->getAppSecret\(\)/", $queryContent)) {
                echo "   ✅ secret参数: 'secret' => \$account->getAppSecret() (正确)\n";
            } else {
                echo "   ❌ 未找到正确的secret参数\n";
            }
        }
    }
} else {
    echo "   ❌ WechatApiService.php文件不存在\n";
}
echo "\n";

// 3. 检查数据库中的实际数据
echo "3. 检查数据库中的实际配置:\n";
try {
    require_once __DIR__ . '/../vendor/autoload.php';
    $kernel = new App\Kernel('dev', true);
    $kernel->boot();
    $entityManager = $kernel->getContainer()->get('doctrine.orm.entity_manager');

    $connection = $entityManager->getConnection();
    $sql = "SELECT id, name, app_id, app_secret, is_active FROM wechat_public_account WHERE is_active = 1 LIMIT 3";
    $stmt = $connection->prepare($sql);
    $result = $stmt->executeQuery();
    $accounts = $result->fetchAllAssociative();

    if (!empty($accounts)) {
        foreach ($accounts as $account) {
            echo "   账户: " . $account['name'] . " (ID: " . $account['id'] . ")\n";
            echo "   app_id: " . substr($account['app_id'], 0, 8) . "...\n";
            echo "   app_secret: " . substr($account['app_secret'], 0, 8) . "...\n";

            // 验证app_id格式
            if (strlen($account['app_id']) >= 18) {
                echo "   ✅ app_id长度符合要求\n";
            } else {
                echo "   ❌ app_id长度可能不正确\n";
            }

            // 验证app_secret格式
            if (strlen($account['app_secret']) >= 32) {
                echo "   ✅ app_secret长度符合要求\n";
            } else {
                echo "   ❌ app_secret长度可能不正确\n";
            }
            echo "\n";
        }
    } else {
        echo "   ❌ 数据库中没有活跃的公众号账户\n";
    }

    $kernel->shutdown();
} catch (Exception $e) {
    echo "   ❌ 无法读取数据库配置: " . $e->getMessage() . "\n";
}

// 4. 实际测试不同的参数组合
echo "4. 测试不同的参数组合:\n";
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
        echo "   测试账户: " . $account['name'] . "\n\n";

        $testCases = [
            [
                'name' => '正确格式 (appid + secret)',
                'params' => [
                    'grant_type' => 'client_credential',
                    'appid' => $account['app_id'],
                    'secret' => $account['app_secret']
                ]
            ],
            [
                'name' => '错误格式1 (app_id + secret)',
                'params' => [
                    'grant_type' => 'client_credential',
                    'app_id' => $account['app_id'],
                    'secret' => $account['app_secret']
                ]
            ],
            [
                'name' => '错误格式2 (appid + app_secret)',
                'params' => [
                    'grant_type' => 'client_credential',
                    'appid' => $account['app_id'],
                    'app_secret' => $account['app_secret']
                ]
            ]
        ];

        foreach ($testCases as $testCase) {
            echo "   测试: " . $testCase['name'] . "\n";

            $url = 'https://api.weixin.qq.com/cgi-bin/token';
            $fullUrl = $url . '?' . http_build_query($testCase['params']);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $fullUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_USERAGENT => 'WeChat-API-Debug/1.0'
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
                    echo "     ✅ 成功获取access_token\n";
                } else {
                    echo "     ❌ 获取失败\n";
                    echo "     错误码: " . ($data['errcode'] ?? '未知') . "\n";
                    echo "     错误信息: " . ($data['errmsg'] ?? '未知') . "\n";
                }
            }
            echo "\n";
        }
    }

    $kernel->shutdown();
} catch (Exception $e) {
    echo "   ❌ 测试失败: " . $e->getMessage() . "\n";
}

// 5. 总结和建议
echo "5. 总结和建议:\n";
echo "   根据微信官方文档，正确的参数名是:\n";
echo "   - appid (小写)\n";
echo "   - secret (小写)\n";
echo "   不是 app_id 或 app_secret\n\n";

echo "   如果WechatApiService中使用了错误的参数名，需要修改为:\n";
echo "   'appid' => \$account->getAppId()\n";
echo "   'secret' => \$account->getAppSecret()\n\n";

echo "=== 诊断完成 ===\n";
