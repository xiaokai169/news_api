<?php
/**
 * CORS系统级诊断工具
 * 用于全面排查生产环境CORS问题
 */

header('Content-Type: application/json; charset=utf-8');

// 启用错误报告用于诊断
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 诊断结果数组
$diagnosis = [
    'timestamp' => date('Y-m-d H:i:s'),
    'request_info' => [],
    'environment_check' => [],
    'cors_configuration' => [],
    'nginx_symfony_conflict' => [],
    'security_analysis' => [],
    'cache_analysis' => [],
    'recommendations' => []
];

// 1. 请求信息分析
$diagnosis['request_info'] = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'unknown',
    'host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
    'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'none',
    'access_control_request_method' => $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? 'none',
    'access_control_request_headers' => $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? 'none',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'referer' => $_SERVER['HTTP_REFERER'] ?? 'none',
    'all_headers' => function_exists('getallheaders') ? getallheaders() : []
];

// 2. 环境配置检查
$diagnosis['environment_check'] = [
    'app_env' => $_ENV['APP_ENV'] ?? 'not_set',
    'app_debug' => $_ENV['APP_DEBUG'] ?? 'not_set',
    'symfony_env' => $_ENV['SYMFONY_ENV'] ?? 'not_set',
    'cors_allow_origin' => $_ENV['CORS_ALLOW_ORIGIN'] ?? 'not_set',
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
    'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'unknown',
    'fastcgi_params' => []
];

// 检查FastCGI参数
$fastcgi_params = ['APP_ENV', 'APP_DEBUG', 'SYMFONY_ENV', 'CORS_ALLOW_ORIGIN'];
foreach ($fastcgi_params as $param) {
    $diagnosis['environment_check']['fastcgi_params'][$param] = [
        'env_value' => $_ENV[$param] ?? 'not_set',
        'server_value' => $_SERVER[$param] ?? 'not_set',
        'getenv_value' => getenv($param) ?: 'not_set'
    ];
}

// 3. CORS配置分析
$diagnosis['cors_configuration'] = [
    'nelmio_cors_file_exists' => file_exists(__DIR__ . '/../config/packages/nelmio_cors.yaml'),
    'nelmio_cors_readable' => is_readable(__DIR__ . '/../config/packages/nelmio_cors.yaml'),
    'cors_bundle_enabled' => false,
    'path_analysis' => []
];

// 检查NelmioCorsBundle是否启用
if (file_exists(__DIR__ . '/../config/bundles.php')) {
    $bundles = include __DIR__ . '/../config/bundles.php';
    $diagnosis['cors_configuration']['cors_bundle_enabled'] = isset($bundles['Nelmio\\CorsBundle\\NelmioCorsBundle']);
}

// 分析当前路径是否匹配CORS规则
$current_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
$diagnosis['cors_configuration']['path_analysis'] = [
    'current_path' => $current_path,
    'matches_api' => preg_match('/^\/api\//', $current_path),
    'matches_official_api' => preg_match('/^\/official-api\//', $current_path),
    'matches_public_api' => preg_match('/^\/public-api\//', $current_path),
    'should_have_cors' => false
];

$diagnosis['cors_configuration']['path_analysis']['should_have_cors'] =
    $diagnosis['cors_configuration']['path_analysis']['matches_api'] ||
    $diagnosis['cors_configuration']['path_analysis']['matches_official_api'] ||
    $diagnosis['cors_configuration']['path_analysis']['matches_public_api'];

// 4. Nginx与Symfony冲突分析
$diagnosis['nginx_symfony_conflict'] = [
    'nginx_config_exists' => file_exists(__DIR__ . '/../nginx_site_config.conf'),
    'nginx_cors_headers_commented' => false,
    'potential_conflicts' => []
];

// 检查Nginx配置中的CORS设置
if (file_exists(__DIR__ . '/../nginx_site_config.conf')) {
    $nginx_config = file_get_contents(__DIR__ . '/../nginx_site_config.conf');
    $diagnosis['nginx_symfony_conflict']['nginx_cors_headers_commented'] =
        strpos($nginx_config, '# add_header Access-Control-Allow-Origin') !== false;

    // 检查潜在的冲突
    if (strpos($nginx_config, 'add_header Access-Control') !== false &&
        strpos($nginx_config, '# add_header Access-Control') === false) {
        $diagnosis['nginx_symfony_conflict']['potential_conflicts'][] = 'Nginx可能设置了CORS头';
    }
}

// 5. 安全分析
$diagnosis['security_analysis'] = [
    'security_config_exists' => file_exists(__DIR__ . '/../config/packages/security.yaml'),
    'api_firewall_disabled' => false,
    'access_control_allows_api' => false,
    'options_method_allowed' => true
];

// 检查安全配置
if (file_exists(__DIR__ . '/../config/packages/security.yaml')) {
    $security_config = file_get_contents(__DIR__ . '/../config/packages/security.yaml');
    $diagnosis['security_analysis']['api_firewall_disabled'] =
        strpos($security_config, 'security: false') !== false;
    $diagnosis['security_analysis']['access_control_allows_api'] =
        strpos($security_config, 'PUBLIC_ACCESS') !== false;
}

// 6. 缓存分析
$diagnosis['cache_analysis'] = [
    'cache_directory' => __DIR__ . '/../var/cache',
    'cache_exists' => file_exists(__DIR__ . '/../var/cache'),
    'prod_cache_exists' => file_exists(__DIR__ . '/../var/cache/prod'),
    'dev_cache_exists' => file_exists(__DIR__ . '/../var/cache/dev'),
    'cache_writable' => is_writable(__DIR__ . '/../var/cache'),
    'last_cache_clear' => 'unknown'
];

// 检查缓存最后修改时间
if (file_exists(__DIR__ . '/../var/cache')) {
    $cache_mtime = filemtime(__DIR__ . '/../var/cache');
    $diagnosis['cache_analysis']['last_cache_clear'] = date('Y-m-d H:i:s', $cache_mtime);
}

// 7. 生成建议
$diagnosis['recommendations'] = [];

// 环境不一致建议
if ($diagnosis['environment_check']['app_env'] !== 'prod' &&
    $diagnosis['environment_check']['symfony_env'] === 'prod') {
    $diagnosis['recommendations'][] = [
        'priority' => 'HIGH',
        'issue' => '环境配置不一致',
        'description' => 'APP_ENV为dev但SYMFONY_ENV为prod，可能导致CORS配置混乱',
        'solution' => '统一环境变量配置，确保APP_ENV和SYMFONY_ENV一致'
    ];
}

// CORS Bundle未启用建议
if (!$diagnosis['cors_configuration']['cors_bundle_enabled']) {
    $diagnosis['recommendations'][] = [
        'priority' => 'CRITICAL',
        'issue' => 'NelmioCorsBundle未启用',
        'description' => 'CORS Bundle未在bundles.php中启用',
        'solution' => '在config/bundles.php中启用NelmioCorsBundle'
    ];
}

// 路径不匹配建议
if (!$diagnosis['cors_configuration']['path_analysis']['should_have_cors']) {
    $diagnosis['recommendations'][] = [
        'priority' => 'HIGH',
        'issue' => '当前路径不匹配CORS规则',
        'description' => '请求路径' . $current_path . '不在CORS配置的路径范围内',
        'solution' => '在nelmio_cors.yaml中添加对应路径配置'
    ];
}

// 缓存问题建议
if (!$diagnosis['cache_analysis']['cache_writable']) {
    $diagnosis['recommendations'][] = [
        'priority' => 'MEDIUM',
        'issue' => '缓存目录不可写',
        'description' => '缓存目录权限问题可能导致CORS配置不生效',
        'solution' => '检查并修复var/cache目录权限'
    ];
}

// 8. 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // 设置CORS头用于测试
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 3600');
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'message' => 'CORS预检请求诊断完成',
        'diagnosis' => $diagnosis
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// 9. 返回诊断结果
echo json_encode([
    'success' => true,
    'message' => 'CORS系统诊断完成',
    'diagnosis' => $diagnosis,
    'next_steps' => [
        '1. 检查recommendations中的高优先级问题',
        '2. 验证环境变量配置',
        '3. 清除Symfony缓存',
        '4. 测试OPTIONS预检请求',
        '5. 检查生产环境Nginx配置'
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
