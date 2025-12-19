<?php

require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

echo "=== 使用 Symfony 内核清除缓存和验证数据库模式 ===\n\n";

// 1. 加载环境变量
echo "1. 加载环境变量...\n";
try {
    $dotenv = new Dotenv();
    $dotenv->loadEnv(__DIR__ . '/.env');
    echo "   ✓ 环境变量加载完成\n";
} catch (Exception $e) {
    echo "   ✗ 环境变量加载失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 2. 清除 Symfony 缓存
echo "2. 清除 Symfony 应用缓存...\n";
try {
    // 清除各种环境的缓存
    $environments = ['prod', 'dev', 'test'];

    foreach ($environments as $env) {
        $cacheDir = __DIR__ . "/var/cache/$env";
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
            echo "   - 已清除 $env 环境缓存\n";
        }
    }

    // 清除 Doctrine 缓存池
    $cachePools = [
        __DIR__ . '/var/cache/prod/pools',
        __DIR__ . '/var/cache/dev/pools',
        __DIR__ . '/var/cache/test/pools'
    ];

    foreach ($cachePools as $poolDir) {
        if (is_dir($poolDir)) {
            $files = glob($poolDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                } elseif (is_dir($file)) {
                    $subFiles = glob($file . '/*');
                    foreach ($subFiles as $subFile) {
                        if (is_file($subFile)) {
                            unlink($subFile);
                        }
                    }
                    rmdir($file);
                }
            }
            echo "   - 已清除缓存池: " . basename($poolDir) . "\n";
        }
    }

    echo "   ✓ Symfony 缓存清除完成\n";
} catch (Exception $e) {
    echo "   ✗ 清除 Symfony 缓存失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. 使用 Symfony 内核验证数据库模式
echo "3. 验证数据库模式一致性...\n";
try {
    // 创建 Symfony 内核
    $kernel = new Symfony\Component\HttpKernel\Kernel('prod', false);
    $kernel->boot();

    // 获取 Doctrine 服务
    $entityManager = $kernel->getContainer()->get('doctrine.orm.default_entity_manager');

    if ($entityManager) {
        // 使用 SchemaValidator 验证
        $validator = new \Doctrine\ORM\Tools\SchemaValidator($entityManager);
        $errors = $validator->validateMapping();

        if (empty($errors)) {
            echo "   ✓ 数据库模式验证通过，没有发现映射不一致的问题\n";
        } else {
            echo "   ✗ 发现数据库模式映射问题:\n";
            foreach ($errors as $className => $classErrors) {
                echo "     - $className:\n";
                foreach ($classErrors as $error) {
                    echo "       * $error\n";
                }
            }
        }

        // 检查数据库连接和表结构
        $connection = $entityManager->getConnection();
        $schemaManager = $connection->createSchemaManager();
        $tables = $schemaManager->listTables();
        echo "   - 数据库连接正常，共有 " . count($tables) . " 张表\n";

        // 检查一些关键表是否存在
        $expectedTables = ['news', 'wechat_public_account', 'article_read_logs'];
        $existingTables = array_map(function($table) {
            return $table->getName();
        }, $tables);

        foreach ($expectedTables as $table) {
            if (in_array($table, $existingTables)) {
                echo "   - 表 $table: ✓ 存在\n";

                // 检查表结构，特别关注 update_at 字段
                $columns = $schemaManager->listTableColumns($table);
                $hasUpdatedAt = false;
                foreach ($columns as $column) {
                    if ($column->getName() === 'updated_at') {
                        $hasUpdatedAt = true;
                        break;
                    }
                }

                if ($hasUpdatedAt) {
                    echo "     - 字段 updated_at: ✓ 存在\n";
                } else {
                    echo "     - 字段 updated_at: ✗ 不存在\n";
                }
            } else {
                echo "   - 表 $table: ✗ 不存在\n";
            }
        }

    } else {
        echo "   ✗ 无法获取 EntityManager\n";
    }

    $kernel->shutdown();

} catch (Exception $e) {
    echo "   ✗ 数据库模式验证失败: " . $e->getMessage() . "\n";
    echo "   详细错误: " . $e->getTraceAsString() . "\n";
}

echo "\n=== 缓存清除和验证完成 ===\n";
