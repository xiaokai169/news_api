<?php
/**
 * 测试微信同步API修复
 * 验证手动解析请求体是否解决了400错误
 */

// 设置内容类型
header('Content-Type: application/json');

// 测试数据
$testData = [
    'accountId' => 'test_account_123',
    'syncType' => 'all',
    'forceSync' => false,
    'syncScope' => 'recent',
    'articleLimit' => 10,
    'async' => true,
    'priority' => 5
];

// API端点
$apiUrl = 'http://127.0.0.1:8084/official-api/wechat/sync';

// 发送POST请求
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

echo "=== 微信同步API修复测试 ===\n";
echo "测试数据: " . json_encode($testData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
echo "API端点: $apiUrl\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

if ($error) {
    echo "❌ 请求失败: $error\n";
    exit(1);
}

echo "HTTP状态码: $httpCode\n";
echo "响应内容:\n";

if ($httpCode === 200) {
    echo "✅ 请求成功!\n";
} else {
    echo "❌ 请求失败\n";
}

echo json_encode(json_decode($response), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// 验证是否修复了400错误
if ($httpCode === 400) {
    echo "\n❌ 仍然存在400错误，修复失败\n";
} elseif ($httpCode === 200) {
    echo "\n✅ 400错误已修复，API正常响应\n";
} else {
    echo "\n⚠️  其他HTTP状态码: $httpCode\n";
}
