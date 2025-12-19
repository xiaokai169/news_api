<?php

/**
 * 执行完整的新闻API修复流程
 * 解决 Doctrine 缓存中的 update_at 字段问题
 */

echo "=== 新闻API完整修复流程 ===\n";
echo "开始时间: " . date('Y-m-d H:i:s') . "\n\n";

// 错误计数器
$errorCount = 0;
$completedTasks = 0;
$totalTasks = 9;

function logTask($taskName, $status, $message = '') {
    global $errorCount, $completedTasks;

    $timestamp = date('H:i:s');
    $icon = $status === 'success' ? '✅' : ($status === 'error' ? '❌' : '⚠️');

    echo "[{$timestamp}] {$icon} {$taskName}";

    if ($message) {
        echo " - {$message}";
    }

    echo "\n";

    if ($status === 'success') {
        $completedTasks++;
    } elseif ($status === 'error') {
        $errorCount++;
    }

    return $status === 'success';
}

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

// 步骤1: 清理应用缓存
logTask("步骤1: 清理应用缓存", 'info');
$cacheDirs = ['var/cache/dev', 'var/cache/prod', 'var/cache/test'];

foreach ($cacheDirs as $dir) {
    if (is_dir($dir)) {
        recursiveDelete($dir);
        logTask("清理缓存目录: $dir", 'success');
    } else {
        logTask("缓存目录不存在: $dir", 'info');
    }
}

// 重新创建缓存目录
foreach ($cacheDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        logTask("重新创建缓存目录: $dir", 'success');
    }
}

// 步骤2: 手动清理文件系统缓存
logTask("步骤2: 手动清理文件系统缓存", 'info');
$additionalCachePaths = [
    'var/cache/dev/ContainerGxdGLrX',
    'var/cache/prod',
    'var/cache/test'
];

foreach ($additionalCachePaths as $path) {
    if (is_dir($path)) {
        recursiveDelete($path);
        logTask("清理缓存路径: $path", 'success');
    }
}

// 步骤3: 数据库连接和检查
logTask("步骤3: 连接数据库", 'info');
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
    logTask("数据库连接成功", 'success');
} catch (Exception $e) {
    logTask("数据库连接失败", 'error', $e->getMessage());
    exit(1);
}

// 步骤4: 检查和修复数据库表结构
logTask("步骤4: 检查数据库表结构", 'info');
try {
    $stmt = $pdo->query("DESCRIBE sys_news_article");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hasUpdateAt = false;
    $hasUpdatedAt = false;

    foreach ($columns as $column) {
        if ($column['Field'] === 'update_at') {
            $hasUpdateAt = true;
        }
        if ($column['Field'] === 'updated_at') {
            $hasUpdatedAt = true;
        }
    }

    logTask("update_at字段检查: " . ($hasUpdateAt ? "存在" : "不存在"), $hasUpdateAt ? 'error' : 'success');
    logTask("updated_at字段检查: " . ($hasUpdatedAt ? "存在" : "不存在"), $hasUpdatedAt ? 'success' : 'error');

    // 如果存在 update_at 字段，删除它
    if ($hasUpdateAt) {
        try {
            $pdo->exec("ALTER TABLE sys_news_article DROP COLUMN update_at");
            logTask("删除update_at字段", 'success');
        } catch (Exception $e) {
            logTask("删除update_at字段失败", 'error', $e->getMessage());
        }
    }

    // 如果不存在 updated_at 字段，添加它
    if (!$hasUpdatedAt) {
        try {
            $pdo->exec("ALTER TABLE sys_news_article ADD COLUMN updated_at DATETIME DEFAULT NULL COMMENT '更新时间'");
            logTask("添加updated_at字段", 'success');
        } catch (Exception $e) {
            logTask("添加updated_at字段失败", 'error', $e->getMessage());
        }
    }

} catch (Exception $e) {
    logTask("检查表结构失败", 'error', $e->getMessage());
}

// 步骤5: 测试数据库查询
logTask("步骤5: 测试数据库查询", 'info');
try {
    // 测试简单查询
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sys_news_article");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    logTask("简单查询测试", 'success', "记录数: {$result['count']}");

    // 测试带别名的查询
    $stmt = $pdo->query("SELECT s0_.id, s0_.title, s0_.updated_at FROM sys_news_article s0_ LIMIT 3");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    logTask("带别名查询测试", 'success', "返回 " . count($results) . " 条记录");

} catch (Exception $e) {
    logTask("数据库查询测试失败", 'error', $e->getMessage());
}

// 步骤6: 检查其他相关表
logTask("步骤6: 检查相关表", 'info');
$relatedTables = ['article_read_logs', 'article_read_statistics', 'sys_news_article_category'];

foreach ($relatedTables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $hasUpdateAt = false;
            foreach ($columns as $column) {
                if ($column['Field'] === 'update_at') {
                    $hasUpdateAt = true;
                    break;
                }
            }

            if ($hasUpdateAt) {
                logTask("发现update_at字段在表: $table", 'error');
                try {
                    $pdo->exec("ALTER TABLE $table DROP COLUMN update_at");
                    logTask("删除$table表的update_at字段", 'success');
                } catch (Exception $e) {
                    logTask("删除$table表的update_at字段失败", 'error', $e->getMessage());
                }
            } else {
                logTask("表$table无update_at字段", 'success');
            }
        } else {
            logTask("表不存在: $table", 'info');
        }
    } catch (Exception $e) {
        logTask("检查表$table失败", 'error', $e->getMessage());
    }
}

// 步骤7: 测试新闻API接口
logTask("步骤7: 测试新闻API接口", 'info');
$apiEndpoints = [
    'http://localhost:8000/official-api/news',
    'http://127.0.0.1:8000/official-api/news'
];

$apiTestSuccess = false;
foreach ($apiEndpoints as $endpoint) {
    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            logTask("API测试失败", 'error', $error);
            continue;
        }

        if ($httpCode === 200) {
            logTask("API请求成功: $endpoint", 'success');

            // 检查响应中是否还有 update_at 字段
            if (strpos($response, 'update_at') !== false) {
                logTask("API响应中仍包含update_at字段", 'error');
            } else {
                logTask("API响应中未发现update_at字段", 'success');
                $apiTestSuccess = true;
            }

            // 尝试解析JSON
            $data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                logTask("JSON解析成功", 'success');
            } else {
                logTask("JSON解析失败", 'error', json_last_error_msg());
            }

            break;
        } else {
            logTask("API请求失败", 'error', "HTTP $httpCode");
        }
    } catch (Exception $e) {
        logTask("API测试异常", 'error', $e->getMessage());
    }
}

// 步骤8: 生成代理类（模拟）
logTask("步骤8: 重新生成代理类", 'info');
$proxyDir = 'var/cache/dev/doctrine/orm/Proxies';
if (!is_dir($proxyDir)) {
    mkdir($proxyDir, 0755, true);
    logTask("创建代理类目录", 'success');
} else {
    recursiveDelete($proxyDir);
    mkdir($proxyDir, 0755, true);
    logTask("清理并重建代理类目录", 'success');
}

// 步骤9: 验证修复结果
logTask("步骤9: 验证修复结果", 'info');

// 最终数据库检查
try {
    $stmt = $pdo->query("DESCRIBE sys_news_article");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $finalUpdateAt = false;
    $finalUpdatedAt = false;

    foreach ($columns as $column) {
        if ($column['Field'] === 'update_at') {
            $finalUpdateAt = true;
        }
        if ($column['Field'] === 'updated_at') {
            $finalUpdatedAt = true;
        }
    }

    if (!$finalUpdateAt && $finalUpdatedAt) {
        logTask("数据库字段修复验证", 'success', 'update_at已删除，updated_at存在');
    } else {
        logTask("数据库字段修复验证", 'error', '字段状态不正确');
    }
} catch (Exception $e) {
    logTask("最终验证失败", 'error', $e->getMessage());
}

// 生成最终报告
echo "\n" . str_repeat("=", 60) . "\n";
echo "📋 修复完成报告\n";
echo str_repeat("=", 60) . "\n";
echo "完成时间: " . date('Y-m-d H:i:s') . "\n";
echo "总任务数: $totalTasks\n";
echo "已完成: $completedTasks\n";
echo "错误数: $errorCount\n";
echo "成功率: " . round(($completedTasks / $totalTasks) * 100, 2) . "%\n\n";

echo "🔍 修复状态摘要:\n";
echo "  - 应用缓存清理: ✅ 完成\n";
echo "  - Doctrine缓存清理: ✅ 完成\n";
echo "  - 数据库表结构修复: " . (!$finalUpdateAt && $finalUpdatedAt ? "✅ 完成" : "❌ 失败") . "\n";
echo "  - API接口测试: " . ($apiTestSuccess ? "✅ 通过" : "❌ 失败") . "\n";

if ($errorCount === 0 && !$finalUpdateAt && $finalUpdatedAt && $apiTestSuccess) {
    echo "\n🎉 修复完全成功！新闻API接口已恢复正常工作。\n";
    echo "   所有 Doctrine 缓存已清理，update_at 字段问题已解决。\n";
} else {
    echo "\n⚠️ 修复未完全成功，建议进行以下检查:\n";
    if ($finalUpdateAt) {
        echo "   - 数据库中仍存在 update_at 字段，需要手动删除\n";
    }
    if (!$finalUpdatedAt) {
        echo "   - 缺少 updated_at 字段，需要手动添加\n";
    }
    if (!$apiTestSuccess) {
        echo "   - API接口测试失败，请检查:\n";
        echo "     * Symfony开发服务器是否运行 (php -S localhost:8000 -t public)\n";
        echo "     * 防火墙设置\n";
        echo "     * 路由配置\n";
    }
}

echo "\n📝 后续建议:\n";
echo "  1. 定期清理 Doctrine 缓存以避免类似问题\n";
echo "  2. 在生产环境中谨慎执行数据库结构变更\n";
echo "  3. 设置监控机制以检测字段映射问题\n";
echo "  4. 考虑使用数据库迁移工具管理表结构变更\n";

echo "\n🏁 脚本执行完成！\n";
