<?php
/**
 * CORS调试测试脚本
 * 用于验证CORS配置和预检请求处理
 */

header('Content-Type: application/json');

// 记录调试信息
$debug = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'path' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'none',
    'headers' => getallheaders(),
    'request_method' => $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? 'none',
    'request_headers' => $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? 'none',
];

// 处理OPTIONS预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $debug['is_preflight'] = true;

    // 设置CORS头
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 3600');

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'CORS preflight request handled successfully',
        'debug' => $debug
    ]);
    exit;
}

$debug['is_preflight'] = false;

// 处理其他请求
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

echo json_encode([
    'success' => true,
    'message' => 'CORS test endpoint',
    'debug' => $debug
]);
