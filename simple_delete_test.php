<?php

echo "🚀 简化DELETE API测试开始...\n\n";

// 测试配置
$baseUrl = 'https://127.0.0.1:8000';
$testId = 11;
$deleteUrl = $baseUrl . '/official-api/news/' . $testId;

echo "测试URL: $deleteUrl\n";
echo str_repeat("-", 50) . "\n";

// 执行DELETE请求
try {
    $startTime = microtime(true);

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
    $endTime = microtime(true);
    $responseTime = round(($endTime - $startTime) * 1000, 2);

    curl_close($ch);

    echo "DELETE请求完成\n";
    echo "状态码: $httpCode\n";
    echo "响应时间: {$responseTime}ms\n";

    if ($error) {
        echo "❌ 请求失败: $error\n";
    } else {
        echo "✅ 请求发送成功\n";
        echo "响应内容: " . substr($response, 0, 500) . "...\n\n";

        // 检查关键错误
        $hasColumnError = strpos($response, 'Unknown column') !== false;
        $hasUpdateTimeError = strpos($response, 'update_time') !== false;
        $hasUpdateAtError = strpos($response, 'update_at') !== false && strpos($response, 'Unknown column') !== false;

        echo "🔍 错误检查:\n";
        echo "- Unknown column错误: " . ($hasColumnError ? '是' : '否') . "\n";
        echo "- update_time错误: " . ($hasUpdateTimeError ? '是' : '否') . "\n";
        echo "- update_at错误: " . ($hasUpdateAtError ? '是' : '否') . "\n\n";

        if ($hasColumnError && $hasUpdateTimeError) {
            echo "❌ 关键发现：仍然存在 'Unknown column update_time' 错误！\n";
            echo "状态：修复失败\n";
        } elseif ($hasColumnError && $hasUpdateAtError) {
            echo "❌ 关键发现：存在 'Unknown column update_at' 错误！\n";
            echo "状态：数据库表结构问题\n";
        } elseif ($httpCode >= 200 && $httpCode < 300) {
            echo "✅ DELETE请求成功执行！\n";
            echo "状态：修复成功\n";

            $responseData = json_decode($response, true);
            if ($responseData) {
                echo "✅ JSON响应格式正确\n";
                if (isset($responseData['data'])) {
                    echo "✅ 响应包含data字段\n";
                }
            }
        } elseif ($httpCode === 404) {
            echo "⚠️  文章不存在或已删除（404）\n";
            echo "状态：正常处理\n";
        } elseif ($httpCode === 500) {
            echo "❌ 服务器内部错误（500）\n";
            echo "错误详情: " . substr($response, 0, 300) . "...\n";
            echo "状态：需要进一步检查\n";
        } else {
            echo "⚠️  意外的状态码: $httpCode\n";
            echo "状态：不确定\n";
        }
    }

} catch (Exception $e) {
    echo "❌ 异常: " . $e->getMessage() . "\n";
}

echo "\n🎉 测试完成！\n";
