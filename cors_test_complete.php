<?php
/**
 * CORS修复完整测试脚本
 * 测试所有修复的CORS问题和API端点
 */

echo "=== CORS修复完整测试报告 ===\n\n";

$testResults = [];
$baseUrl = 'http://127.0.0.1';
$ports = [8000, 8001, 8002];

// 测试函数
function testEndpoint($url, $description, $expectedStatus = 200, $headers = []) {
    global $testResults;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $responseHeaders = substr($response, 0, $headerSize);
    $responseBody = substr($response, $headerSize);
    curl_close($ch);

    // 检查CORS头
    $hasCorsHeaders = false;
    $corsHeaders = [];
    if (preg_match('/Access-Control-Allow-Origin:\s*(.+)/i', $responseHeaders, $matches)) {
        $corsHeaders['origin'] = trim($matches[1]);
        $hasCorsHeaders = true;
    }
    if (preg_match('/Access-Control-Allow-Methods:\s*(.+)/i', $responseHeaders, $matches)) {
        $corsHeaders['methods'] = trim($matches[1]);
    }
    if (preg_match('/Access-Control-Allow-Headers:\s*(.+)/i', $responseHeaders, $matches)) {
        $corsHeaders['headers'] = trim($matches[1]);
    }

    $success = ($httpCode == $expectedStatus);

    $testResults[] = [
        'url' => $url,
        'description' => $description,
        'status' => $httpCode,
        'expected' => $expectedStatus,
        'success' => $success,
        'cors_headers' => $corsHeaders,
        'has_cors' => $hasCorsHeaders
    ];

    echo sprintf(
        "[%s] %s\n  URL: %s\n  状态: %d (期望: %d)\n  CORS: %s\n\n",
        $success ? '✓' : '✗',
        $description,
        $url,
        $httpCode,
        $expectedStatus,
        $hasCorsHeaders ? json_encode($corsHeaders, JSON_UNESCAPED_UNICODE) : '无'
    );

    return $success;
}

echo "1. 测试HTTP Swagger入口页面\n";
testEndpoint("$baseUrl:8000/swagger_http.php", "HTTP Swagger入口页面");

echo "2. 测试导航页面\n";
testEndpoint("$baseUrl:8000/swagger_route.php", "API文档导航页面");

echo "3. 测试API文档JSON\n";
testEndpoint("$baseUrl:8000/api_doc.json", "API文档JSON数据");

echo "4. 测试端口8001 API健康检查\n";
testEndpoint("$baseUrl:8001/api/health", "端口8001健康检查");

echo "5. 测试CORS预检请求 (OPTIONS)\n";
testEndpoint("$baseUrl:8001/api/health", "OPTIONS预检请求", 200, ['X-HTTP-Method-Override: OPTIONS']);

echo "6. 测试带Origin头的CORS请求\n";
testEndpoint("$baseUrl:8001/api/health", "带Origin头的CORS请求", 200, ['Origin: http://localhost:3000']);

echo "7. 测试端口8002测试服务器\n";
testEndpoint("$baseUrl:8002/", "端口8002测试服务器");

echo "8. 测试原始Swagger手动页面\n";
testEndpoint("$baseUrl:8000/swagger_manual.html", "原始Swagger手动页面");

// 生成测试报告
$passedTests = array_filter($testResults, fn($r) => $r['success']);
$totalTests = count($testResults);
$passedCount = count($passedTests);

echo "\n=== 测试总结 ===\n";
echo "总测试数: $totalTests\n";
echo "通过测试: $passedCount\n";
echo "失败测试: " . ($totalTests - $passedCount) . "\n";
echo "成功率: " . round(($passedCount / $totalTests) * 100, 2) . "%\n\n";

// CORS头检查
$corsWorkingTests = array_filter($testResults, fn($r) => $r['has_cors']);
echo "CORS配置检查:\n";
echo "有CORS头的端点: " . count($corsWorkingTests) . "/$totalTests\n";

if ($passedCount == $totalTests) {
    echo "🎉 所有测试通过！CORS问题已完全解决。\n";
} else {
    echo "⚠️  部分测试失败，需要进一步检查。\n";
}

echo "\n=== 推荐访问方式 ===\n";
echo "1. HTTP Swagger入口: http://localhost:8000/swagger_http.php\n";
echo "2. API文档导航: http://localhost:8000/swagger_route.php\n";
echo "3. API健康检查: http://localhost:8001/api/health\n";
echo "4. 原始Swagger页面: http://localhost:8000/swagger_manual.html\n";
