<?php
/**
 * OPTIONS 预检请求测试脚本
 * 用于诊断 CORS 预检请求处理问题
 */

// 记录所有输入
$request_data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'query_string' => $_SERVER['QUERY_STRING'] ?? '',
    'headers' => getallheaders(),
    'server_vars' => [
        'HTTP_ORIGIN' => $_SERVER['HTTP_ORIGIN'] ?? 'none',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? 'none',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? 'none',
        'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'none',
        'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? 'none',
        'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'unknown',
        'SERVER_PORT' => $_SERVER['SERVER_PORT'] ?? 'unknown',
        'HTTPS' => $_SERVER['HTTPS'] ?? 'off',
        'REQUEST_SCHEME' => $_SERVER['REQUEST_SCHEME'] ?? 'http'
    ],
    'environment' => [
        'APP_ENV' => $_ENV['APP_ENV'] ?? 'not_set',
        'APP_DEBUG' => $_ENV['APP_DEBUG'] ?? 'not_set',
        'CORS_ALLOW_ORIGIN' => $_ENV['CORS_ALLOW_ORIGIN'] ?? 'not_set'
    ]
];

// 写入调试日志
error_log('[CORS PREFLIGHT TEST] ' . json_encode($request_data, JSON_UNESCAPED_UNICODE));

// 设置响应头
header('Content-Type: application/json');

// 处理 OPTIONS 请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $is_preflight = !empty($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']);

    if ($is_preflight) {
        // 这是一个预检请求
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        $request_method = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? 'GET';
        $request_headers = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '';

        // 设置CORS头
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, ' . $request_headers);
        header('Access-Control-Max-Age: 3600');
        header('Access-Control-Allow-Credentials: false');

        // 记录预检请求处理
        error_log('[CORS PREFLIGHT] Preflight request handled - Origin: ' . $origin .
                 ', Method: ' . $request_method .
                 ', Headers: ' . $request_headers);

        http_response_code(200);

        echo json_encode([
            'success' => true,
            'message' => 'CORS preflight request handled successfully',
            'preflight' => true,
            'origin' => $origin,
            'allowed_methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'allowed_headers' => 'Content-Type, Authorization, X-Requested-With, ' . $request_headers,
            'request_data' => $request_data
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    } else {
        // 这是一个普通的OPTIONS请求（非预检）
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

        http_response_code(200);

        echo json_encode([
            'success' => true,
            'message' => 'OPTIONS request handled (non-preflight)',
            'preflight' => false,
            'request_data' => $request_data
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

} else {
    // 非OPTIONS请求
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

    echo json_encode([
        'success' => true,
        'message' => 'CORS test endpoint (non-OPTIONS request)',
        'method' => $_SERVER['REQUEST_METHOD'],
        'request_data' => $request_data,
        'note' => 'Use OPTIONS method to test preflight requests'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// 记录响应结束
error_log('[CORS PREFLIGHT TEST] Response sent for method: ' . $_SERVER['REQUEST_METHOD']);
