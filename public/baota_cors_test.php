<?php
/**
 * 宝塔面板环境 CORS 测试脚本
 * 用于诊断和修复宝塔环境下的跨域问题
 */

header('Content-Type: application/json');

// 记录宝塔环境详细信息
$baota_info = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    'https' => $_SERVER['HTTPS'] ?? 'off',
    'server_port' => $_SERVER['SERVER_PORT'] ?? 'unknown',
    'environment' => [
        'APP_ENV' => $_ENV['APP_ENV'] ?? 'not_set',
        'APP_DEBUG' => $_ENV['APP_DEBUG'] ?? 'not_set',
        'CORS_ALLOW_ORIGIN' => $_ENV['CORS_ALLOW_ORIGIN'] ?? 'not_set',
        'DEFAULT_URI' => $_ENV['DEFAULT_URI'] ?? 'not_set',
    ],
    'all_headers' => getallheaders(),
    'server_vars' => [
        'HTTP_ORIGIN' => $_SERVER['HTTP_ORIGIN'] ?? 'none',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? 'none',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? 'none',
        'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'none',
        'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? 'none',
        'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ],
];

// 写入调试日志
error_log('[BAOTA CORS TEST] ' . json_encode($baota_info, JSON_UNESCAPED_UNICODE));

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    $request_method = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? 'GET';
    $request_headers = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '';

    // 🔧 设置 CORS 头 - 宝塔环境专用
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, ' . $request_headers);
    header('Access-Control-Max-Age: 3600');
    header('Access-Control-Allow-Credentials: false');

    error_log('[BAOTA CORS] OPTIONS request handled - Origin: ' . $origin . ', Method: ' . $request_method);

    http_response_code(200);

    echo json_encode([
        'success' => true,
        'message' => '宝塔环境 CORS OPTIONS 预检请求处理成功',
        'cors_headers_set' => true,
        'origin' => $origin,
        'allowed_methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        'allowed_headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin, ' . $request_headers,
        'baota_info' => $baota_info
    ], JSON_UNESCAPED_UNICODE);

} else {
    // 非 OPTIONS 请求
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';

    // 🔧 设置 CORS 头
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
    header('Access-Control-Allow-Credentials: false');

    echo json_encode([
        'success' => true,
        'message' => '宝塔环境 CORS 测试端点',
        'cors_headers_set' => true,
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'origin' => $origin,
        'note' => '使用 OPTIONS 方法测试预检请求',
        'baota_info' => $baota_info
    ], JSON_UNESCAPED_UNICODE);
}

// 记录响应结束
error_log('[BAOTA CORS TEST] Response sent for method: ' . $_SERVER['REQUEST_METHOD'] . ', Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? 'none'));
