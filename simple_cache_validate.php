<?php

echo "=== 简化版缓存清除和数据库验证 ===\n\n";

// 1. 清除应用缓存
echo "1. 清除应用缓存...\n";
try {
    $cacheDirs = [
        'var/cache/prod',
        'var/cache/dev',
        'var/cache/test'
    ];

    foreach ($cacheDirs as $cacheDir) {
        if (is_dir($cacheDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $fileinfo) {
                if ($fileinfo->isDir()) {
                    rmdir($fileinfo->getRealPath());
                } else {
                    unlink($fileinfo->getRealPath());
                }
            }
            echo "   - 已清除 $cacheDir\n";
        }
    }
    echo "   ✓ 应用缓存清除完成\n";
} catch (Exception $e) {
    echo "   ✗ 清除应用缓存失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 2. 直接使用数据库连接验证
echo "2. 验证数据库连接和表结构...\n";
try {
    // 从 .env 文件读取数据库配置
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $envContent = file_get_contents($envFile);
        $lines = explode("\n", $envContent);
        $databaseUrl = '';

        foreach ($lines as $line) {
            if (strpos(trim($line), 'DATABASE_URL=') === 0 && strpos(trim($line), '#') !== 0) {
                $databaseUrl = trim(substr(trim($line), strlen('DATABASE_URL=')));
                $databaseUrl = trim($databaseUrl, '"');
                break;
            }
        }

        if (!empty($databaseUrl)) {
            echo "   - 找到数据库配置\n";

            // 解析数据库 URL
            $urlParts = parse_url($databaseUrl);
            $host = $urlParts['host'] ?? '127.0.0.1';
            $port = $urlParts['port'] ?? '3306';
            $dbname = substr($urlParts['path'], 1) ?? '';
            $username = $urlParts['user'] ?? '';
            $password = $urlParts['pass'] ?? '';

            echo "   - 解析到的连接信息: host=$host, port=$port, dbname=$dbname, user=$username\n";

            echo "   - 连接数据库: $dbname@$host:$port\n";

            // 使用 PDO 连接数据库
            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            echo "   ✓ 数据库连接成功\n";

            // 获取所有表
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            echo "   - 数据库中共有 " . count($tables) . " 张表\n";

            // 检查关键表
            $expectedTables = ['news', 'wechat_public_account', 'article_read_logs'];
            foreach ($expectedTables as $table) {
                if (in_array($table, $tables)) {
                    echo "   - 表 $table: ✓ 存在\n";

                    // 检查表结构
                    $columns = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll();
                    $columnNames = array_column($columns, 'Field');

                    // 检查是否有 update_at 或 updated_at 字段
                    $hasUpdatedAt = in_array('updated_at', $columnNames);
                    $hasUpdateAt = in_array('update_at', $columnNames);

                    if ($hasUpdatedAt) {
                        echo "     - 字段 updated_at: ✓ 存在\n";
                    } elseif ($hasUpdateAt) {
                        echo "     - 字段 update_at: ⚠ 存在（可能是错误的字段名）\n";
                    } else {
                        echo "     - 字段 update_at/updated_at: ✗ 都不存在\n";
                    }

                    // 显示所有字段
                    echo "     - 所有字段: " . implode(', ', $columnNames) . "\n";

                } else {
                    echo "   - 表 $table: ✗ 不存在\n";
                }
            }

            // 检查是否有 update_at 字段错误的记录
            echo "\n3. 检查 update_at 字段错误...\n";
            foreach ($expectedTables as $table) {
                if (in_array($table, $tables)) {
                    try {
                        $count = $pdo->query("SELECT COUNT(*) as count FROM $table WHERE update_at IS NOT NULL")->fetch()['count'];
                        if ($count > 0) {
                            echo "   - 表 $table: 发现 $count 条记录有 update_at 字段数据\n";
                        } else {
                            echo "   - 表 $table: ✓ 没有 update_at 字段数据\n";
                        }
                    } catch (Exception $e) {
                        echo "   - 表 $table: 检查 update_at 字段时出错 - " . $e->getMessage() . "\n";
                    }
                }
            }

        } else {
            echo "   ✗ 未找到 DATABASE_URL 配置\n";
        }
    } else {
        echo "   ✗ 未找到 .env 文件\n";
    }

} catch (Exception $e) {
    echo "   ✗ 数据库验证失败: " . $e->getMessage() . "\n";
}

echo "\n=== 验证完成 ===\n";
