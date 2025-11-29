<?php
/**
 * 直接 CORS 测试脚本
 * 绕过 Symfony 框架，直接设置 CORS 头
 */

// 🔧 强制设置 CORS 头 - 在任何输出之前
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
header('Access-Control-Max-Age: 3600');
header('Access-Control-Allow-Credentials: false');

// 记录调试信息
error_log('[DIRECT CORS] Headers set for method: ' . $_SERVER['REQUEST_METHOD'] . ', URI: ' . $_SERVER['REQUEST_URI']);

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'CORS OPTIONS 预检请求处理成功（直接脚本）',
        'method' => $_SERVER['REQUEST_METHOD'],
        'headers_set' => [
            'Access-Control-Allow-Origin: *',
            'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin',
            'Access-Control-Max-Age: 3600'
        ],
        'server_info' => [
            'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
            'REQUEST_URI' => $_SERVER['REQUEST_URI'],
            'HTTP_ORIGIN' => $_SERVER['HTTP_ORIGIN'] ?? 'none',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? 'none',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? 'none',
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 设置响应头
header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => '直接 CORS 测试脚本',
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers_set' => [
        'Access-Control-Allow-Origin: *',
        'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS',
        'Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin',
        'Access-Control-Max-Age: 3600',
        'Content-Type: application/json'
    ],
    'server_info' => [
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
        'REQUEST_URI' => $_SERVER['REQUEST_URI'],
        'HTTP_ORIGIN' => $_SERVER['HTTP_ORIGIN'] ?? 'none',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? 'none',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? 'none',
        'PHP_VERSION' => PHP_VERSION,
        'SERVER_SOFTWARE' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'
    ],
    'note' => '这是一个直接 PHP 脚本，绕过 Symfony 框架直接设置 CORS 头'
], JSON_UNESCAPED_UNICODE);
