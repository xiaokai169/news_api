<?php
/**
 * 简单的CORS测试 - 直接测试OPTIONS请求
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== CORS X-Request-Id 直接测试 ===\n\n";

// 测试1: 直接curl测试API路径
echo "1. 测试 /api/test OPTIONS请求:\n";
$command = 'curl -s -I -X OPTIONS \
    -H "Origin: https://example.com" \
    -H "Access-Control-Request-Method: POST" \
    -H "Access-Control-Request-Headers: Content-Type, X-Request-Id" \
    "http://localhost/api/test" 2>/dev/null';

$output = shell_exec($command);
echo "命令: $command\n";
echo "响应:\n" . $output . "\n\n";

// 检查响应头
if (strpos($output, 'X-Request-Id') !== false) {
    echo "✅ 找到 X-Request-Id 头部\n";
} else {
    echo "❌ 未找到 X-Request-Id 头部\n";
}

echo "\n" . str_repeat("=", 50) . "\n\n";

// 测试2: 测试 /official-api/news
echo "2. 测试 /official-api/news OPTIONS请求:\n";
$command2 = 'curl -s -I -X OPTIONS \
    -H "Origin: http://localhost:3000" \
    -H "Access-Control-Request-Method: GET" \
    -H "Access-Control-Request-Headers: X-Request-Id, Accept" \
    "http://localhost/official-api/news" 2>/dev/null';

$output2 = shell_exec($command2);
echo "命令: $command2\n";
echo "响应:\n" . $output2 . "\n\n";

if (strpos($output2, 'X-Request-Id') !== false) {
    echo "✅ 找到 X-Request-Id 头部\n";
} else {
    echo "❌ 未找到 X-Request-Id 头部\n";
}

echo "\n" . str_repeat("=", 50) . "\n\n";

// 测试3: 检查实际的配置文件
echo "3. 验证配置文件内容:\n";

// 检查nelmio_cors.yaml
$corsConfig = file_get_contents(__DIR__ . '/../config/packages/nelmio_cors.yaml');
if (strpos($corsConfig, 'X-Request-Id') !== false) {
    echo "✅ nelmio_cors.yaml 包含 X-Request-Id\n";
} else {
    echo "❌ nelmio_cors.yaml 不包含 X-Request-Id\n";
}

// 检查ProductionCorsSubscriber
$prodSub = file_get_contents(__DIR__ . '/../src/EventSubscriber/ProductionCorsSubscriber.php');
if (strpos($prodSub, 'X-Request-Id') !== false) {
    echo "✅ ProductionCorsSubscriber 包含 X-Request-Id\n";
} else {
    echo "❌ ProductionCorsSubscriber 不包含 X-Request-Id\n";
}

// 检查ForceCorsSubscriber
$forceSub = file_get_contents(__DIR__ . '/../src/EventSubscriber/ForceCorsSubscriber.php');
if (strpos($forceSub, 'X-Request-Id') !== false) {
    echo "✅ ForceCorsSubscriber 包含 X-Request-Id\n";
} else {
    echo "❌ ForceCorsSubscriber 不包含 X-Request-Id\n";
}

echo "\n" . str_repeat("=", 50) . "\n\n";

// 测试4: 检查错误日志
echo "4. 检查最近的错误日志:\n";
$logCommand = 'tail -20 /var/log/apache2/error.log 2>/dev/null | grep -i cors';
$logOutput = shell_exec($logCommand);
if (!empty($logOutput)) {
    echo "找到CORS相关日志:\n" . $logOutput . "\n";
} else {
    echo "未找到CORS相关日志\n";
}

echo "\n=== 测试完成 ===\n";
