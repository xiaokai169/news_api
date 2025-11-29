<?php
/**
 * CORS修复验证脚本
 * 测试 /official-api/wechat/articles 路径的CORS配置
 */

header('Content-Type: application/json');

// 记录请求信息用于调试
$debugInfo = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'path' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'none',
    'access_control_request_method' => $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? 'none',
    'access_control_request_headers' => $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? 'none',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'none',
    'all_headers' => getallheaders(),
];

// 写入调试日志
error_log('[CORS TEST] ' . json_encode($debugInfo));

// 处理OPTIONS预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // 设置CORS头
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 3600');
    header('Content-Length: 0');

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'CORS preflight request handled successfully',
        'debug' => $debugInfo
    ]);
    exit;
}

// 处理其他请求
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// 模拟API响应
$response = [
    'success' => true,
    'message' => 'CORS test endpoint for /official-api/wechat/articles',
    'data' => [
        'items' => [
            ['id' => 1, 'title' => '测试文章1', 'content' => '测试内容1'],
            ['id' => 2, 'title' => '测试文章2', 'content' => '测试内容2'],
        ],
        'total' => 2,
        'page' => 1,
        'limit' => 10,
        'pages' => 1
    ],
    'debug' => $debugInfo
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
