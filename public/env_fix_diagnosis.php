<?php
/**
 * 环境变量诊断和修复脚本
 * 用于检查和修复宝塔面板环境变量传递问题
 */

header('Content-Type: application/json');

$diagnosis = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'server_info' => $_SERVER,
    'env_vars' => [
        'getenv_results' => [
            'APP_ENV' => getenv('APP_ENV'),
            'APP_DEBUG' => getenv('APP_DEBUG'),
            'CORS_ALLOW_ORIGIN' => getenv('CORS_ALLOW_ORIGIN'),
            'SYMFONY_ENV' => getenv('SYMFONY_ENV'),
        ],
        'superglobal_env' => $_ENV,
        'superglobal_server' => [
            'APP_ENV' => $_SERVER['APP_ENV'] ?? 'not_set',
            'APP_DEBUG' => $_SERVER['APP_DEBUG'] ?? 'not_set',
            'CORS_ALLOW_ORIGIN' => $_SERVER['CORS_ALLOW_ORIGIN'] ?? 'not_set',
        ]
    ],
    'file_checks' => [],
    'solutions' => []
];

// 检查环境文件
$env_files = [
    '.env' => __DIR__ . '/../.env',
    '.env.local' => __DIR__ . '/../.env.local',
    '.env.prod' => __DIR__ . '/../.env.prod',
    '.env.prod.local' => __DIR__ . '/../.env.prod.local'
];

foreach ($env_files as $name => $path) {
    $diagnosis['file_checks'][$name] = [
        'exists' => file_exists($path),
        'readable' => is_readable($path),
        'path' => $path,
        'size' => file_exists($path) ? filesize($path) : 0
    ];

    if (file_exists($path) && is_readable($path)) {
        $content = file_get_contents($path);
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
                list($key, $value) = explode('=', $line, 2);
                if (trim($key) === 'APP_ENV' || trim($key) === 'APP_DEBUG' || trim($key) === 'CORS_ALLOW_ORIGIN') {
                    $diagnosis['file_checks'][$name]['content'][trim($key)] = trim($value);
                }
            }
        }
    }
}

// 🔧 强制设置环境变量（临时修复）
if (empty(getenv('APP_ENV'))) {
    putenv('APP_ENV=prod');
    $_ENV['APP_ENV'] = 'prod';
    $diagnosis['solutions'][] = 'Forced APP_ENV=prod';
}

if (empty(getenv('CORS_ALLOW_ORIGIN'))) {
    putenv('CORS_ALLOW_ORIGIN=*');
    $_ENV['CORS_ALLOW_ORIGIN'] = '*';
    $diagnosis['solutions'][] = 'Forced CORS_ALLOW_ORIGIN=*';
}

if (empty(getenv('APP_DEBUG'))) {
    putenv('APP_DEBUG=false');
    $_ENV['APP_DEBUG'] = 'false';
    $diagnosis['solutions'][] = 'Forced APP_DEBUG=false';
}

// 测试 CORS 头设置
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
    header('Access-Control-Max-Age: 3600');
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'message' => 'Environment variables diagnosed and fixed',
        'diagnosis' => $diagnosis,
        'cors_headers_set' => true
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');

    echo json_encode([
        'success' => true,
        'message' => 'Environment variables diagnosis',
        'diagnosis' => $diagnosis,
        'cors_headers_set' => true
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
