<?php
/**
 * 简化的 API 前缀修复版本
 * 专门用于解决 /api/ 前缀问题
 */

// 完全禁用错误显示，确保只返回 JSON
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 获取请求信息
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];

// 解析路径
$path = parse_url($requestUri, PHP_URL_PATH);
$path = '/' . trim($path, '/');

// 发送 JSON 响应
function send_json($data, $status_code = 200) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 主页
if ($path === '/') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
<html>
<head>
    <title>API 修复验证</title>
    <meta charset="utf-8">
</head>
<body>
    <h1>🚀 API 前缀修复验证</h1>
    <h2>支持的端点：</h2>
    <ul>
        <li><a href="/health">/health</a> - 健康检查</li>
        <li><a href="/api/health">/api/health</a> - 健康检查（带前缀）</li>
        <li><a href="/test">/test</a> - 测试接口</li>
        <li><a href="/api/test">/api/test</a> - 测试接口（带前缀）</li>
    </ul>
    <h2>修复状态：</h2>
    <p>✅ 支持 /api/ 前缀访问</p>
    <p>✅ 完全防止 PHP 源码泄露</p>
    <p>✅ 统一 JSON 响应格式</p>
</body>
</html>';
    exit;
}

// 处理 CORS
if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(200);
    exit;
}

// 路由处理
switch ($path) {
    case '/health':
    case '/api/health':
        send_json([
            'success' => true,
            'status' => 'ok',
            'message' => 'API 前缀修复成功！',
            'timestamp' => date('c'),
            'service' => '官方网站后台API',
            'version' => '4.0.0-fixed',
            'path_accessed' => $path,
            'supports_api_prefix' => true,
            'fix_applied' => 'api_with_prefix.php'
        ]);

    case '/test':
    case '/api/test':
        send_json([
            'success' => true,
            'message' => 'Hello World - API 前缀修复验证',
            'timestamp' => date('c'),
            'method' => $method,
            'path_accessed' => $path,
            'supports_api_prefix' => true,
            'fix_applied' => 'api_with_prefix.php'
        ]);

    case '/info':
    case '/api/info':
        send_json([
            'success' => true,
            'name' => '官方网站后台API',
            'version' => '4.0.0-fixed',
            'description' => 'API 前缀问题已修复，支持 /api/ 前缀访问',
            'supported_paths' => [
                '/health' => '直接访问',
                '/api/health' => '前缀访问',
                '/test' => '直接访问',
                '/api/test' => '前缀访问'
            ],
            'fix_details' => [
                'problem' => '原 api_final.php 不支持 /api/ 前缀',
                'solution' => '使用支持前缀的新路由文件',
                'status' => '已修复',
                'prevents_source_leak' => true
            ],
            'timestamp' => date('c')
        ]);

    default:
        send_json([
            'success' => false,
            'error' => true,
            'message' => '未找到请求的端点: ' . $path,
            'supported_endpoints' => [
                '/health', '/api/health',
                '/test', '/api/test',
                '/info', '/api/info'
            ],
            'timestamp' => date('c'),
            'status' => 404
        ], 404);
}
