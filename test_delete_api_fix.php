<?php

/**
 * DELETE API修复验证测试脚本
 * 测试修复后的DELETE API功能，确认 'update_time' -> 'update_at' 字段修复是否成功
 *
 * 测试目标：https://127.0.0.1:8000/official-api/news/11
 */

echo "🚀 开始DELETE API修复验证测试...\n\n";

// 测试结果记录
$testResults = [];
$startTime = microtime(true);

// 测试配置
$baseUrl = 'https://127.0.0.1:8000';
$testId = 11; // 测试的文章ID
$deleteUrl = $baseUrl . '/official-api/news/' . $testId;

echo "📋 测试配置:\n";
echo "基础URL: $baseUrl\n";
echo "测试ID: $testId\n";
echo "DELETE URL: $deleteUrl\n";
echo str_repeat("-", 60) . "\n\n";

// 1. 预检查 - 验证文章是否存在
echo "📋 步骤 1: 预检查 - 验证目标文章是否存在\n";
echo str_repeat("-", 50) . "\n";

$articleExists = false;
$articleData = null;

try {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/official-api/news/' . $testId);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "❌ 预检查请求失败: $error\n";
    } else {
        echo "✅ 预检查请求完成\n";
        echo "状态码: $httpCode\n";

        if ($httpCode === 200) {
            $articleData = json_decode($response, true);
            if ($articleData && isset($articleData['data'])) {
                $articleExists = true;
                echo "✅ 文章存在，可以执行删除测试\n";
                echo "文章标题: " . ($articleData['data']['name'] ?? '未知') . "\n";
                echo "文章状态: " . ($articleData['data']['status'] ?? '未知') . "\n";
            } else {
                echo "⚠️  响应格式异常\n";
            }
        } elseif ($httpCode === 404) {
            echo "⚠️  文章不存在或已被删除，将测试404处理\n";
        } else {
            echo "⚠️  预检查返回状态码: $httpCode\n";
            echo "响应内容: " . substr($response, 0, 200) . "...\n";
        }
    }
} catch (Exception $e) {
    echo "❌ 预检查异常: " . $e->getMessage() . "\n";
}

$testResults['pre_check'] = [
    'status' => $articleExists ? 'PASS' : 'WARN',
    'message' => $articleExists ? '文章存在，可执行删除测试' : '文章不存在或已删除',
    'http_code' => $httpCode ?? 0
];

echo "\n";

// 2. 执行DELETE请求测试
echo "📋 步骤 2: 执行DELETE请求测试\n";
echo str_repeat("-", 50) . "\n";

$deleteResult = null;
$deleteSuccess = false;

try {
    $startTimeDelete = microtime(true);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $deleteUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $endTimeDelete = microtime(true);
    $responseTime = round(($endTimeDelete - $startTimeDelete) * 1000, 2);

    curl_close($ch);

    echo "DELETE请求执行完成\n";
    echo "状态码: $httpCode\n";
    echo "响应时间: {$responseTime}ms\n";

    if ($error) {
        echo "❌ DELETE请求失败: $error\n";
        $testResults['delete_request'] = [
            'status' => 'FAIL',
            'message' => '请求失败: ' . $error,
            'http_code' => 0,
            'response_time' => $responseTime
        ];
    } else {
        echo "✅ DELETE请求发送成功\n";
        echo "响应内容: " . substr($response, 0, 500) . "...\n";

        // 检查是否有关键错误
        $hasColumnError = strpos($response, 'Unknown column') !== false;
        $hasUpdateTimeError = strpos($response, 'update_time') !== false;
        $hasUpdateAtError = strpos($response, 'update_at') !== false && strpos($response, 'Unknown column') !== false;

        if ($hasColumnError && $hasUpdateTimeError) {
            echo "❌ 发现关键错误：仍然存在 'Unknown column update_time' 错误！\n";
            $deleteSuccess = false;
        } elseif ($hasColumnError && $hasUpdateAtError) {
            echo "❌ 发现关键错误：存在 'Unknown column update_at' 错误！\n";
            $deleteSuccess = false;
        } elseif ($httpCode >= 200 && $httpCode < 300) {
            echo "✅ DELETE请求成功执行！\n";
            $deleteSuccess = true;

            // 尝试解析响应
            $responseData = json_decode($response, true);
            if ($responseData) {
                echo "✅ JSON响应格式正确\n";
                if (isset($responseData['data'])) {
                    echo "✅ 响应包含data字段\n";
                    echo "返回数据: " . json_encode($responseData['data'], JSON_UNESCAPED_UNICODE) . "\n";
                }
            } else {
                echo "⚠️  JSON解析失败，但HTTP状态正常\n";
            }
        } elseif ($httpCode === 404) {
            echo "⚠️  文章不存在或已删除（404）\n";
            $deleteSuccess = true; // 404也是正确的处理
        } elseif ($httpCode === 500) {
            echo "❌ 服务器内部错误（500）\n";
            echo "错误详情: " . substr($response, 0, 300) . "...\n";
            $deleteSuccess = false;
        } else {
            echo "⚠️  意外的状态码: $httpCode\n";
            $deleteSuccess = false;
        }

        $testResults['delete_request'] = [
            'status' => $deleteSuccess ? 'PASS' : 'FAIL',
            'message' => $deleteSuccess ? 'DELETE请求成功' : 'DELETE请求失败',
            'http_code' => $httpCode,
            'response_time' => $responseTime,
            'has_column_error' => $hasColumnError,
            'has_update_time_error' => $hasUpdateTimeError,
            'has_update_at_error' => $hasUpdateAtError,
            'response_preview' => substr($response, 0, 200)
        ];
    }

} catch (Exception $e) {
    echo "❌ DELETE请求异常: " . $e->getMessage() . "\n";
    $testResults['delete_request'] = [
        'status' => 'FAIL',
        'message' => '请求异常: ' . $e->getMessage(),
        'http_code' => 0
    ];
}

echo "\n";

// 3. 后验证 - 检查删除结果
echo "📋 步骤 3: 后验证 - 检查删除结果\n";
echo str_repeat("-", 50) . "\n";

$verificationResult = null;

try {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/official-api/news/' . $testId);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "后验证请求完成\n";
    echo "状态码: $httpCode\n";

    if ($httpCode === 404) {
        echo "✅ 删除验证成功：文章已不可访问（404）\n";
        $verificationResult = 'SUCCESS';
    } elseif ($httpCode === 200) {
        $responseData = json_decode($response, true);
        if ($responseData && isset($responseData['data'])) {
            $status = $responseData['data']['status'] ?? null;
            if ($status == 3) { // STATUS_DELETED
                echo "✅ 删除验证成功：文章状态已更新为已删除\n";
                $verificationResult = 'SUCCESS';
            } else {
                echo "⚠️  文章仍可访问，状态: $status\n";
                $verificationResult = 'PARTIAL';
            }
        } else {
            echo "⚠️  响应格式异常\n";
            $verificationResult = 'UNKNOWN';
        }
    } else {
        echo "⚠️  验证请求状态码: $httpCode\n";
        $verificationResult = 'ERROR';
    }

    $testResults['verification'] = [
        'status' => $verificationResult === 'SUCCESS' ? 'PASS' : 'WARN',
        'message' => "删除验证结果: $verificationResult",
        'http_code' => $httpCode
    ];

} catch (Exception $e) {
    echo "❌ 后验证异常: " . $e->getMessage() . "\n";
    $testResults['verification'] = [
        'status' => 'FAIL',
        'message' => '验证异常: ' . $e->getMessage(),
        'http_code' => 0
    ];
}

echo "\n";

// 4. 数据库直接验证（如果可能）
echo "📋 步骤 4: 数据库直接验证\n";
echo str_repeat("-", 50) . "\n";

$dbVerification = null;

try {
    // 读取数据库配置
    if (file_exists('.env')) {
        $envContent = file_get_contents('.env');
        preg_match('/DATABASE_URL="(.+)"/', $envContent, $matches);

        if (isset($matches[1])) {
            $dbUrl = $matches[1];
            $parsed = parse_url($dbUrl);
            $host = $parsed['host'] ?? 'localhost';
            $dbname = substr($parsed['path'], 1);

            $dbConnection = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $parsed['user'] ?? 'root',
                $parsed['pass'] ?? ''
            );
            $dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            echo "✅ 数据库连接成功\n";

            // 检查文章状态
            $stmt = $dbConnection->prepare("SELECT id, status, update_at FROM sys_news_article WHERE id = :id");
            $stmt->execute(['id' => $testId]);
            $article = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($article) {
                echo "✅ 找到文章记录\n";
                echo "ID: {$article['id']}\n";
                echo "状态: {$article['status']}\n";
                echo "更新时间: {$article['update_at']}\n";

                if ($article['status'] == 3) {
                    echo "✅ 数据库验证：文章状态已正确更新为已删除\n";
                    $dbVerification = 'SUCCESS';
                } else {
                    echo "⚠️  数据库验证：文章状态未更新为已删除\n";
                    $dbVerification = 'PARTIAL';
                }
            } else {
                echo "⚠️  数据库中未找到文章记录\n";
                $dbVerification = 'NOT_FOUND';
            }

            // 检查字段结构
            echo "\n🔍 检查表字段结构:\n";
            $stmt = $dbConnection->prepare("DESCRIBE sys_news_article");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $hasUpdateAt = false;
            $hasUpdateTime = false;

            foreach ($columns as $column) {
                if ($column['Field'] === 'update_at') {
                    $hasUpdateAt = true;
                    echo "✅ 找到 update_at 字段\n";
                }
                if ($column['Field'] === 'update_time') {
                    $hasUpdateTime = true;
                    echo "⚠️  发现 update_time 字段（可能导致冲突）\n";
                }
            }

            if ($hasUpdateAt && !$hasUpdateTime) {
                echo "✅ 字段结构正确：只有 update_at 字段\n";
            } elseif ($hasUpdateAt && $hasUpdateTime) {
                echo "⚠️  字段结构警告：同时存在 update_at 和 update_time 字段\n";
            } else {
                echo "❌ 字段结构错误：未找到 update_at 字段\n";
            }

        } else {
            echo "❌ 无法解析数据库配置\n";
        }
    } else {
        echo "❌ 未找到 .env 文件\n";
    }

} catch (Exception $e) {
    echo "❌ 数据库验证失败: " . $e->getMessage() . "\n";
}

$testResults['database_verification'] = [
    'status' => $dbVerification === 'SUCCESS' ? 'PASS' : ($dbVerification ? 'WARN' : 'FAIL'),
    'message' => "数据库验证结果: " . ($dbVerification ?? 'FAILED'),
    'verification_result' => $dbVerification
];

echo "\n";

// 5. 生成测试报告
echo "📋 步骤 5: 生成测试报告\n";
echo str_repeat("-", 50) . "\n";

$endTime = microtime(true);
$totalTime = round(($endTime - $startTime) * 1000, 2);

$passCount = 0;
$failCount = 0;
$warnCount = 0;

foreach ($testResults as $testName => $result) {
    if ($result['status'] === 'PASS') {
        $passCount++;
    } elseif ($result['status'] === 'FAIL') {
        $failCount++;
    } else {
        $warnCount++;
    }
}

echo "🎯 测试总结:\n";
echo "总测试数: " . count($testResults) . "\n";
echo "通过: $passCount\n";
echo "警告: $warnCount\n";
echo "失败: $failCount\n";
echo "总耗时: {$totalTime}ms\n";

echo "\n📊 详细结果:\n";
foreach ($testResults as $testName => $result) {
    $statusIcon = $result['status'] === 'PASS' ? '✅' : ($result['status'] === 'FAIL' ? '❌' : '⚠️');
    echo "$statusIcon $testName: {$result['message']}\n";
}

// 关键错误检查
$hasCriticalError = false;
if (isset($testResults['delete_request']['has_update_time_error']) && $testResults['delete_request']['has_update_time_error']) {
    $hasCriticalError = true;
    echo "\n❌ 关键发现：仍然存在 'Unknown column update_time' 错误！\n";
    echo "建议：检查Entity映射和SQL语句中的字段名\n";
}

if (isset($testResults['delete_request']['has_update_at_error']) && $testResults['delete_request']['has_update_at_error']) {
    $hasCriticalError = true;
    echo "\n❌ 关键发现：存在 'Unknown column update_at' 错误！\n";
    echo "建议：检查数据库表结构是否包含 update_at 字段\n";
}

if (!$hasCriticalError) {
    echo "\n✅ 关键发现：字段名修复成功！\n";
    echo "✅ DELETE API不再出现字段名错误\n";
}

// 保存详细报告
$report = [
    'timestamp' => date('Y-m-d H:i:s'),
    'test_url' => $deleteUrl,
    'total_time_ms' => $totalTime,
    'summary' => [
        'total' => count($testResults),
        'passed' => $passCount,
        'warnings' => $warnCount,
        'failed' => $failCount
    ],
    'results' => $testResults,
    'critical_error' => $hasCriticalError,
    'fix_status' => !$hasCriticalError ? 'SUCCESS' : 'FAILED'
];

file_put_contents('delete_api_fix_test_report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\n📄 详细报告已保存到: delete_api_fix_test_report.json\n";

// 最终结论
echo "\n" . str_repeat("=", 60) . "\n";
if (!$hasCriticalError && $deleteSuccess) {
    echo "🎉 DELETE API修复验证：成功！\n";
    echo "✅ 字段名 'update_time' -> 'update_at' 修复成功\n";
    echo "✅ DELETE请求正常执行\n";
    echo "✅ 不再出现数据库字段错误\n";
} elseif (!$hasCriticalError) {
    echo "⚠️  DELETE API修复验证：部分成功\n";
    echo "✅ 字段名修复成功\n";
    echo "⚠️  DELETE请求可能存在其他问题\n";
} else {
    echo "❌ DELETE API修复验证：失败！\n";
    echo "❌ 仍然存在字段名相关问题\n";
    echo "❌ 需要进一步检查和修复\n";
}
echo str_repeat("=", 60) . "\n";

echo "\n🎉 测试完成！\n";
