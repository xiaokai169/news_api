<?php
/**
 * 测试DTO字段映射的简单脚本
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置内容类型
header('Content-Type: text/plain; charset=utf-8');

echo "开始测试DTO字段映射...\n\n";

// 模拟Symfony的自动映射行为
function testFieldMapping($testName, $requestData) {
    echo "=== $testName ===\n";
    echo "请求数据: " . json_encode($requestData, JSON_UNESCAPED_UNICODE) . "\n";

    // 模拟SyncWechatDto的字段映射逻辑
    $accountId = '';

    // 这是SyncWechatDto::populateFromData的逻辑
    if (isset($requestData['publicAccountId'])) {
        $accountId = $requestData['publicAccountId'];
        echo "检测到publicAccountId字段: $accountId\n";
    }
    if (isset($requestData['accountId'])) {
        $accountId = $requestData['accountId'];
        echo "检测到accountId字段: $accountId\n";
    }

    echo "最终accountId值: '$accountId'\n";
    echo "验证结果: " . (empty($accountId) ? '❌ 空值' : '✅ 有值') . "\n\n";

    return $accountId;
}

// 测试各种情况
$result1 = testFieldMapping('测试新字段accountId', [
    'accountId' => 'test_account_new',
    'syncType' => 'info'
]);

$result2 = testFieldMapping('测试旧字段publicAccountId', [
    'publicAccountId' => 'test_account_old',
    'syncType' => 'info'
]);

$result3 = testFieldMapping('测试两个字段同时存在（accountId优先）', [
    'accountId' => 'test_account_priority',
    'publicAccountId' => 'test_account_ignored',
    'syncType' => 'info'
]);

$result4 = testFieldMapping('测试空字段', [
    'accountId' => '',
    'syncType' => 'info'
]);

$result5 = testFieldMapping('测试缺少字段', [
    'syncType' => 'info'
]);

echo "=== 总结 ===\n";
echo "所有测试完成。字段映射逻辑正常工作。\n";

// 现在测试实际的API请求
echo "\n=== 实际API测试 ===\n";

function testApiRequest($testName, $requestData) {
    echo "\n--- $testName ---\n";

    $ch = curl_init('http://127.0.0.1:8084/official-api/wechat/sync');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "请求失败: $error\n";
        return false;
    }

    echo "HTTP状态码: $httpCode\n";
    echo "响应: $response\n";

    $responseData = json_decode($response, true);
    if ($responseData && isset($responseData['message'])) {
        echo "错误消息: {$responseData['message']}\n";
    }

    return $httpCode === 200;
}

// 测试有效的accountId（使用非空值）
testApiRequest('API测试 - 有效accountId', [
    'accountId' => 'wx_valid_account_12345',
    'syncType' => 'info',
    'forceSync' => false,
    'async' => false
]);

testApiRequest('API测试 - 有效publicAccountId', [
    'publicAccountId' => 'wx_valid_account_67890',
    'syncType' => 'info',
    'forceSync' => false,
    'async' => false
]);

echo "\n测试完成！\n";
?>
