<?php
/**
 * CORS 调试测试脚本
 * 用于验证CORS配置和URL格式问题
 */

// 设置响应头
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// 处理OPTIONS预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$debug_info = [
    'timestamp' => date('Y-m-d H:i:s'),
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'http_host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
    'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'server_port' => $_SERVER['SERVER_PORT'] ?? 'unknown',
    'protocol' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http',
    'full_url' => '',
    'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'not set',
    'referer' => $_SERVER['HTTP_REFERER'] ?? 'not set',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'not set',
    'cors_headers' => [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With'
    ]
];

// 构建完整URL
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$debug_info['full_url'] = $scheme . '://' . $host . $uri;

// 测试不同的URL格式
$test_urls = [
    'relative_path' => '/public-api/articles?type=news',
    'protocol_relative' => '//' . $host . '/public-api/articles?type=news',
    'http_full' => 'http://' . $host . '/public-api/articles?type=news',
    'https_full' => 'https://' . $host . '/public-api/articles?type=news',
];

$debug_info['test_urls'] = $test_urls;

// 检查当前环境
$debug_info['environment'] = [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'unknown',
];

echo json_encode([
    'success' => true,
    'message' => 'CORS 调试信息',
    'debug_info' => $debug_info
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
