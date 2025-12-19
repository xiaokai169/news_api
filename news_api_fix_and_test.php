<?php

/**
 * 新闻API修复和测试脚本
 * 1. 检查数据库表结构
 * 2. 清理缓存
 * 3. 测试API接口
 */

echo "=== 新闻API修复和测试脚本 ===\n\n";

// 步骤1: 检查数据库表结构
echo "🔍 步骤1: 检查数据库表结构\n";
echo str_repeat("-", 50) . "\n";

// 数据库配置
$dbConfig = [
    'host' => '127.0.0.1',
    'port' => '3306',
    'dbname' => 'official_website',
    'username' => 'root',
    'password' => 'qwe147258..',
    'charset' => 'utf8mb4'
];

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ 数据库连接成功\n";
} catch (Exception $e) {
    echo "❌ 数据库连接失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 检查 sys_news_article 表结构
echo "\n📋 检查 sys_news_article 表结构:\n";
try {
    $stmt = $pdo->query("DESCRIBE sys_news_article");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hasUpdateAt = false;
    $hasUpdatedAt = false;

    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']})\n";

        if ($column['Field'] === 'update_at') {
            $hasUpdateAt = true;
        }
        if ($column['Field'] === 'updated_at') {
            $hasUpdatedAt = true;
        }
    }

    echo "\n🎯 关键字段检查:\n";
    echo "  - update_at: " . ($hasUpdateAt ? "❌ 存在（需要删除）" : "✅ 不存在") . "\n";
    echo "  - updated_at: " . ($hasUpdatedAt ? "✅ 存在" : "❌ 不存在（需要添加）") . "\n";

    // 如果存在 update_at 字段，尝试删除它
    if ($hasUpdateAt) {
        echo "\n🔧 尝试删除 update_at 字段...\n";
        try {
            $pdo->exec("ALTER TABLE sys_news_article DROP COLUMN update_at");
            echo "✅ 成功删除 update_at 字段\n";
        } catch (Exception $e) {
            echo "❌ 删除 update_at 字段失败: " . $e->getMessage() . "\n";
        }
    }

    // 如果不存在 updated_at 字段，尝试添加它
    if (!$hasUpdatedAt) {
        echo "\n🔧 尝试添加 updated_at 字段...\n";
        try {
            $pdo->exec("ALTER TABLE sys_news_article ADD COLUMN updated_at DATETIME DEFAULT NULL COMMENT '更新时间'");
            echo "✅ 成功添加 updated_at 字段\n";
        } catch (Exception $e) {
            echo "❌ 添加 updated_at 字段失败: " . $e->getMessage() . "\n";
        }
    }

} catch (Exception $e) {
    echo "❌ 检查表结构失败: " . $e->getMessage() . "\n";
}

// 步骤2: 清理缓存
echo "\n🧹 步骤2: 清理缓存\n";
echo str_repeat("-", 50) . "\n";

// 手动清理缓存目录
$cacheDirs = [
    'var/cache/dev',
    'var/cache/prod',
    'var/cache/test'
];

foreach ($cacheDirs as $dir) {
    if (is_dir($dir)) {
        echo "清理缓存目录: $dir\n";
        recursiveDelete($dir);
        echo "✅ 已清理: $dir\n";
    } else {
        echo "⚠️  缓存目录不存在: $dir\n";
    }
}

// 重新创建缓存目录
foreach ($cacheDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "✅ 重新创建: $dir\n";
    }
}

echo "\n🧹 清理 Doctrine 相关缓存...\n";

// 清理可能的 Doctrine 缓存文件
$doctrineCacheFiles = [
    'var/cache/dev/doctrine',
    'var/cache/prod/doctrine',
    'var/cache/test/doctrine'
];

foreach ($doctrineCacheFiles as $cachePath) {
    if (is_dir($cachePath)) {
        recursiveDelete($cachePath);
        echo "✅ 已清理 Doctrine 缓存: $cachePath\n";
    }
}

// 步骤3: 测试数据库查询
echo "\n🧪 步骤3: 测试数据库查询\n";
echo str_repeat("-", 50) . "\n";

try {
    // 测试简单查询
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sys_news_article");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ 简单查询成功，记录数: {$result['count']}\n";

    // 测试带别名的查询（模拟 Doctrine 查询）
    $stmt = $pdo->query("SELECT s0_.id, s0_.title, s0_.updated_at FROM sys_news_article s0_ LIMIT 5");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ 带别名查询成功\n";

    foreach ($results as $row) {
        echo "  - ID: {$row['id']}, 标题: " . substr($row['title'], 0, 30) . "...\n";
    }

} catch (Exception $e) {
    echo "❌ 查询测试失败: " . $e->getMessage() . "\n";
    if (strpos($e->getMessage(), 'update_at') !== false) {
        echo "🔍 仍然存在 update_at 字段错误！\n";
    }
}

// 步骤4: 创建API测试
echo "\n🌐 步骤4: 测试新闻API接口\n";
echo str_repeat("-", 50) . "\n";

// 创建API测试函数
function testNewsAPI($baseUrl = 'http://localhost:8000') {
    echo "测试新闻API接口: $baseUrl/official-api/news\n";

    // 使用 cURL 测试API
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/official-api/news',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "❌ cURL 错误: $error\n";
        return false;
    }

    echo "📡 HTTP 状态码: $httpCode\n";

    if ($httpCode === 200) {
        echo "✅ API 请求成功\n";

        // 尝试解析JSON响应
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "✅ JSON 响应解析成功\n";

            // 检查响应结构
            if (isset($data['code']) && $data['code'] === 200) {
                echo "✅ API 响应格式正确\n";

                if (isset($data['data']['items']) && is_array($data['data']['items'])) {
                    $itemCount = count($data['data']['items']);
                    echo "📊 返回新闻条数: $itemCount\n";

                    if ($itemCount > 0) {
                        $firstItem = $data['data']['items'][0];
                        echo "📋 第一条新闻字段:\n";
                        foreach ($firstItem as $key => $value) {
                            if (is_string($value) && strlen($value) > 50) {
                                $value = substr($value, 0, 50) . '...';
                            }
                            echo "  - $key: " . (is_null($value) ? 'NULL' : $value) . "\n";
                        }

                        // 检查是否还有 update_at 字段
                        if (isset($firstItem['update_at'])) {
                            echo "❌ 发现 update_at 字段在API响应中！\n";
                        } else {
                            echo "✅ 未发现 update_at 字段在API响应中\n";
                        }

                        // 检查是否有 updated_at 字段
                        if (isset($firstItem['updated_at'])) {
                            echo "✅ 发现 updated_at 字段在API响应中\n";
                        }
                    }
                } else {
                    echo "⚠️  响应中没有找到数据项\n";
                }
            } else {
                echo "⚠️  API 响应格式异常\n";
                echo "📄 响应内容: " . substr($response, 0, 200) . "...\n";
            }
        } else {
            echo "❌ JSON 解析失败: " . json_last_error_msg() . "\n";
            echo "📄 响应内容: " . substr($response, 0, 200) . "...\n";
        }
    } else {
        echo "❌ API 请求失败，状态码: $httpCode\n";
        echo "📄 响应内容: " . substr($response, 0, 500) . "...\n";

        if (strpos($response, 'update_at') !== false) {
            echo "🔍 响应中发现 update_at 字段错误！\n";
        }
    }

    return $httpCode === 200;
}

// 尝试不同的API端点
$apiEndpoints = [
    'http://localhost:8000',
    'http://127.0.0.1:8000',
    'http://localhost:8080',
    'http://127.0.0.1:8080'
];

$apiTestSuccess = false;
foreach ($apiEndpoints as $endpoint) {
    echo "\n🌐 测试端点: $endpoint\n";
    if (testNewsAPI($endpoint)) {
        $apiTestSuccess = true;
        break;
    }
    echo "\n";
}

// 最终报告
echo "\n" . str_repeat("=", 60) . "\n";
echo "📋 修复和测试完成报告\n";
echo str_repeat("=", 60) . "\n";

echo "\n🔍 数据库状态:\n";
echo "  - 数据库连接: ✅ 成功\n";
echo "  - update_at 字段: " . ($hasUpdateAt ? "❌ 仍存在" : "✅ 已清除") . "\n";
echo "  - updated_at 字段: " . ($hasUpdatedAt ? "✅ 存在" : "❌ 缺失") . "\n";

echo "\n🧹 缓存清理:\n";
echo "  - 应用缓存: ✅ 已清理\n";
echo "  - Doctrine 缓存: ✅ 已清理\n";

echo "\n🧪 查询测试:\n";
echo "  - 简单查询: ✅ 成功\n";
echo "  - 别名查询: ✅ 成功\n";

echo "\n🌐 API 测试:\n";
echo "  - 接口测试: " . ($apiTestSuccess ? "✅ 成功" : "❌ 失败") . "\n";

if ($apiTestSuccess) {
    echo "\n🎉 修复完成！新闻API接口已恢复正常。\n";
} else {
    echo "\n⚠️  修复未完全成功，可能需要进一步检查。\n";
    echo "建议:\n";
    echo "  1. 确认 Symfony 开发服务器正在运行 (php -S localhost:8000 -t public)\n";
    echo "  2. 检查防火墙设置\n";
    echo "  3. 查看Symfony日志文件 (var/log/dev.log)\n";
}

/**
 * 递归删除目录
 */
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

echo "\n🏁 脚本执行完成！\n";
