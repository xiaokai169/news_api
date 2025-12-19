<?php

/**
 * 简单的缓存清理脚本
 * 不依赖 Symfony 控制台，直接操作文件系统
 */

echo "🧹 开始清理缓存...\n";

// 缓存目录列表
$cacheDirs = [
    'var/cache',
    'var/log'
];

foreach ($cacheDirs as $dir) {
    if (is_dir($dir)) {
        echo "清理目录: $dir\n";
        recursiveDelete($dir);
        echo "✅ 已清理: $dir\n";
    } else {
        echo "⚠️  目录不存在: $dir\n";
    }
}

// 重新创建缓存目录
if (!is_dir('var/cache')) {
    mkdir('var/cache', 0755, true);
    echo "✅ 重新创建: var/cache\n";
}

if (!is_dir('var/cache/dev')) {
    mkdir('var/cache/dev', 0755, true);
    echo "✅ 重新创建: var/cache/dev\n";
}

if (!is_dir('var/cache/prod')) {
    mkdir('var/cache/prod', 0755, true);
    echo "✅ 重新创建: var/cache/prod\n";
}

if (!is_dir('var/log')) {
    mkdir('var/log', 0755, true);
    echo "✅ 重新创建: var/log\n";
}

echo "\n✅ 缓存清理完成！\n";

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

echo "\n🔍 检查数据库表结构...\n";

// 检查数据库连接
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
            $dbname = substr($parsed['path'], 1); // 去掉开头的 /

            echo "连接到数据库: $dbname@$host\n";

            // 尝试连接数据库
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
                echo "⚠️  发现 update_at 字段（需要删除）\n";
            } else {
                echo "✅ 未发现 update_at 字段\n";
            }

            if ($hasUpdatedAt) {
                echo "✅ 发现 updated_at 字段\n";
            } else {
                echo "⚠️  未发现 updated_at 字段（可能需要添加）\n";
            }

        } else {
            echo "❌ 无法解析数据库配置\n";
        }
    } else {
        echo "❌ 未找到 .env 文件\n";
    }

} catch (Exception $e) {
    echo "❌ 数据库检查失败: " . $e->getMessage() . "\n";
}

echo "\n🎯 脚本执行完成！\n";
