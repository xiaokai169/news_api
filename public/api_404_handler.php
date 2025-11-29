<?php
/**
 * API 404错误处理器
 * 当Nginx配置无法立即修改时，可作为临时解决方案
 */

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
