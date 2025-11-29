<?php
/**
 * CORS Bundle 诊断脚本
 * 用于验证 NelmioCorsBundle 配置和环境变量
 */

header('Content-Type: application/json');

// 记录开始时间
$start_time = microtime(true);

$diagnosis = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'server_info' => $_SERVER,
    'environment' => [],
    'bundle_check' => [],
    'config_analysis' => [],
    'nginx_headers' => [],
    'test_results' => []
];

// 1. 环境变量检查
$diagnosis['environment'] = [
    'APP_ENV' => $_ENV['APP_ENV'] ?? 'not_set',
    'APP_DEBUG' => $_ENV['APP_DEBUG'] ?? 'not_set',
    'CORS_ALLOW_ORIGIN' => $_ENV['CORS_ALLOW_ORIGIN'] ?? 'not_set',
    'SYMFONY_ENV' => $_ENV['SYMFONY_ENV'] ?? 'not_set',
    'all_env_vars' => array_filter($_ENV, function($key) {
        return strpos($key, 'APP_') === 0 || strpos($key, 'CORS_') === 0 || strpos($key, 'SYMFONY_') === 0;
    }, ARRAY_FILTER_USE_KEY)
];

// 2. 检查 Bundle 配置文件
$bundle_config_file = __DIR__ . '/../config/bundles.php';
if (file_exists($bundle_config_file)) {
    $bundles = include $bundle_config_file;
    $diagnosis['bundle_check'] = [
        'nelmio_cors_loaded' => isset($bundles['Nelmio\\CorsBundle\\NelmioCorsBundle']),
        'bundle_config_exists' => true,
        'all_bundles' => array_keys($bundles)
    ];
} else {
    $diagnosis['bundle_check'] = [
        'bundle_config_exists' => false,
        'error' => 'bundles.php file not found'
    ];
}

// 3. 检查 CORS 配置文件
$cors_config_file = __DIR__ . '/../config/packages/nelmio_cors.yaml';
if (file_exists($cors_config_file)) {
    $diagnosis['config_analysis'] = [
        'config_file_exists' => true,
        'config_content' => file_get_contents($cors_config_file),
        'file_size' => filesize($cors_config_file),
        'last_modified' => date('Y-m-d H:i:s', filemtime($cors_config_file))
    ];
} else {
    $diagnosis['config_analysis'] = [
        'config_file_exists' => false,
        'error' => 'nelmio_cors.yaml file not found'
    ];
}

// 4. 检查当前请求头
$diagnosis['nginx_headers'] = [
    'all_headers' => getallheaders(),
    'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'none',
    'request_method' => $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? 'none',
    'request_headers' => $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? 'none',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'none'
];

// 5. 模拟 OPTIONS 请求测试
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // 设置基本的CORS头进行测试
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 3600');

    $diagnosis['test_results']['options_handled'] = true;
    $diagnosis['test_results']['response_headers_set'] = true;

    http_response_code(200);
} else {
    $diagnosis['test_results']['options_handled'] = false;
    $diagnosis['test_results']['request_method'] = $_SERVER['REQUEST_METHOD'];

    // 为非OPTIONS请求也设置CORS头
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
}

// 6. 检查 Symfony 内核是否可用
$kernel_file = __DIR__ . '/../src/Kernel.php';
if (file_exists($kernel_file)) {
    $diagnosis['symfony_check'] = [
        'kernel_exists' => true,
        'kernel_readable' => is_readable($kernel_file),
        'kernel_path' => $kernel_file
    ];

    // 尝试获取 Symfony 版本（如果可能）
    try {
        if (class_exists('\Symfony\Component\HttpKernel\Kernel')) {
            $diagnosis['symfony_check']['symfony_version'] = \Symfony\Component\HttpKernel\Kernel::VERSION;
        }
    } catch (Exception $e) {
        $diagnosis['symfony_check']['version_error'] = $e->getMessage();
    }
} else {
    $diagnosis['symfony_check'] = [
        'kernel_exists' => false,
        'error' => 'Kernel.php not found'
    ];
}

// 7. 性能统计
$diagnosis['performance'] = [
    'execution_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms',
    'memory_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB'
];

// 8. 问题检测和诊断
$issues = [];

if ($diagnosis['environment']['APP_ENV'] === 'dev' && $diagnosis['environment']['APP_DEBUG'] === 'true') {
    $issues[] = 'WARNING: Running in development mode but Nginx may be forcing production environment';
}

if (!$diagnosis['bundle_check']['nelmio_cors_loaded'] ?? false) {
    $issues[] = 'CRITICAL: NelmioCorsBundle is not loaded in bundles.php';
}

if (!$diagnosis['config_analysis']['config_file_exists'] ?? false) {
    $issues[] = 'CRITICAL: nelmio_cors.yaml configuration file not found';
}

if ($diagnosis['environment']['CORS_ALLOW_ORIGIN'] === 'not_set') {
    $issues[] = 'WARNING: CORS_ALLOW_ORIGIN environment variable not set';
}

$diagnosis['issues_detected'] = $issues;
$diagnosis['diagnosis_summary'] = [
    'total_issues' => count($issues),
    'critical_issues' => count(array_filter($issues, function($issue) {
        return strpos($issue, 'CRITICAL') === 0;
    })),
    'warnings' => count(array_filter($issues, function($issue) {
        return strpos($issue, 'WARNING') === 0;
    }))
];

// 输出诊断结果
echo json_encode([
    'success' => true,
    'diagnosis' => $diagnosis
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
