<?php
/**
 * 404 JSON响应修复验证脚本
 * 用于测试API 404错误是否正确返回JSON格式
 */

echo "=== API 404 JSON响应修复验证 ===\n\n";

$testUrls = [
    'http://localhost/public-api/articles?type=news',
    'http://localhost/official-api/article-read/statistics',
    'http://localhost/api/nonexistent-endpoint',
    'http://localhost/public-api/news/999999'
];

foreach ($testUrls as $url) {
    echo "测试URL: $url\n";
    echo str_repeat('-', 50) . "\n";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    // 分离响应头和响应体
    $headerSize = strpos($response, "\r\n\r\n");
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize + 4);

    echo "HTTP状态码: $httpCode\n";
    echo "Content-Type: $contentType\n";
    echo "响应头:\n" . str_replace("\n", "\n  ", $headers) . "\n";
    echo "响应体:\n" . $body . "\n\n";

    // 验证是否为JSON格式
    $jsonData = json_decode($body, true);
    if ($jsonData !== null) {
        echo "✅ 响应为有效的JSON格式\n";
        if (isset($jsonData['success']) && $jsonData['success'] === false) {
            echo "✅ 包含正确的错误响应结构\n";
        }
    } else {
        echo "❌ 响应不是有效的JSON格式\n";
    }

    echo "\n" . str_repeat("=", 60) . "\n\n";
}

echo "=== 验证完成 ===\n";
echo "如果看到 ✅ 标记，说明修复成功\n";
echo "如果看到 ❌ 标记，说明需要进一步检查配置\n";
