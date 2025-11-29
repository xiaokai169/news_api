
<?php
/**
 * X-Request-Id头部修复验证脚本
 * 验证CORS配置中X-Request-Id头部的修复效果
 */

header('Content-Type: application/json; charset=utf-8');

function verifyXRequestIdFix() {
    $results = [];

    // 1. 检查配置文件中的修复
    $results['config_verification'] = [
        'nelmio_cors' => checkNelmioCorsConfig(),
        'production_subscriber' => checkProductionSubscriberConfig(),
        'force_subscriber' => checkForceSubscriberConfig()
    ];

    // 2. 模拟OPTIONS请求测试
    $results['options_test'] = performOptionsRequestTest();

    // 3. 生成诊断报告
    $results['diagnosis'] = generateDiagnosis($results);

    return $results;
}

function checkNelmioCorsConfig() {
    $configPath = __DIR__ . '/../config/packages/nelmio_cors.yaml';
    if (!file_exists($configPath)) {
        return ['status' => 'error', 'message' => '配置文件不存在'];
    }

    $content = file_get_contents($configPath);

    // 检查是否包含所有X-Request-Id变体
    $xRequestIdVariants = ['x-request-id', 'X-Request-Id', 'X-Request-ID'];
    $foundVariants = [];

    foreach ($xRequestIdVariants as $variant) {
        if (strpos($content, $variant) !== false) {
            $foundVariants[] = $variant;
        }
    }

    return [
        'status' => 'success',
        'has_all_variants' => count($foundVariants) === count($xRequestIdVariants),
        'found_variants' => $foundVariants,
        'allow_headers_line' => extractAllowHeadersLine($content)
    ];
}

function checkProductionSubscriberConfig() {
    $configPath = __DIR__ . '/../src/EventSubscriber/ProductionCorsSubscriber.php';
    if (!file_exists($configPath)) {
        return ['status' => 'error', 'message' => 'ProductionCorsSubscriber不存在'];
    }

    $content = file_get_contents($configPath);

    // 检查Allow-Headers行
    if (preg_match("/Access-Control-Allow-Headers.*?['\"]([^'\"]+)['\"]/", $content, $matches)) {
        $allowHeaders = $matches[1];
        $hasXRequestId = strpos(strtolower($allowHeaders), 'x-request-id') !== false;

        return [
            'status' => 'success',
            'has_x_request_id' => $hasXRequestId,
            'allow_headers_value' => $allowHeaders,
            'contains_variants' => [
                'x-request-id' => strpos($allowHeaders, 'x-request-id') !== false,
                'X-Request-Id' => strpos($allowHeaders, 'X-Request-Id') !== false,
                'X-Request-ID' => strpos($allowHeaders, 'X-Request-ID') !== false
            ]
        ];
    }

    return ['status' => 'error', 'message' => '未找到Allow-Headers配置'];
}

function checkForceSubscriberConfig() {
    $configPath = __DIR__ . '/../src/EventSubscriber/ForceCorsSubscriber.php';
    if (!file_exists($configPath)) {
        return ['status' => 'error', 'message' => 'ForceCorsSubscriber不存在'];
    }

    $content = file_get_contents($configPath);

    if (preg_match("/Access-Control-Allow-Headers.*?['\"]([^'\"]+)['\"]/", $content, $matches)) {
        $allowHeaders = $matches[1];
        $hasXRequestId = strpos(strtolower($allowHeaders), 'x-request-id') !== false;

        return [
            'status' => 'success',
            'has_x_request_id' => $hasXRequestId,
            'allow_headers_value' => $allowHeaders
        ];
    }

    return ['status' => 'error', 'message' => '未找到Allow-Headers配置'];
}

function performOptionsRequestTest() {
    $testCases = [
        [
            'name' => '标准API路径测试',
            'path' => '/api/test',
            'origin' => 'https://example.com',
            'request_headers' => 'Content-Type, Authorization, X-Request-Id'
        ],
        [
            'name' => 'Official API路径测试',
            'path' => '/official-api/news',
            'origin' => 'http://localhost:3000',
            'request_headers' => 'Content-Type, X-Request-Id'
        ],
        [
            'name' => 'Public API路径测试',
            'path' => '/public-api/test',
            'origin' => 'https://frontend.example.com',
            'request_headers' => 'X-Request-Id, Accept'
        ]
    ];

    $results = [];

    foreach ($testCases as $testCase) {
        $result = testOptionsRequest($testCase);
        $results[$testCase['name']] = $result;
    }

    return $results;
}

function testOptionsRequest($testCase) {
    $headers = [
        'Origin: ' . $testCase['origin'],
        'Access-Control-Request-Method: POST',
        'Access-Control-Request-Headers: ' . $testCase['request_headers'],
        'Content-Type: application/json'
    ];

    $url = 'http://' . $_SERVER['HTTP_HOST'] . $testCase['path'];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $result = [
        'path' => $testCase['path'],
        'origin' => $testCase['origin'],
        'request_headers' => $testCase['request_headers'],
        'http_code' => $httpCode,
        'error' => $error,
        'success' => $httpCode === 200 || $httpCode === 204,
        'cors_headers' => []
    ];

    if ($response) {
        $result['cors_headers'] = extractCorsHeaders($response);
        $result['has_x_request_id'] = checkXRequestIdInHeaders($result['cors_headers']);
    }

    return $result;
}

function extractCorsHeaders($response) {
    $headers = [];
    $headerText = substr($response, 0, strpos($response, "\r\n\r\n"));
    $headerLines = explode("\r\n", $headerText);

    foreach ($headerLines as $line) {
        if (strpos($line, ':') !== false) {
            list($key, $value) = explode(':', $line, 2);
            $key = trim($key);
            if (strpos(strtolower($key), 'access-control') === 0) {
                $headers[$key] = trim($value);
            }
        }
    }

    return $headers;
}

function checkXRequestIdInHeaders($corsHeaders) {
    $allowHeaders = $corsHeaders['Access-Control-Allow-Headers'] ?? '';
    return strpos(strtolower($allowHeaders), 'x-request-id') !== false;
}

function extractAllowHeadersLine($content) {
    if (preg_match("/allow_headers:\s*\[([^\]]+)\]/", $content, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

function generateDiagnosis($results) {
    $diagnosis = [
        'timestamp' => date('Y-m-d H:i:s'),
        'overall_status' => 'success',
        'issues_found' => [],
        'fixes_applied' => [],
        'recommendations' => []
    ];

    // 检查配置文件状态
    foreach ($results['config_verification'] as $config => $result) {
        if ($result['status'] !== 'success') {
            $diagnosis['issues_found'][] = "{$config}配置错误: " . $result['message'];
            $diagnosis['overall_status'] = 'error';
        } elseif (!$result['has_x_request_id'] ?? false) {
            $diagnosis['issues_found'][] = "{$config}中缺少X-Request-Id头部";
            $diagnosis['overall_status'] = 'warning';
        }
    }

    // 检查OPTIONS测试结果
    $failedTests = [];
