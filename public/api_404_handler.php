<?php
/**
 * API 404错误处理器
 * 当Nginx配置无法立即修改时，可作为临时解决方案
 */

// 处理OPTIONS预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, X-Custom-Header, X-Request-Id, x-request-id, X-Request-ID');
    header('Access-Control-Max-Age: 3600');
    header('Access-Control-Allow-Credentials: false');
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');
http_response_code(404);

// 获取请求信息
$requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 构建JSON响应，与ApiResponse类保持一致的格式
$response = [
    'status' => '404',
    'message' => 'API endpoint not found',
    'timestamp' => time(),
    'path' => $requestUri,
    'data' => [
        'requested_path' => $requestUri,
        'method' => $requestMethod,
        'available_api_prefixes' => [
            '/api' => 'General API endpoints',
            '/official-api' => 'Official application APIs',
            '/public-api' => 'Public access APIs'
        ]
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
