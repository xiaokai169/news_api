<?php

/**
 * 新闻API修复验证脚本
 * 验证 'Unknown column s0_.update_at' 错误修复情况
 */

echo "🔍 开始验证新闻API修复...\n\n";

// 测试结果
$results = [];

// 1. 检查Entity映射
echo "📋 步骤 1: 验证Entity映射\n";
echo str_repeat("-", 40) . "\n";

$entityFile = 'src/Entity/SysNewsArticle.php';
if (file_exists($entityFile)) {
    $entityContent = file_get_contents($entityFile);

    // 检查是否有正确的update_at映射
    if (strpos($entityContent, "name: 'update_at'") !== false) {
        echo "✅ Entity中找到正确的 'update_at' 字段映射\n";
        $results['entity_mapping'] = 'PASS';
    } else {
        echo "❌ Entity中未找到正确的 'update_at' 字段映射\n";
        $results['entity_mapping'] = 'FAIL';
    }

    // 检查是否还有错误的updated_at映射
    if (strpos($entityContent, "name: 'updated_at'") !== false) {
        echo "⚠️  警告: Entity中仍然存在 'updated_at' 字段映射\n";
        $results['entity_conflict'] = 'WARNING';
    } else {
        echo "✅ Entity中没有冲突的 'updated_at' 字段映射\n";
        $results['entity_conflict'] = 'PASS';
    }
} else {
    echo "❌ 未找到Entity文件: $entityFile\n";
    $results['entity_file'] = 'FAIL';
}

echo "\n";

// 2. 检查数据库表结构
echo "📋 步骤 2: 验证数据库表结构\n";
echo str_repeat("-", 40) . "\n";

try {
    // 读取数据库配置
    if (file_exists('.env')) {
        $envContent = file_get_contents('.env');
        preg_match('/DATABASE_URL="(.+)"/', $envContent, $matches);

        if (isset($matches[1])) {
            $dbUrl = $matches[1];
            echo "找到数据库配置\n";

            // 解析数据库连接信息
            $parsed = parse_url($dbUrl);
            $host = $parsed['host'] ?? 'localhost';
            $dbname = substr($parsed['path'], 1);

            // 连接数据库
            $pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $parsed['user'] ?? 'root',
                $parsed['pass'] ?? ''
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            echo "✅ 数据库连接成功\n";

            // 检查 sys_news_article 表结构
            $stmt = $pdo->prepare("DESCRIBE sys_news_article");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "\n📋 sys_news_article 表字段:\n";
            $hasUpdateAt = false;
            $hasUpdatedAt = false;

            foreach ($columns as $column) {
                if ($column['Field'] === 'update_at') {
                    $hasUpdateAt = true;
                    echo "✅ update_at ({$column['Type']})\n";
                } elseif ($column['Field'] === 'updated_at') {
                    $hasUpdatedAt = true;
                    echo "⚠️  updated_at ({$column['Type']})\n";
                } else {
                    echo "- {$column['Field']} ({$column['Type']})\n";
                }
            }

            echo "\n🔍 字段验证结果:\n";
            if ($hasUpdateAt && !$hasUpdatedAt) {
                echo "✅ 数据库结构正确：有 update_at，没有 updated_at\n";
                $results['database_structure'] = 'PASS';
            } elseif ($hasUpdateAt && $hasUpdatedAt) {
                echo "⚠️  数据库结构警告：同时存在 update_at 和 updated_at\n";
                $results['database_structure'] = 'WARNING';
            } elseif (!$hasUpdateAt && $hasUpdatedAt) {
                echo "❌ 数据库结构错误：只有 updated_at，没有 update_at\n";
                $results['database_structure'] = 'FAIL';
            } else {
                echo "❌ 数据库结构错误：既没有 update_at 也没有 updated_at\n";
                $results['database_structure'] = 'FAIL';
            }

            // 3. 测试简单查询
            echo "\n📋 步骤 3: 测试数据库查询\n";
            echo str_repeat("-", 40) . "\n";

            try {
                // 测试基本查询
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sys_news_article");
                $stmt->execute();
                $count = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "✅ 基本查询成功，总记录数: {$count['count']}\n";

                // 测试涉及update_at字段的查询
                $stmt = $pdo->prepare("SELECT id, name, update_at FROM sys_news_article ORDER BY update_at DESC LIMIT 5");
                $stmt->execute();
                $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "✅ update_at字段查询成功\n";

                if (count($articles) > 0) {
                    echo "📰 最新文章示例:\n";
                    foreach ($articles as $article) {
                        echo "- ID: {$article['id']}, 标题: {$article['name']}, 更新时间: {$article['update_at']}\n";
                    }
                }

                $results['database_query'] = 'PASS';

            } catch (Exception $e) {
                echo "❌ 数据库查询失败: " . $e->getMessage() . "\n";
                $results['database_query'] = 'FAIL';
            }

        } else {
            echo "❌ 无法解析数据库配置\n";
            $results['database_config'] = 'FAIL';
        }
    } else {
        echo "❌ 未找到 .env 文件\n";
        $results['env_file'] = 'FAIL';
    }

} catch (Exception $e) {
    echo "❌ 数据库连接失败: " . $e->getMessage() . "\n";
    $results['database_connection'] = 'FAIL';
}

echo "\n";

// 4. 检查是否有其他相关文件需要修复
echo "📋 步骤 4: 检查相关文件\n";
echo str_repeat("-", 40) . "\n";

$filesToCheck = [
    'src/Repository/SysNewsArticleRepository.php',
    'src/Controller/PublicController.php',
    'src/DTO/Filter/NewsFilterDto.php'
];

foreach ($filesToCheck as $file) {
    if (file_exists($file)) {
        echo "✅ 文件存在: $file\n";

        // 检查文件中是否还有错误的updated_at引用
        $content = file_get_contents($file);
        if (strpos($content, 'updated_at') !== false) {
            echo "⚠️  警告: $file 中包含 'updated_at' 引用\n";
        }
    } else {
        echo "- 文件不存在: $file\n";
    }
}

echo "\n";

// 5. 生成总结报告
echo "📋 步骤 5: 生成总结报告\n";
echo str_repeat("-", 40) . "\n";

$passCount = 0;
$failCount = 0;
$warningCount = 0;

foreach ($results as $test => $status) {
    switch ($status) {
        case 'PASS':
            $passCount++;
            break;
        case 'FAIL':
            $failCount++;
            break;
        case 'WARNING':
            $warningCount++;
            break;
    }
}

echo "🎯 验证总结:\n";
echo "✅ 通过: $passCount\n";
echo "⚠️  警告: $warningCount\n";
echo "❌ 失败: $failCount\n";
echo "📊 总计: " . count($results) . "\n";

echo "\n📊 详细结果:\n";
foreach ($results as $test => $status) {
    $icon = match($status) {
        'PASS' => '✅',
        'FAIL' => '❌',
        'WARNING' => '⚠️',
        default => '❓'
    };
    echo "$icon $test: $status\n";
}

// 判断修复是否成功
$isFixSuccessful = ($results['entity_mapping'] ?? 'FAIL') === 'PASS' &&
                   ($results['database_structure'] ?? 'FAIL') === 'PASS' &&
                   ($results['database_query'] ?? 'FAIL') === 'PASS';

echo "\n🎉 修复验证结果:\n";
if ($isFixSuccessful) {
    echo "✅ 修复成功！'Unknown column s0_.update_at' 错误已解决\n";
    echo "✅ Entity映射正确\n";
    echo "✅ 数据库结构正确\n";
    echo "✅ 数据库查询正常\n";
} else {
    echo "❌ 修复未完全成功，仍存在问题\n";
    if (($results['entity_mapping'] ?? 'FAIL') !== 'PASS') {
        echo "- Entity映射问题\n";
    }
    if (($results['database_structure'] ?? 'FAIL') !== 'PASS') {
        echo "- 数据库结构问题\n";
    }
    if (($results['database_query'] ?? 'FAIL') !== 'PASS') {
        echo "- 数据库查询问题\n";
    }
}

// 保存验证报告
$report = [
    'timestamp' => date('Y-m-d H:i:s'),
    'fix_successful' => $isFixSuccessful,
    'summary' => [
        'passed' => $passCount,
        'warnings' => $warningCount,
        'failed' => $failCount,
        'total' => count($results)
    ],
    'results' => $results
];

file_put_contents('news_fix_verification_report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\n📄 详细验证报告已保存到: news_fix_verification_report.json\n";

echo "\n🎯 验证完成！\n";
