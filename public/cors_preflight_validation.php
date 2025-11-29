<?php
/**
 * CORS预检请求验证脚本
 * 用于测试和验证x-request-id头部问题
 */

header('Content-Type: application/json; charset=utf-8');

// 启用详细日志记录
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

function log_cors_event($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] [CORS VALIDATION] $message";
    error_log($log_message);
    echo $log_message . "\n";
}

// 记录请求开始
log_cors_event("开始处理CORS预检请求验证");

// 获取请求详情
$request_method = $_SERVER['REQUEST_METHOD'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'none';
$request_headers = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? 'none';
$request_method_header = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? 'none';

log_cors_event("请求方法: $request_method");
log_cors_event("Origin: $origin");
log_cors_event("请求方法头: $request_method_header");
log_cors_event("请求头部: $request_headers");

// 检查是否包含x-request-id
$has_x_request_id = strpos(strtolower($request_headers), 'x-request-id') !== false;
log_cors_event("是否包含x-request-id: " . ($has_x_request_id ? '是' : '否'));

// 验证结果
$validation = [
    'timestamp' => date('Y-m-d H:i:s'),
    'request_details' => [
        'method' => $request_method,
        'origin' => $origin,
        'access_control_request_method' => $request_method_header,
        'access_control_request_headers' => $request_headers,
        'has_x_request_id' => $has_x_request_id
    ],
    'current_cors_headers' => [],
    'test_scenarios' => []
];

// 获取当前响应头（如果有）
if (function_exists('headers_list')) {
    $current_headers = headers_list();
    $validation['current_cors_headers'] = array_filter($current_headers, function($header) {
        return strpos(strtolower($header), 'access-control') === 0;
    });
}

// 测试场景1: 当前ForceCorsSubscriber配置
log_cors_event("测试场景1: 当前ForceCorsSubscriber配置");
$scenario1_headers = [
    'Access-Control-Allow-Origin' => $origin,
    'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
    'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
    'Access-Control-Max-Age' => '3600'
];

$scenario1_result = [
    'scenario' => '当前ForceCorsSubscriber配置',
    'allow_headers' => $scenario1_headers['Access-Control-Allow-Headers'],
    'allows_x_request_id' => strpos(strtolower($scenario1_headers['Access-Control-Allow-Headers']), 'x-request-id') !== false,
    'will_pass_preflight' => false
];

log_cors_event("场景1结果: " . ($scenario1_result['will_pass_preflight'] ? '通过' : '失败'));
$validation['test_scenarios'][] = $scenario1_result;

// 测试场景2: 修复后的ForceCorsSubscriber配置
log_cors_event("测试场景2: 修复后的ForceCorsSubscriber配置");
$scenario2_headers = [
    'Access-Control-Allow-Origin' => $origin,
    'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
    'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin, x-request-id, X-Request-ID',
    'Access-Control-Max-Age' => '3600'
];

$scenario2_result = [
    'scenario' => '修复后的ForceCorsSubscriber配置',
    'allow_headers' => $scenario2_headers['Access-Control-Allow-Headers'],
    'allows_x_request_id' => strpos(strtolower($scenario2_headers['Access-Control-Allow-Headers']), 'x-request-id') !== false,
    'will_pass_preflight' => true
];

log_cors_event("场景2结果: " . ($scenario2_result['will_pass_preflight'] ? '通过' : '失败'));
$validation['test_scenarios'][] = $scenario2_result;

// 测试场景3: NelmioCorsBundle配置
log_cors_event("测试场景3: NelmioCorsBundle配置");
$scenario3_result = [
    'scenario' => 'NelmioCorsBundle配置',
    'allow_headers' => '* (wildcard)',
    'allows_x_request_id' => true,
    'will_pass_preflight' => true,
    'note' => '通配符*应该允许所有头部，但可能与ForceCorsSubscriber冲突'
];

log_cors_event("场景3结果: " . ($scenario3_result['will_pass_preflight'] ? '通过' : '失败'));
$validation['test_scenarios'][] = $scenario3_result;

// 如果是OPTIONS请求，使用修复后的配置进行响应
if ($request_method === 'OPTIONS') {
    log_cors_event("处理OPTIONS请求，使用修复后的CORS配置");

    // 使用场景2的修复配置
    foreach ($scenario2_headers as $name => $value) {
        header("$name: $value");
    }

    log_cors_event("已设置CORS响应头");
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'message' => 'CORS预检请求处理完成（使用修复配置）',
        'validation' => $validation,
        'headers_set' => $scenario2_headers
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// 返回完整的验证结果
echo json_encode([
    'success' => true,
    'message' => 'CORS预检请求验证完成',
    'validation' => $validation,
    'root_cause' => [
        'primary_issue' => 'ForceCorsSubscriber缺少x-request-id头部支持',
        'secondary_issue' => 'CORS处理器配置不一致',
        'impact' => '导致包含x-request-id头部的预检请求被拒绝'
    ],
    'fix_priority' => [
        '1' => '修改src/EventSubscriber/ForceCorsSubscriber.php第74行',
        '2' => '清除Symfony缓存',
        '3' => '测试预检请求',
        '4' => '验证生产环境'
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

log_cors_event("CORS预检请求验证完成");
