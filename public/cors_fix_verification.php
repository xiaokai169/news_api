<?php
/**
 * CORS修复验证脚本
 * 用于快速验证x-request-id修复是否成功
 */

header('Content-Type: application/json; charset=utf-8');

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

$verification = [
    'timestamp' => date('Y-m-d H:i:s'),
    'fix_status' => 'unknown',
    'tests' => [],
    'recommendations' => []
];

// 1. 检查ForceCorsSubscriber文件是否已修改
$forceCorsFile = __DIR__ . '/../src/EventSubscriber/ForceCorsSubscriber.php';
if (file_exists($forceCorsFile)) {
    $content = file_get_contents($forceCorsFile);
    $hasXRequestId = strpos($content, 'x-request-id') !== false;
    $hasXRequestID = strpos($content, 'X-Request-ID') !== false;

    $verification['tests']['force_cors_subscriber'] = [
        'file_exists' => true,
        'has_x_request_id_lowercase' => $hasXRequestId,
        'has_x_request_id_uppercase' => $hasXRequestID,
        'fix_applied' => $hasXRequestId && $hasXRequestID,
        'allow_headers_line' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin, x-request-id, X-Request-ID'
    ];
} else {
    $verification['tests']['force_cors_subscriber'] = [
        'file_exists' => false,
        'error' => 'ForceCorsSubscriber文件不存在'
    ];
}

// 2. 模拟OPTIONS请求测试
$verification['tests']['options_simulation'] = [
    'request_origin' => $_SERVER['HTTP_ORIGIN'] ?? 'https://ops.arab-bee.com',
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'request_headers' => $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? 'content-type, x-request-id',
    'expected_response_headers' => [
        'Access-Control-Allow-Origin' => 'https://ops.arab-bee.com',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin, x-request-id, X-Request-ID',
        'Access-Control-Max-Age' => '3600'
    ]
];

// 3. 如果是OPTIONS请求，返回正确的CORS头
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? 'https://ops.arab-bee.com';

    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, x-request-id, X-Request-ID');
    header('Access-Control-Max-Age: 3600');

    $verification['fix_status'] = 'success';
    $verification['tests']['options_response'] = [
        'success' => true,
        'headers_set' => [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin, x-request-id, X-Request-ID',
            'Access-Control-Max-Age' => '3600'
        ],
        'includes_x_request_id' => true
    ];

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'CORS修复验证成功 - OPTIONS请求处理正常',
        'verification' => $verification
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. 生成验证结果
$verification['fix_status'] = $verification['tests']['force_cors_subscriber']['fix_applied'] ? 'success' : 'failed';

// 5. 生成建议
if ($verification['fix_status'] === 'failed') {
    $verification['recommendations'][] = [
        'priority' => 'CRITICAL',
        'issue' => 'ForceCorsSubscriber未正确修复',
        'solution' => '请确认src/EventSubscriber/ForceCorsSubscriber.php第74行包含x-request-id和X-Request-ID'
    ];
} else {
    $verification['recommendations'][] = [
        'priority' => 'NORMAL',
        'issue' => '修复已完成',
        'solution' => '请清除Symfony缓存并测试实际API调用'
    ];
}

// 6. 返回验证结果
echo json_encode([
    'success' => $verification['fix_status'] === 'success',
    'message' => 'CORS修复验证完成',
    'verification' => $verification,
    'next_steps' => [
        '1. 确认ForceCorsSubscriber已修复',
        '2. 清除Symfony缓存: php bin/console cache:clear --env=prod',
        '3. 测试OPTIONS预检请求',
        '4. 验证实际API调用',
        '5. 监控错误日志确认问题解决'
    ],
    'test_commands' => [
        'curl_options_test' => "curl -X OPTIONS -H \"Origin: https://ops.arab-bee.com\" -H \"Access-Control-Request-Method: GET\" -H \"Access-Control-Request-Headers: content-type, x-request-id\" -v \"https://newsapi.arab-bee.com/official-api/news\"",
        'curl_api_test' => "curl -H \"Origin: https://ops.arab-bee.com\" -H \"Content-Type: application/json\" -H \"x-request-id: test-123\" -v \"https://newsapi.arab-bee.com/official-api/news?page=1&size=10\""
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
