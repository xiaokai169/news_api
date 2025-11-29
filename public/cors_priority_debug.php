<?php
/**
 * CORS优先级调试脚本
 * 检查哪个组件在设置CORS头
 */

header('Content-Type: application/json; charset=utf-8');

// 模拟OPTIONS请求来检查CORS头的来源
function debugCorsHeaders() {
    $testPath = '/api/test';
    $testOrigin = 'https://example.com';
    $testHeaders = 'Content-Type, Authorization, X-Request-Id';

    $headers = [
        'Origin: ' . $testOrigin,
        'Access-Control-Request-Method: POST',
        'Access-Control-Request-Headers: ' . $testHeaders,
        'Content-Type: application/json',
        'User-Agent: CORS-Debug-Script'
    ];

    $url = 'http://' . $_SERVER['HTTP_HOST'] . $testPath;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_STDERR, fopen('php://temp', 'w+'));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    // 获取verbose输出
    $verboseLog = stream_get_contents(curl_getinfo($ch, CURLINFO_STDERR));
    curl_close($ch);

    $result = [
        'test_info' => [
            'path' => $testPath,
            'origin' => $testOrigin,
            'request_headers' => $testHeaders,
            'http_code' => $httpCode,
            'error' => $error
        ],
        'response_analysis' => []
    ];

    if ($response) {
        // 提取响应头
        $headerText = substr($response, 0, strpos($response, "\r\n\r\n"));
        $headerLines = explode("\r\n", $headerText);

        $corsHeaders = [];
        foreach ($headerLines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $key = trim($key);
                if (strpos(strtolower($key), 'access-control') === 0) {
                    $corsHeaders[$key] = trim($value);
                }
            }
        }

        $result['response_analysis']['cors_headers'] = $corsHeaders;

        // 分析Allow-Headers
        $allowHeaders = $corsHeaders['Access-Control-Allow-Headers'] ?? '';
        $result['response_analysis']['allow_headers_analysis'] = [
            'raw_value' => $allowHeaders,
            'has_x_request_id' => strpos(strtolower($allowHeaders), 'x-request-id') !== false,
            'headers_list' => array_map('trim', explode(',', $allowHeaders))
        ];

        // 检查是否包含我们期望的头部
        $expectedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin'];
        $actualHeaders = array_map('trim', explode(',', $allowHeaders));

        $result['response_analysis']['header_comparison'] = [
            'expected_base_headers' => $expectedHeaders,
            'actual_headers' => $actualHeaders,
            'missing_headers' => array_diff($expectedHeaders, $actualHeaders),
            'extra_headers' => array_diff($actualHeaders, $expectedHeaders)
        ];
    }

    $result['verbose_log'] = $verboseLog;

    return $result;
}

// 检查Symfony配置
function checkSymfonyConfig() {
    $result = [];

    // 检查环境变量
    $result['environment'] = [
        'APP_ENV' => $_ENV['APP_ENV'] ?? 'not_set',
        'APP_DEBUG' => $_ENV['APP_DEBUG'] ?? 'not_set',
        'CORS_ALLOW_ORIGIN' => $_ENV['CORS_ALLOW_ORIGIN'] ?? 'not_set'
    ];

    // 检查缓存状态
    $cacheDir = __DIR__ . '/../var/cache';
    $result['cache_status'] = [
        'cache_dir_exists' => is_dir($cacheDir),
        'cache_writable' => is_writable($cacheDir),
        'cache_contents' => is_dir($cacheDir) ? scandir($cacheDir) : []
    ];

    return $result;
}

// 执行调试
$debugResult = [
    'timestamp' => date('Y-m-d H:i:s'),
    'cors_test' => debugCorsHeaders(),
    'symfony_config' => checkSymfonyConfig(),
    'diagnosis' => []
];

// 生成诊断
$corsTest = $debugResult['cors_test'];
$allowHeaders = $corsTest['response_analysis']['allow_headers_analysis']['raw_value'] ?? '';

if (strpos(strtolower($allowHeaders), 'x-request-id') === false) {
    $debugResult['diagnosis'][] = '❌ X-Request-Id头部未在响应中找到';
    $debugResult['diagnosis'][] = '🔍 可能原因：NelmioCorsBundle覆盖了Event Subscriber配置';
    $debugResult['diagnosis'][] = '💡 解决方案：检查优先级或禁用冲突的Event Subscriber';
} else {
    $debugResult['diagnosis'][] = '✅ X-Request-Id头部已正确包含';
}

// 检查是否是基础的CORS配置
$basicHeaders = ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin'];
$actualHeaders = $corsTest['response_analysis']['allow_headers_analysis']['headers_list'] ?? [];
$hasOnlyBasicHeaders = empty(array_diff($actualHeaders, $basicHeaders));

if ($hasOnlyBasicHeaders) {
    $debugResult['diagnosis'][] = '⚠️  响应只包含基础CORS头部，可能被默认配置覆盖';
    $debugResult['diagnosis'][] = '🔧 建议：检查NelmioCorsBundle配置是否正确';
}

echo json_encode($debugResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
