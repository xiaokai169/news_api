<?php

/**
 * 新闻API修复后综合测试脚本
 * 测试修复后的新闻列表API，确保 'Unknown column s0_.update_at' 错误已经解决
 */

echo "🚀 开始新闻API修复后综合测试...\n\n";

// 测试结果记录
$testResults = [];
$startTime = microtime(true);

// 1. 清理缓存
echo "📋 步骤 1: 清理缓存\n";
echo str_repeat("-", 50) . "\n";

$cacheDirs = ['var/cache', 'var/log'];
$cacheCleared = true;

foreach ($cacheDirs as $dir) {
    if (is_dir($dir)) {
        echo "清理目录: $dir\n";
        try {
            recursiveDelete($dir);
            echo "✅ 已清理: $dir\n";
        } catch (Exception $e) {
            echo "❌ 清理失败: $dir - " . $e->getMessage() . "\n";
            $cacheCleared = false;
        }
    } else {
        echo "⚠️  目录不存在: $dir\n";
    }
}

// 重新创建缓存目录
$dirsToCreate = ['var/cache', 'var/cache/dev', 'var/cache/prod', 'var/log'];
foreach ($dirsToCreate as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "✅ 重新创建: $dir\n";
    }
}

$testResults['cache_clear'] = [
    'status' => $cacheCleared ? 'PASS' : 'FAIL',
    'message' => $cacheCleared ? '缓存清理成功' : '缓存清理失败'
];

echo "\n";

// 2. 验证数据库架构
echo "📋 步骤 2: 验证数据库架构\n";
echo str_repeat("-", 50) . "\n";

$dbConnection = null;
$schemaValid = false;

try {
    // 读取数据库配置
    if (file_exists('.env')) {
        $envContent = file_get_contents('.env');
        preg_match('/DATABASE_URL="(.+)"/', $envContent, $matches);

        if (isset($matches[1])) {
            $dbUrl = $matches[1];
            echo "找到数据库配置: " . substr($dbUrl, 0, 50) . "...\n";

            // 解析数据库连接信息
            $parsed = parse_url($dbUrl);
            $host = $parsed['host'] ?? 'localhost';
            $dbname = substr($parsed['path'], 1);

            echo "连接到数据库: $dbname@$host\n";

            // 连接数据库
            $dbConnection = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $parsed['user'] ?? 'root',
                $parsed['pass'] ?? ''
            );
            $dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            echo "✅ 数据库连接成功\n";

            // 检查 sys_news_article 表结构
            $stmt = $dbConnection->prepare("DESCRIBE sys_news_article");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "\n📋 sys_news_article 表结构:\n";
            $hasUpdateAt = false;
            $hasUpdatedAt = false;

            foreach ($columns as $column) {
                echo "- {$column['Field']} ({$column['Type']})\n";

                if ($column['Field'] === 'update_at') {
                    $hasUpdateAt = true;
                }
                if ($column['Field'] === 'updated_at') {
                    $hasUpdatedAt = true;
                }
            }

            echo "\n🔍 字段检查结果:\n";
            if ($hasUpdateAt) {
                echo "⚠️  发现 update_at 字段（Entity映射正确）\n";
            } else {
                echo "❌ 未发现 update_at 字段\n";
            }

            if ($hasUpdatedAt) {
                echo "⚠️  发现 updated_at 字段（可能冲突）\n";
            } else {
                echo "✅ 未发现 updated_at 字段\n";
            }

            $schemaValid = $hasUpdateAt && !$hasUpdatedAt;

        } else {
            echo "❌ 无法解析数据库配置\n";
        }
    } else {
        echo "❌ 未找到 .env 文件\n";
    }

} catch (Exception $e) {
    echo "❌ 数据库验证失败: " . $e->getMessage() . "\n";
}

$testResults['schema_validate'] = [
    'status' => $schemaValid ? 'PASS' : 'FAIL',
    'message' => $schemaValid ? '数据库架构正确' : '数据库架构有问题'
];

echo "\n";

// 3. 测试新闻API
echo "📋 步骤 3: 测试新闻API\n";
echo str_repeat("-", 50) . "\n";

$apiTests = [];

// 基础URL（假设本地开发环境）
$baseUrl = 'http://localhost:8000/api';
$apiTests['basic_list'] = testApiEndpoint($baseUrl . '/news', '基本列表查询');
$apiTests['pagination'] = testApiEndpoint($baseUrl . '/news?page=1&limit=10', '分页查询');
$apiTests['sort_by_update_at'] = testApiEndpoint($baseUrl . '/news?sort=update_at&order=desc', '按update_at排序');
$apiTests['filter_by_status'] = testApiEndpoint($baseUrl . '/news?status=1', '状态过滤');

foreach ($apiTests as $testName => $result) {
    echo "\n📊 {$result['description']}:\n";
    echo "状态: {$result['status']}\n";
    echo "响应码: {$result['http_code']}\n";
    echo "响应时间: {$result['response_time']}ms\n";

    if ($result['status'] === 'FAIL') {
        echo "错误: " . $result['error'] . "\n";
        if (isset($result['response'])) {
            echo "响应内容: " . substr($result['response'], 0, 200) . "...\n";
        }
    } else {
        echo "✅ 请求成功\n";
        // 检查响应格式
        if (isset($result['response'])) {
            $response = json_decode($result['response'], true);
            if ($response && isset($response['data'])) {
                echo "数据格式: 正确（包含data字段）\n";
                if (isset($response['pagination'])) {
                    echo "分页信息: 正确（包含pagination字段）\n";
                }
            } else {
                echo "⚠️  数据格式可能有问题\n";
            }
        }
    }

    $testResults[$testName] = [
        'status' => $result['status'],
        'message' => $result['status'] === 'PASS' ? '测试通过' : '测试失败: ' . $result['error'],
        'http_code' => $result['http_code'],
        'response_time' => $result['response_time']
    ];
}

echo "\n";

// 4. 生成测试报告
echo "📋 步骤 4: 生成测试报告\n";
echo str_repeat("-", 50) . "\n";

$endTime = microtime(true);
$totalTime = round(($endTime - $startTime) * 1000, 2);

$passCount = 0;
$failCount = 0;

foreach ($testResults as $testName => $result) {
    if ($result['status'] === 'PASS') {
        $passCount++;
    } else {
        $failCount++;
    }
}

echo "🎯 测试总结:\n";
echo "总测试数: " . count($testResults) . "\n";
echo "通过: $passCount\n";
echo "失败: $failCount\n";
echo "总耗时: {$totalTime}ms\n";

echo "\n📊 详细结果:\n";
foreach ($testResults as $testName => $result) {
    $statusIcon = $result['status'] === 'PASS' ? '✅' : '❌';
    echo "$statusIcon $testName: {$result['message']}\n";
}

// 保存测试报告到JSON文件
$report = [
    'timestamp' => date('Y-m-d H:i:s'),
    'total_time_ms' => $totalTime,
    'summary' => [
        'total' => count($testResults),
        'passed' => $passCount,
        'failed' => $failCount
    ],
    'results' => $testResults
];

file_put_contents('news_api_fix_test_report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\n📄 详细报告已保存到: news_api_fix_test_report.json\n";

// 检查关键错误
$hasColumnError = false;
foreach ($apiTests as $testName => $result) {
    if ($result['status'] === 'FAIL' &&
        (strpos($result['error'], 'Unknown column') !== false ||
         strpos($result['error'], 'update_at') !== false)) {
        $hasColumnError = true;
        break;
    }
}

if ($hasColumnError) {
    echo "\n❌ 关键发现: 仍然存在 'Unknown column update_at' 错误！\n";
    echo "建议: 检查Entity映射是否正确修改\n";
} else {
    echo "\n✅ 关键发现: 'Unknown column update_at' 错误已解决！\n";
}

echo "\n🎉 测试完成！\n";

// 辅助函数
function recursiveDelete($dir) {
    if (!is_dir($dir)) {
        return;
    }

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            recursiveDelete($path);
            rmdir($path);
        } else {
            unlink($path);
        }
    }
}

function testApiEndpoint($url, $description = '') {
    $startTime = microtime(true);

    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $endTime = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000, 2);

        if ($error) {
            return [
                'status' => 'FAIL',
                'error' => $error,
                'http_code' => 0,
                'response_time' => $responseTime,
                'description' => $description
            ];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'status' => 'PASS',
                'response' => $response,
                'http_code' => $httpCode,
                'response_time' => $responseTime,
                'description' => $description
            ];
        } else {
            return [
                'status' => 'FAIL',
                'error' => "HTTP $httpCode",
                'http_code' => $httpCode,
                'response_time' => $responseTime,
                'response' => $response,
                'description' => $description
            ];
        }

    } catch (Exception $e) {
        $endTime = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000, 2);

        return [
            'status' => 'FAIL',
            'error' => $e->getMessage(),
            'http_code' => 0,
            'response_time' => $responseTime,
            'description' => $description
        ];
    }
}
