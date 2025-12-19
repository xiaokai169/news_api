<?php
require_once 'vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;

echo "=== News API 验证测试 ===\n\n";

// 测试配置
$baseUrl = 'https://127.0.0.1:8000';
$testUrl = '/official-api/news?current=1&pageSize=10&name=&status=&categoryId=&isRecommend=&page=1&size=10';

$client = HttpClient::create();

// 测试 1: OPTIONS 请求（原始报错的请求）
echo "1. 测试 OPTIONS 请求（原始报错的端点）\n";
echo "URL: {$baseUrl}{$testUrl}\n";
echo "方法: OPTIONS\n";

try {
    $response = $client->request('OPTIONS', $baseUrl . $testUrl, [
        'verify_peer' => false,
        'verify_host' => false,
        'timeout' => 10
    ]);

    $statusCode = $response->getStatusCode();
    $headers = $response->getHeaders();
    $content = $response->getContent(false);

    echo "状态码: {$statusCode}\n";
    echo "响应头:\n";
    foreach ($headers as $name => $values) {
        echo "  {$name}: " . implode(', ', $values) . "\n";
    }
    echo "响应内容: " . ($content ?: '(空内容)') . "\n";

    if ($statusCode === 500) {
        echo "❌ 仍然出现 500 错误！\n";
    } elseif ($statusCode === 200 || $statusCode === 204) {
        echo "✅ OPTIONS 请求成功！\n";
    } else {
        echo "⚠️  状态码: {$statusCode}\n";
    }

} catch (Exception $e) {
    echo "❌ 请求异常: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("-", 80) . "\n\n";

// 测试 2: GET 请求
echo "2. 测试 GET 请求\n";
echo "URL: {$baseUrl}{$testUrl}\n";
echo "方法: GET\n";

try {
    $response = $client->request('GET', $baseUrl . $testUrl, [
        'verify_peer' => false,
        'verify_host' => false,
        'timeout' => 10
    ]);

    $statusCode = $response->getStatusCode();
    $headers = $response->getHeaders();
    $content = $response->getContent(false);

    echo "状态码: {$statusCode}\n";
    echo "Content-Type: " . ($headers['content-type'][0] ?? '未知') . "\n";

    if ($statusCode === 200) {
        echo "✅ GET 请求成功！\n";

        // 尝试解析 JSON 响应
        $data = json_decode($content, true);
        if ($data !== null) {
            echo "✅ JSON 响应格式正确\n";
            echo "响应数据结构:\n";
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "⚠️  JSON 解析失败，原始内容: " . substr($content, 0, 500) . "...\n";
        }
    } elseif ($statusCode === 500) {
        echo "❌ GET 请求也出现 500 错误！\n";
        echo "错误内容: " . substr($content, 0, 500) . "...\n";
    } else {
        echo "⚠️  状态码: {$statusCode}\n";
        echo "响应内容: " . substr($content, 0, 500) . "...\n";
    }

} catch (Exception $e) {
    echo "❌ 请求异常: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("-", 80) . "\n\n";

// 测试 3: 简化的 news 端点
echo "3. 测试简化的 news 端点\n";
echo "URL: {$baseUrl}/official-api/news\n";
echo "方法: GET\n";

try {
    $response = $client->request('GET', $baseUrl . '/official-api/news', [
        'verify_peer' => false,
        'verify_host' => false,
        'timeout' => 10
    ]);

    $statusCode = $response->getStatusCode();
    $content = $response->getContent(false);

    echo "状态码: {$statusCode}\n";

    if ($statusCode === 200) {
        echo "✅ 简化端点请求成功！\n";
    } elseif ($statusCode === 500) {
        echo "❌ 简化端点也出现 500 错误！\n";
        echo "错误内容: " . substr($content, 0, 500) . "...\n";
    } else {
        echo "⚠️  状态码: {$statusCode}\n";
    }

} catch (Exception $e) {
    echo "❌ 请求异常: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("-", 80) . "\n\n";

// 测试 4: 检查 API 平台文档端点
echo "4. 测试 API 平台文档端点\n";
echo "URL: {$baseUrl}/api\n";
echo "方法: GET\n";

try {
    $response = $client->request('GET', $baseUrl . '/api', [
        'verify_peer' => false,
        'verify_host' => false,
        'timeout' => 10
    ]);

    $statusCode = $response->getStatusCode();

    echo "状态码: {$statusCode}\n";

    if ($statusCode === 200) {
        echo "✅ API 平台文档可访问！\n";
    } else {
        echo "⚠️  API 平台文档状态码: {$statusCode}\n";
    }

} catch (Exception $e) {
    echo "❌ API 平台文档访问异常: " . $e->getMessage() . "\n";
}

echo "\n=== 测试完成 ===\n";
