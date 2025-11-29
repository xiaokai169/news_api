<?php

use App\Kernel;

require_once dirname(__DIR__)."/vendor/autoload_runtime.php";

// 🔧 紧急 CORS 修复 - 在任何输出之前设置
if (isset($_SERVER['HTTP_ORIGIN']) || $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
    header('Access-Control-Max-Age: 3600');
    header('Access-Control-Allow-Credentials: false');

    error_log('[INDEX CORS] Headers set at index.php - Method: ' . $_SERVER['REQUEST_METHOD'] . ', Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? 'none'));
}

// 🔧 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'CORS OPTIONS 预检请求处理成功（index.php 级别）',
        'method' => $_SERVER['REQUEST_METHOD'],
        'headers_set' => [
            'Access-Control-Allow-Origin: *',
            'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin',
            'Access-Control-Max-Age: 3600'
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

return function (array $context) {
    return new Kernel($context["APP_ENV"], (bool) $context["APP_DEBUG"]);
};
