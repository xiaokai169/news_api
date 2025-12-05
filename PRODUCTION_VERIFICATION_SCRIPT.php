<?php

/**
 * 生产环境分布式锁修复验证脚本
 * 用于验证 lock_key -> lockKey 字段名修复是否成功
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置时区
date_default_timezone_set('Asia/Shanghai');

echo "🔍 生产环境分布式锁修复验证脚本\n";
echo "📅 执行时间: " . date('Y-m-d H:i:s') . "\n";
echo "📍 当前目录: " . __DIR__ . "\n";
echo str_repeat("=", 60) . "\n\n";

// 颜色输出函数
function colorOutput($text, $color = 'default') {
    $colors = [
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'default' => "\033[0m"
    ];
    echo $colors[$color] . $text . $colors['default'] . "\n";
}

function success($text) { colorOutput("✅ " . $text, 'green'); }
function error($text) { colorOutput("❌ " . $text, 'red'); }
function warning($text) { colorOutput("⚠️ " . $text, 'yellow'); }
function info($text) { colorOutput("ℹ️ " . $text, 'blue'); }

// 验证步骤 1: 检查文件是否存在
function checkFiles() {
    info("步骤 1: 检查关键文件是否存在");

    $files = [
        'src/Service/DistributedLockService.php',
        'src/Entity/DistributedLock.php',
        'src/Command/DistributedLockManagerCommand.php'
    ];

    $allExists = true;
    foreach ($files as $file) {
        if (file_exists($file)) {
            success("文件存在: $file");
        } else {
            error("文件不存在: $file");
            $allExists = false;
        }
    }

    return $allExists;
}

// 验证步骤 2: 检查代码中的字段名
function checkCodeFieldNames() {
    info("\n步骤 2: 检查代码中的字段名");

    $issues = [];

    // 检查 DistributedLockService.php
    $serviceFile = 'src/Service/DistributedLockService.php';
    if (file_exists($serviceFile)) {
        $content = file_get_contents($serviceFile);

        // 检查是否还有旧的 lock_key 字段（排除注释）
        $lines = explode("\n", $content);
        $hasOldField = false;
        foreach ($lines as $lineNum => $line) {
            // 跳过注释行
            if (preg_match('/^\s*\/\//', $line) || preg_match('/^\s*\*/', $line)) {
                continue;
            }
            if (strpos($line, 'lock_key') !== false) {
                $issues[] = "$serviceFile 第" . ($lineNum + 1) . "行: $line";
                $hasOldField = true;
            }
        }

        if (!$hasOldField) {
            success("DistributedLockService.php 无旧字段名");
        } else {
            error("DistributedLockService.php 仍包含旧字段名");
        }

        // 检查是否有正确的 lockKey 字段
        if (strpos($content, 'lockKey') !== false) {
            success("DistributedLockService.php 包含正确的 lockKey 字段");
        } else {
            warning("DistributedLockService.php 未找到 lockKey 字段");
        }
    }

    // 检查 DistributedLockManagerCommand.php
    $commandFile = 'src/Command/DistributedLockManagerCommand.php';
    if (file_exists($commandFile)) {
        $content = file_get_contents($commandFile);

        if (strpos($content, 'lock_key') !== false) {
            error("DistributedLockManagerCommand.php 仍包含旧字段名");
            $issues[] = "$commandFile 包含 lock_key";
        } else {
            success("DistributedLockManagerCommand.php 无旧字段名");
        }

        if (strpos($content, 'lockKey') !== false) {
            success("DistributedLockManagerCommand.php 包含正确的 lockKey 字段");
        }
    }

    return empty($issues);
}

// 验证步骤 3: 检查实体映射
function checkEntityMapping() {
    info("\n步骤 3: 检查实体映射");

    $entityFile = 'src/Entity/DistributedLock.php';
    if (!file_exists($entityFile)) {
        error("实体文件不存在: $entityFile");
        return false;
    }

    $content = file_get_contents($entityFile);

    // 检查是否有正确的字段映射
    if (strpos($content, "name: 'lockKey'") !== false) {
        success("实体映射正确: name: 'lockKey'");
    } else {
        error("实体映射错误: 未找到 name: 'lockKey'");
        return false;
    }

    // 检查字段属性
    if (strpos($content, 'private ?string $lockKey') !== false) {
        success("实体属性正确: \$lockKey");
    } else {
        error("实体属性错误: 未找到 \$lockKey");
        return false;
    }

    return true;
}

// 验证步骤 4: 数据库连接测试
function testDatabaseConnection() {
    info("\n步骤 4: 测试数据库连接");

    try {
        // 尝试加载数据库配置
        if (file_exists('.env')) {
            $envContent = file_get_contents('.env');
            preg_match('/DATABASE_URL="(.+)"/', $envContent, $matches);

            if (isset($matches[1])) {
                $dbUrl = $matches[1];
                success("找到数据库配置");

                // 解析数据库连接信息
                $parsed = parse_url($dbUrl);
                if ($parsed && isset($parsed['host'])) {
                    success("数据库主机: {$parsed['host']}");
                    return true;
                } else {
                    error("数据库URL解析失败");
                    return false;
                }
            } else {
                warning("未找到 DATABASE_URL 配置");
            }
        }

        // 尝试使用 Symfony 命令验证
        $output = [];
        $returnCode = 0;
        exec('php bin/console doctrine:database:check 2>&1', $output, $returnCode);

        if ($returnCode === 0) {
            success("数据库连接正常");
            return true;
        } else {
            warning("数据库连接可能有问题");
            return false;
        }

    } catch (Exception $e) {
        error("数据库连接测试失败: " . $e->getMessage());
        return false;
    }
}

// 验证步骤 5: Doctrine 架构验证
function validateDoctrineSchema() {
    info("\n步骤 5: 验证 Doctrine 架构");

    $output = [];
    $returnCode = 0;
    exec('php bin/console doctrine:schema:validate --env=prod 2>&1', $output, $returnCode);

    $outputText = implode("\n", $output);

    if ($returnCode === 0 && strpos($outputText, '[OK]') !== false) {
        success("Doctrine 架构验证通过");
        return true;
    } else {
        error("Doctrine 架构验证失败");
        echo "输出: \n" . $outputText . "\n";
        return false;
    }
}

// 验证步骤 6: 测试分布式锁功能
function testDistributedLock() {
    info("\n步骤 6: 测试分布式锁功能");

    try {
        // 测试锁管理命令
        $output = [];
        $returnCode = 0;
        exec('php bin/console app:distributed-lock:manage status 2>&1', $output, $returnCode);

        $outputText = implode("\n", $output);

        if ($returnCode === 0) {
            success("分布式锁管理命令正常");

            // 检查是否包含字段名错误
            if (strpos($outputText, 'lock_key') !== false) {
                error("输出中仍包含旧字段名 lock_key");
                return false;
            } else {
                success("输出中无旧字段名");
            }

            return true;
        } else {
            error("分布式锁管理命令失败");
            echo "错误输出: \n" . $outputText . "\n";
            return false;
        }

    } catch (Exception $e) {
        error("分布式锁测试失败: " . $e->getMessage());
        return false;
    }
}

// 验证步骤 7: 检查缓存状态
function checkCacheStatus() {
    info("\n步骤 7: 检查缓存状态");

    $cacheDir = 'var/cache';

    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*');
        if (count($files) > 0) {
            warning("缓存目录不为空，建议清理缓存");
            echo "缓存文件数量: " . count($files) . "\n";
        } else {
            success("缓存目录已清空");
        }
    } else {
        info("缓存目录不存在");
    }

    return true;
}

// 主验证流程
function runVerification() {
    $results = [];

    $results['files'] = checkFiles();
    $results['code'] = checkCodeFieldNames();
    $results['entity'] = checkEntityMapping();
    $results['database'] = testDatabaseConnection();
    $results['doctrine'] = validateDoctrineSchema();
    $results['lock'] = testDistributedLock();
    $results['cache'] = checkCacheStatus();

    // 汇总结果
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "📊 验证结果汇总\n";
    echo str_repeat("=", 60) . "\n";

    $passed = 0;
    $total = count($results);

    foreach ($results as $test => $result) {
        $status = $result ? "✅ 通过" : "❌ 失败";
        $color = $result ? 'green' : 'red';
        colorOutput(sprintf("%-20s: %s", $test, $status), $color);

        if ($result) $passed++;
    }

    echo "\n📈 总体结果: $passed/$total 项测试通过\n";

    if ($passed === $total) {
        success("🎉 所有验证测试通过！修复成功！");
        echo "\n🔧 建议执行以下命令完成修复:\n";
        echo "php bin/console cache:clear --env=prod --no-warmup\n";
        echo "php bin/console doctrine:generate:proxies --env=prod --regenerate\n";
        echo "systemctl restart php-fpm nginx\n";
    } else {
        error("⚠️ 仍有问题需要修复");
        echo "\n🔧 建议执行以下修复步骤:\n";
        echo "1. 检查代码中是否还有 lock_key 引用\n";
        echo "2. 验证数据库字段名是否正确\n";
        echo "3. 清理所有缓存\n";
        echo "4. 重启相关服务\n";
    }

    return $passed === $total;
}

// 执行验证
try {
    $success = runVerification();
    exit($success ? 0 : 1);
} catch (Exception $e) {
    error("验证脚本执行失败: " . $e->getMessage());
    exit(1);
}
