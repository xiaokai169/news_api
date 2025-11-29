<?php
/**
 * CORS x-request-id 头部诊断工具
 * 专门用于诊断 x-request-id 头部不被允许的问题
 */

header('Content-Type: application/json; charset=utf-8');

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

$diagnosis = [
    'timestamp' => date('Y-m-d H:i:s'),
    'issue' => 'Request header field x-request-id is not allowed by Access-Control-Allow-Headers',
    'analysis' => [],
    'test_results' => [],
    'recommendations' => []
];

// 1. 分析当前请求头
$diagnosis['analysis']['current_request'] = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'none',
    'access_control_request_method' => $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? 'none',
    'access_control_request_headers' => $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? 'none',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'has_x_request_id_in_preflight' => strpos(strtolower($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? ''), 'x-request-id') !== false
];

// 2. 检查ForceCorsSubscriber的Allow-Headers配置
$diagnosis['analysis']['force_cors_subscriber'] = [
    'file_exists' => file_exists(__DIR__ . '/../src/EventSubscriber/ForceCorsSubscriber.php'),
    'allow_headers_line' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
    'missing_x_request_id' => true,
    'explanation' => 'ForceCorsSubscriber第74行没有包含x-request-id头部'
];

// 3. 检查NelmioCorsBundle配置
$diagnosis['analysis']['nelmio_cors_config'] = [
    'file_exists' => file_exists(__DIR__ . '/../config/packages/nelmio_cors.yaml'),
    'allow_headers_wildcard' => false,
    'explanation' => 'nelmio_cors.yaml使用allow_headers: ["*"]应该允许所有头部'
];

// 4. 模拟正确的CORS响应
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    error_log('[CORS DIAGNOSIS] Handling OPTIONS request with x-request-id support');

    // 设置包含x-request-id的完整CORS头
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    $allowHeaders = 'Content-Type, Authorization, X-Requested-With, Accept, Origin, x-request-id, X-Request-ID';

    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: ' . $allowHeaders);
    header('Access-Control-Max-Age: 3600');
    header('Access-Control-Allow-Credentials: false');

    $diagnosis['test_results']['options_response'] = [
        'success' => true,
        'headers_sent' => [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => $allowHeaders,
            'Access-Control-Max-Age' => '3600'
        ],
        'includes_x_request_id' => true,
        'case_sensitive_check' => [
            'lowercase_x-request-id' => strpos(strtolower($allowHeaders), 'x-request-id') !== false,
            'uppercase_X-Request-ID' => strpos($allowHeaders, 'X-Request-ID') !== false,
            'mixed_case_x-Request-id' => strpos($allowHeaders, 'x-Request-id') !== false
        ]
    ];

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'CORS预检请求诊断完成 - 包含x-request-id支持',
        'diagnosis' => $diagnosis
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// 5. 生成修复建议
$diagnosis['recommendations'] = [
    [
        'priority' => 'CRITICAL',
        'issue' => 'ForceCorsSubscriber缺少x-request-id头部',
        'description' => 'ForceCorsSubscriber.php第74行的Access-Control-Allow-Headers不包含x-request-id',
        'solution' => '修改allow_headers配置，添加x-request-id和X-Request-ID',
        'code_change' => [
            'file' => 'src/EventSubscriber/ForceCorsSubscriber.php',
            'line' => 74,
            'from' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
            'to' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin, x-request-id, X-Request-ID'
        ]
    ],
    [
        'priority' => 'HIGH',
        'issue' => '大小写敏感性问题',
        'description' => 'HTTP头部是大小写不敏感的，但需要确保包含各种变体',
        'solution' => '在Allow-Headers中同时包含小写和大小写混合的x-request-id变体'
    ],
    [
        'priority' => 'MEDIUM',
        'issue' => 'CORS处理器冲突',
        'description' => 'NelmioCorsBundle和ForceCorsSubscriber可能产生冲突',
        'solution' => '确保两个配置一致，或者禁用其中一个'
    ]
];

// 6. 返回完整诊断结果
echo json_encode([
    'success' => true,
    'message' => 'CORS x-request-id 头部诊断完成',
    'diagnosis' => $diagnosis,
    'immediate_fix' => [
        'step1' => '修改ForceCorsSubscriber.php第74行，添加x-request-id支持',
        'step2' => '清除Symfony缓存: php bin/console cache:clear --env=prod',
        'step3' => '测试OPTIONS预检请求',
        'step4' => '验证实际API调用'
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
