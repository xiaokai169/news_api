<?php

/**
 * 使用有效参数测试微信同步API端点
 * 验证修复后的分布式锁功能
 */

echo "=== 使用有效参数测试微信同步API ===\n\n";

// 测试配置
$apiUrl = 'https://newsapi.arab-bee.com/official-api/wechat/sync';
$testTimeout = 30; // 30秒超时

// 构建有效的测试数据
$testData = [
    'accountId' => 'test_account_123',
    'syncType' => 'articles',
    'syncScope' => 'recent',
    'articleLimit' => 5,
    'forceSync' => false,
    'async' => false, // 同步执行以便观察结果
    'autoHandleDuplicates' => true,
    'duplicateAction' => 'skip',
    'customOptions' => [
        'name' => '测试公众号',
        'appId' => 'test_app_id',
        'appSecret' => 'test_app_secret'
    ]
];

echo "测试数据:\n";
echo json_encode($testData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// 执行API测试
echo "1. 执行API请求...\n";
$result = testWechatSyncApi($apiUrl, $testData, $testTimeout);

echo "\n2. 检查API响应...\n";
analyzeApiResponse($result);

echo "\n3. 检查分布式锁状态...\n";
checkDistributedLockStatus($testData['accountId']);

echo "\n4. 检查日志文件...\n";
checkLogFiles();

echo "\n=== 测试完成 ===\n";

/**
 * 测试微信同步API
 */
function testWechatSyncApi(string $url, array $data, int $timeout): array
{
    $result = [
        'success' => false,
        'status_code' => null,
        'response_time' => null,
        'response_body' => null,
        'error' => null,
        'has_distributed_lock_error' => false
    ];

    try {
        $startTime = microtime(true);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: WeChatSyncTest/1.0'
            ],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        $result['status_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $result['response_time'] = round((microtime(true) - $startTime) * 1000, 2);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $result['error'] = $error;
        } else {
            $result['response_body'] = $response;
            $result['has_distributed_lock_error'] = strpos($response, 'distributed_lock') !== false &&
                                                   strpos($response, 'doesn\'t exist') !== false;

            if ($result['status_code'] >= 200 && $result['status_code'] < 300) {
                $result['success'] = true;
            }
        }

    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }

    echo "   状态码: {$result['status_code']}\n";
    echo "   响应时间: {$result['response_time']}ms\n";
    echo "   请求成功: " . ($result['success'] ? '是' : '否') . "\n";
    echo "   分布式锁错误: " . ($result['has_distributed_lock_error'] ? '是' : '否') . "\n";
    if ($result['error']) {
        echo "   错误: {$result['error']}\n";
    }

    return $result;
}

/**
 * 分析API响应
 */
function analyzeApiResponse(array $result): void
{
    if (!$result['response_body']) {
        echo "   ❌ 无响应内容\n";
        return;
    }

    echo "   响应内容:\n";
    $response = json_decode($result['response_body'], true);

    if (json_last_error() === JSON_ERROR_NONE) {
        echo "   ✅ JSON格式正确\n";
        echo "   响应数据:\n";
        echo "   " . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

        // 检查响应状态
        if (isset($response['status'])) {
            if ($response['status'] === '200' || $response['status'] === 200) {
                echo "   ✅ API调用成功\n";
            } else {
                echo "   ⚠️ API返回错误状态: {$response['status']}\n";
            }
        }

        if (isset($response['message'])) {
            echo "   消息: {$response['message']}\n";
        }

        // 检查是否有分布式锁相关错误
        if ($result['has_distributed_lock_error']) {
            echo "   ❌ 仍然存在分布式锁错误\n";
        } else {
            echo "   ✅ 无分布式锁错误\n";
        }

    } else {
        echo "   ❌ JSON格式错误: " . json_last_error_msg() . "\n";
        echo "   原始响应: " . substr($result['response_body'], 0, 500) . "...\n";
    }
}

/**
 * 检查分布式锁状态
 */
function checkDistributedLockStatus(string $accountId): void
{
    echo "   检查账号 {$accountId} 的分布式锁状态...\n";

    $lockKey = 'wechat_sync_' . $accountId;
    echo "   锁键名: {$lockKey}\n";

    // 这里应该查询数据库中的分布式锁表
    // 由于我们在测试环境中，模拟检查
    try {
        $output = shell_exec("wsl -e bash -c 'mysql -u root -p123456 -e \"SELECT * FROM distributed_locks WHERE lock_key=\\\"{$lockKey}\\\";\" official_website 2>/dev/null'");
        if ($output && trim($output)) {
            echo "   📋 找到锁记录:\n";
            echo "   " . trim($output) . "\n";
        } else {
            echo "   ℹ️ 未找到活跃锁记录\n";
        }
    } catch (Exception $e) {
        echo "   ❌ 无法检查锁状态: {$e->getMessage()}\n";
    }
}

/**
 * 检查日志文件
 */
function checkLogFiles(): void
{
    $logFiles = [
        '../var/log/wechat.log' => '微信日志',
        '../var/log/error.log' => '错误日志',
        '../var/log/prod.log' => '生产日志'
    ];

    foreach ($logFiles as $filePath => $description) {
        echo "   检查{$description}...\n";

        if (file_exists($filePath)) {
            $size = filesize($filePath);
            $modified = date('Y-m-d H:i:s', filemtime($filePath));
            echo "     ✅ 文件存在，大小: {$size} 字节，修改时间: {$modified}\n";

            // 检查最近的日志条目
            if ($size > 0) {
                $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $recentLines = array_slice($lines, -5); // 最后5行
                echo "     最近日志条目:\n";
                foreach ($recentLines as $line) {
                    if (strpos($line, 'distributed_lock') !== false ||
                        strpos($line, 'wechat_sync') !== false) {
                        echo "     " . trim($line) . "\n";
                    }
                }
            }
        } else {
            echo "     ❌ 文件不存在\n";
        }
    }
}
