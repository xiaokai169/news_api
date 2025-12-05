<?php
echo "=== 快速微信API测试 ===\n";

// 直接测试一个简单的API调用
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
    CURLOPT_TIMEOUT => 5,
    CURLOPT_USERAGENT => 'WeChat-Quick-Test/1.0'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "测试微信API连通性:\n";
echo "HTTP状态: $httpCode\n";

if ($error) {
    echo "❌ 网络错误: $error\n";
} elseif ($response) {
    $data = json_decode($response, true);
    if (isset($data['errcode'])) {
        echo "✅ 微信API可访问，返回错误码: " . $data['errcode'] . " - " . ($data['errmsg'] ?? '') . "\n";
        echo "💡 这表明网络连通性正常，问题在于app_id/app_secret配置\n";
    } else {
        echo "✅ 微信API响应正常\n";
    }
} else {
    echo "❌ 无响应\n";
}

echo "\n=== 检查当前IP ===\n";
$ipServices = ['https://api.ipify.org'];
foreach ($ipServices as $service) {
    $response = @file_get_contents($service, false, stream_context_create(['http' => ['timeout' => 3]]));
    if ($response) {
        echo "当前出口IP: " . trim($response) . "\n";
        echo "用户提供的正确IP: 1.92.157.200\n";
        if (trim($response) === '1.92.157.200') {
            echo "✅ IP匹配\n";
        } else {
            echo "❌ IP不匹配，需要在微信公众平台白名单中添加: " . trim($response) . "\n";
        }
        break;
    }
}

echo "\n=== 诊断结论 ===\n";
echo "基于代码分析，WechatApiService中的参数使用是正确的:\n";
echo "- appid 参数名正确\n";
echo "- secret 参数名正确\n";
echo "\n最可能的问题源:\n";
echo "1. IP白名单配置问题\n";
echo "2. AppSecret配置错误或已过期\n";
echo "3. 公众号权限问题\n";
