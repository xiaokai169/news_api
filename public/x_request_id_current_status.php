<?php
/**
 * X-Request-Id头部状态验证脚本
 * 检查当前CORS配置中是否包含X-Request-Id头部
 */

header('Content-Type: application/json; charset=utf-8');

function testCorsHeaders() {
    $results = [];

    // 1. 检查nelmio_cors.yaml配置
    $corsConfigPath = __DIR__ . '/../config/packages/nelmio_cors.yaml';
    if (file_exists($corsConfigPath)) {
        $content = file_get_contents($corsConfigPath);
        $results['nelmio_cors_config'] = [
            'file_exists' => true,
            'allow_headers_line' => strpos($content, 'allow_headers:') !== false,
            'allow_headers_wildcard' => strpos($content, "['*']") !== false,
            'contains_x_request_id' => strpos($content, 'X-Request-Id') !== false || strpos($content, 'x-request-id') !== false,
            'config_content' => $content
        ];
    } else {
        $results['nelmio_cors_config'] = ['file_exists' => false];
    }

    // 2. 检查Event Subscriber配置
    $subscribers = [
        'ForceCorsSubscriber' => __DIR__ . '/../src/EventSubscriber/ForceCorsSubscriber.php',
        'ProductionCorsSubscriber' => __DIR__ . '/../src/EventSubscriber/ProductionCorsSubscriber.php',
        'CorsDebugSubscriber' => __DIR__ . '/../src/EventSubscriber/CorsDebugSubscriber.php'
    ];

    foreach ($subscribers as $name => $path) {
        if (file_exists($path)) {
            $content = file_get_contents($path);
            $results["subscriber_{$name}"] = [
                'file_exists' => true,
                'contains_x_request_id' => strpos($content, 'X-Request-Id') !== false || strpos($content, 'x-request-id') !== false,
                'has_allow_headers_setting' => strpos($content, 'Access-Control-Allow-Headers') !== false,
                'allow_headers_content' => []
            ];

            // 提取Allow-Headers的具体内容
            if (preg_match_all("/Access-Control-Allow-Headers.*?['\"]([^'\"]+)['\"]/", $content, $matches)) {
                $results["subscriber_{$name}"]['allow_headers_content'] = $matches[1];
            }
        } else {
            $results["subscriber_{$name}"] = ['file_exists' => false];
        }
    }

    // 3. 模拟OPTIONS请求测试
    $testOrigins = ['https://example.com', 'http://localhost:3000'];
    $testHeaders = ['Content-Type', 'Authorization', 'X-Request-Id'];

    $results['options_request_test'] = [];
    foreach ($testOrigins as $origin) {
        $headers = [
            'Origin: ' . $origin,
            'Access-Control-Request-Method: POST',
            'Access-Control-Request-Headers: ' . implode(', ', $testHeaders),
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://' . $_SERVER['HTTP_HOST'] . '/api/test');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $results['options_request_test'][$origin] = [
            'http_code' => $httpCode,
            'error' => $error,
            'response_headers' => $response ? extractHeaders($response) : [],
            'has_x_request_id_in_allow_headers' => false
        ];

        // 检查响应头中是否包含X-Request-Id
        if ($response && preg_match('/Access-Control-Allow-Headers:\s*(.+)/i', $response, $matches)) {
            $allowHeaders = $matches[1];
            $results['options_request_test'][$origin]['has_x_request_id_in_allow_headers'] =
                strpos(strtolower($allowHeaders), 'x-request-id') !== false;
            $results['options_request_test'][$origin]['allow_headers_value'] = trim($allowHeaders);
        }
    }

    return $results;
}

function extractHeaders($response) {
    $headers = [];
    $headerText = substr($response, 0, strpos($response, "\r\n\r\n"));
    $headerLines = explode("\r\n", $headerText);

    foreach ($headerLines as $line) {
        if (strpos($line, ':') !== false) {
            list($key, $value) = explode(':', $line, 2);
            $headers[trim($key)] = trim($value);
        }
    }

    return $headers;
}

// 执行测试
$testResults = testCorsHeaders();

// 生成诊断报告
$diagnosis = [
    'timestamp' => date('Y-m-d H:i:s'),
    'test_results' => $testResults,
    'issues_found' => [],
    'recommendations' => []
];

// 分析问题
if (!$testResults['nelmio_cors_config']['file_exists']) {
    $diagnosis['issues_found'][] = 'nelmio_cors.yaml配置文件不存在';
} elseif (!$testResults['nelmio_cors_config']['contains_x_request_id'] && !$testResults['nelmio_cors_config']['allow_headers_wildcard']) {
    $diagnosis['issues_found'][] = 'nelmio_cors.yaml中未配置X-Request-Id头部';
}

foreach (['ForceCorsSubscriber', 'ProductionCorsSubscriber'] as $subscriber) {
    $key = "subscriber_{$subscriber}";
    if ($testResults[$key]['file_exists'] && !$testResults[$key]['contains_x_request_id']) {
        $diagnosis['issues_found'][] = "{$subscriber}中未配置X-Request-Id头部";
    }
}

// 检查OPTIONS请求测试结果
foreach ($testResults['options_request_test'] as $origin => $result) {
    if (!$result['has_x_request_id_in_allow_headers']) {
        $diagnosis['issues_found'][] = "OPTIONS请求测试失败 - Origin: {$origin} 的响应中缺少X-Request-Id头部";
    }
}

// 生成建议
if (!empty($diagnosis['issues_found'])) {
    $diagnosis['recommendations'][] = '在所有CORS配置中添加X-Request-Id头部';
    $diagnosis['recommendations'][] = '确保Event Subscriber中的配置与nelmio_cors.yaml一致';
    $diagnosis['recommendations'][] = '测试所有API路径的CORS配置';
} else {
    $diagnosis['recommendations'][] = 'X-Request-Id头部配置正确，无需修改';
}

echo json_encode($diagnosis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
