<?php
/**
 * 微信同步API综合测试脚本
 * 测试400错误修复后的所有场景
 */

header('Content-Type: text/html; charset=utf-8');
echo "<h1>微信同步API综合测试</h1>\n";

// 测试配置
$baseUrl = 'http://127.0.0.1:8084/official-api/wechat/sync';
$testResults = [];
$testCount = 0;
$passedTests = 0;

// 测试用例定义
$testCases = [
    // 1. 原始问题场景 - 使用accountId，但没有articleLimit
    [
        'name' => '原始问题场景（缺少articleLimit）',
        'data' => ['accountId' => 'gh_e4b07b2a992e6669', 'force' => false],
        'expected_status' => 400,
        'expected_error' => 'recent范围必须提供文章数量限制'
    ],

    // 2. 修复后的场景 - 使用accountId，包含articleLimit
    [
        'name' => '修复后场景（完整参数）',
        'data' => [
            'accountId' => 'gh_e4b07b2a992e6669',
            'force' => false,
            'articleLimit' => 50
        ],
        'expected_status' => 200,
        'expected_error' => null
    ],

    // 3. 字段兼容性测试 - 使用publicAccountId
    [
        'name' => '字段兼容性（publicAccountId）',
        'data' => [
            'publicAccountId' => 'gh_e4b07b2a992e6669',
            'force' => false,
            'articleLimit' => 50
        ],
        'expected_status' => 200,
        'expected_error' => null
    ],

    // 4. 空accountId测试
    [
        'name' => '空accountId验证',
        'data' => [
            'accountId' => '',
            'force' => false,
            'articleLimit' => 50
        ],
        'expected_status' => 400,
        'expected_error' => '公众号ID不能为空'
    ],

    // 5. 使用all范围（不需要articleLimit）
    [
        'name' => '全部同步范围（不需要articleLimit）',
        'data' => [
            'accountId' => 'gh_e4b07b2a992e6669',
            'force' => false,
            'syncScope' => 'all'
        ],
        'expected_status' => 200,
        'expected_error' => null
    ],

    // 6. 自定义范围测试
    [
        'name' => '自定义范围测试',
        'data' => [
            'accountId' => 'gh_e4b07b2a992e6669',
            'force' => false,
            'syncScope' => 'custom',
            'syncStartTime' => '2024-01-01 00:00:00',
            'syncEndTime' => '2024-01-31 23:59:59'
        ],
        'expected_status' => 200,
        'expected_error' => null
    ],

    // 7. 自定义范围缺少时间
    [
        'name' => '自定义范围缺少时间参数',
        'data' => [
            'accountId' => 'gh_e4b07b2a992e6669',
            'force' => false,
            'syncScope' => 'custom'
        ],
        'expected_status' => 400,
        'expected_error' => '自定义范围必须提供开始时间和结束时间'
    ],

    // 8. 无效JSON测试
    [
        'name' => '无效JSON格式',
        'data' => 'invalid json',
        'expected_status' => 400,
        'expected_error' => null,
        'invalid_json' => true
    ],

    // 9. 文章数量超出限制
    [
        'name' => '文章数量超出限制',
        'data' => [
            'accountId' => 'gh_e4b07b2a992e6669',
            'force' => false,
            'articleLimit' => 1500
        ],
        'expected_status' => 400,
        'expected_error' => '文章数量限制不能超过1000'
    ],

    // 10. 完整参数测试
    [
        'name' => '完整参数测试',
        'data' => [
            'accountId' => 'gh_e4b07b2a992e6669',
            'syncType' => 'articles',
            'forceSync' => false,
            'syncScope' => 'recent',
            'articleLimit' => 100,
            'includeDeleted' => false,
            'autoHandleDuplicates' => true,
            'duplicateAction' => 'update',
            'async' => true,
            'priority' => 5
        ],
        'expected_status' => 200,
        'expected_error' => null
    ]
];

// 执行测试
foreach ($testCases as $index => $testCase) {
    $testCount++;
    echo "<h3>测试 {$testCount}: {$testCase['name']}</h3>\n";

    // 准备请求
    $ch = curl_init($baseUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    if (isset($testCase['invalid_json']) && $testCase['invalid_json']) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $testCase['data']);
    } else {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testCase['data']));
    }

    // 执行请求
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // 解析响应
    $responseData = null;
    if ($response) {
        $responseData = json_decode($response, true);
    }

    // 记录测试结果
    $testResults[$index] = [
        'name' => $testCase['name'],
        'request_data' => $testCase['data'],
        'http_code' => $httpCode,
        'response' => $responseData,
        'raw_response' => $response,
        'curl_error' => $error,
        'expected_status' => $testCase['expected_status'],
        'expected_error' => $testCase['expected_error'],
        'passed' => false
    ];

    // 验证结果
    $statusMatch = $httpCode == $testCase['expected_status'];
    $errorMatch = true;

    if ($testCase['expected_error'] !== null) {
        $errorMatch = isset($responseData['message']) &&
                      strpos($responseData['message'], $testCase['expected_error']) !== false;
    }

    $testResults[$index]['passed'] = $statusMatch && $errorMatch;

    if ($testResults[$index]['passed']) {
        $passedTests++;
        echo "<p style='color: green;'>✅ 通过</p>\n";
    } else {
        echo "<p style='color: red;'>❌ 失败</p>\n";
    }

    // 显示详细信息
    echo "<div style='margin-left: 20px; border: 1px solid #ccc; padding: 10px;'>\n";
    echo "<strong>请求数据:</strong><pre>" . json_encode($testCase['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>\n";
    echo "<strong>HTTP状态码:</strong> {$httpCode} (期望: {$testCase['expected_status']})<br>\n";
    echo "<strong>响应数据:</strong><pre>" . json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>\n";
    if ($error) {
        echo "<strong>CURL错误:</strong> {$error}<br>\n";
    }
    echo "</div>\n";

    echo "<hr>\n";
}

// 生成测试报告
echo "<h2>测试总结</h2>\n";
echo "<p>总测试数: {$testCount}</p>\n";
echo "<p>通过测试: {$passedTests}</p>\n";
echo "<p>失败测试: " . ($testCount - $passedTests) . "</p>\n";
echo "<p>通过率: " . round(($passedTests / $testCount) * 100, 2) . "%</p>\n";

// 保存详细测试结果
$report = [
    'test_summary' => [
        'total_tests' => $testCount,
        'passed_tests' => $passedTests,
        'failed_tests' => $testCount - $passedTests,
        'pass_rate' => round(($passedTests / $testCount) * 100, 2),
        'timestamp' => date('Y-m-d H:i:s')
    ],
    'test_results' => $testResults
];

$reportFile = __DIR__ . '/wechat_sync_comprehensive_test_report.json';
file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "<h2>详细测试报告</h2>\n";
echo "<p>详细测试报告已保存到: <code>{$reportFile}</code></p>\n";

// 显示失败的测试
$failedTests = array_filter($testResults, function($result) {
    return !$result['passed'];
});

if (!empty($failedTests)) {
    echo "<h3 style='color: red;'>失败的测试详情</h3>\n";
    foreach ($failedTests as $index => $result) {
        echo "<div style='margin-bottom: 20px; border: 1px solid red; padding: 10px;'>\n";
        echo "<strong>测试名称:</strong> {$result['name']}<br>\n";
        echo "<strong>期望状态码:</strong> {$result['expected_status']}<br>\n";
        echo "<strong>实际状态码:</strong> {$result['http_code']}<br>\n";
        if ($result['expected_error']) {
            echo "<strong>期望错误:</strong> {$result['expected_error']}<br>\n";
        }
        if (isset($result['response']['message'])) {
            echo "<strong>实际错误:</strong> {$result['response']['message']}<br>\n";
        }
        echo "</div>\n";
    }
}

// 检查服务器日志
echo "<h3>服务器日志检查</h3>\n";
$logFile = __DIR__ . '/../server.log';
if (file_exists($logFile)) {
    $logs = file_get_contents($logFile);
    $recentLogs = substr($logs, -2000); // 获取最后2000字符
    echo "<div style='background: #f5f5f5; padding: 10px; font-family: monospace; white-space: pre-wrap;'>\n";
    echo htmlspecialchars($recentLogs);
    echo "</div>\n";
} else {
    echo "<p>未找到服务器日志文件: {$logFile}</p>\n";
}

echo "<h3>测试完成</h3>\n";
echo "<p>所有测试已完成。请查看上述结果和详细报告。</p>\n";
?>
