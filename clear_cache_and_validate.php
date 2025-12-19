<?php

require_once 'vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Doctrine\ORM\Tools\SchemaValidator;

echo "=== 开始清除缓存和验证数据库模式 ===\n\n";

// 1. 清除应用缓存
echo "1. 清除应用缓存...\n";
try {
    // 手动清除缓存目录
    $cacheDirs = [
        'var/cache/prod',
        'var/cache/dev',
        'var/cache/test'
    ];

    foreach ($cacheDirs as $cacheDir) {
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*');
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
            echo "   - 已清除 $cacheDir\n";
        }
    }
    echo "   ✓ 应用缓存清除完成\n";
} catch (Exception $e) {
    echo "   ✗ 清除应用缓存失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 2. 尝试使用 Doctrine 直接清除缓存
echo "2. 清除 Doctrine 缓存...\n";
try {
    // 检查是否有 Doctrine 配置
    if (file_exists('config/packages/doctrine.yaml')) {
        $doctrineConfig = yaml_parse_file('config/packages/doctrine.yaml');
        echo "   - 找到 Doctrine 配置文件\n";

        // 尝试创建 EntityManager 来清除缓存
        $paths = [__DIR__ . '/src/Entity'];
        $isDevMode = false;

        // 简单的 Doctrine 配置
        $config = \Doctrine\ORM\Tools\Setup::createAnnotationMetadataConfiguration($paths, $isDevMode);

        // 从环境变量获取数据库连接信息
        $dbUrl = $_ENV['DATABASE_URL'] ?? '';
        if ($dbUrl) {
            $conn = \Doctrine\DBAL\DriverManager::getConnection(['url' => $dbUrl]);
            $entityManager = \Doctrine\ORM\EntityManager::create($conn, $config);

            // 清除各种缓存
            $cacheDriver = $entityManager->getConfiguration()->getMetadataCache();
            if ($cacheDriver) {
                $cacheDriver->clear();
                echo "   - 已清除元数据缓存\n";
            }

            $queryCache = $entityManager->getConfiguration()->getQueryCache();
            if ($queryCache) {
                $queryCache->clear();
                echo "   - 已清除查询缓存\n";
            }

            $resultCache = $entityManager->getConfiguration()->getResultCache();
            if ($resultCache) {
                $resultCache->clear();
                echo "   - 已清除结果缓存\n";
            }

            echo "   ✓ Doctrine 缓存清除完成\n";
        } else {
            echo "   ✗ 未找到 DATABASE_URL 环境变量\n";
        }
    } else {
        echo "   ✗ 未找到 Doctrine 配置文件\n";
    }
} catch (Exception $e) {
    echo "   ✗ 清除 Doctrine 缓存失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. 验证数据库模式
echo "3. 验证数据库模式一致性...\n";
try {
    if (isset($entityManager)) {
        $validator = new SchemaValidator($entityManager);
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

        // 检查数据库同步状态
        $conn = $entityManager->getConnection();
        $sm = $conn->getSchemaManager();
        $tables = $sm->listTables();
        echo "   - 数据库中共有 " . count($tables) . " 张表\n";

    } else {
        echo "   ✗ 无法创建 EntityManager，跳过模式验证\n";
    }
} catch (Exception $e) {
    echo "   ✗ 数据库模式验证失败: " . $e->getMessage() . "\n";
}

echo "\n=== 缓存清除和验证完成 ===\n";
