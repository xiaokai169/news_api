<?php

echo "=== 详细诊断报告 ===\n\n";

// 1. PHP版本检查
echo "1. PHP版本检查:\n";
echo "   PHP版本: " . PHP_VERSION . "\n";
echo "   支持构造函数属性提升: " . (version_compare(PHP_VERSION, '8.0.0', '>=') ? '✅' : '❌') . "\n\n";

// 2. 检查关键类是否可加载
echo "2. 关键类加载检查:\n";

try {
    if (class_exists('Psr\Log\LoggerInterface')) {
        echo "   ✅ Psr\Log\LoggerInterface 可加载\n";
    } else {
        echo "   ❌ Psr\Log\LoggerInterface 不可加载\n";
    }
} catch (Exception $e) {
    echo "   ❌ Psr\Log\LoggerInterface 加载失败: " . $e->getMessage() . "\n";
}

try {
    if (class_exists('Monolog\Logger')) {
        echo "   ✅ Monolog\Logger 可加载\n";

        // 检查是否实现了LoggerInterface
        $logger = new ReflectionClass('Monolog\Logger');
        if ($logger->implementsInterface('Psr\Log\LoggerInterface')) {
            echo "   ✅ Monolog\Logger 实现了 LoggerInterface\n";
        } else {
            echo "   ❌ Monolog\Logger 未实现 LoggerInterface\n";
        }
    } else {
        echo "   ❌ Monolog\Logger 不可加载\n";
    }
} catch (Exception $e) {
    echo "   ❌ Monolog\Logger 加载失败: " . $e->getMessage() . "\n";
}

try {
    if (class_exists('App\Service\WechatApiService')) {
        echo "   ✅ App\Service\WechatApiService 可加载\n";
    } else {
        echo "   ❌ App\Service\WechatApiService 不可加载\n";
    }
} catch (Exception $e) {
    echo "   ❌ App\Service\WechatApiService 加载失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. 检查composer自动加载
echo "3. Composer自动加载检查:\n";
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    echo "   ✅ autoload.php 文件存在\n";
    require_once $autoloadPath;
    echo "   ✅ autoload.php 已加载\n";
} else {
    echo "   ❌ autoload.php 文件不存在\n";
}

echo "\n";

// 4. 尝试创建实例
echo "4. 实例创建测试:\n";

try {
    $logger = new Monolog\Logger('test');
    echo "   ✅ Monolog\Logger 实例创建成功\n";

    if ($logger instanceof Psr\Log\LoggerInterface) {
        echo "   ✅ 实例是 LoggerInterface 的实现\n";
    } else {
        echo "   ❌ 实例不是 LoggerInterface 的实现\n";
    }
} catch (Exception $e) {
    echo "   ❌ Monolog\Logger 实例创建失败: " . $e->getMessage() . "\n";
}

try {
    if (class_exists('App\Service\WechatApiService')) {
        $wechatService = new App\Service\WechatApiService($logger);
        echo "   ✅ WechatApiService 实例创建成功\n";
    }
} catch (Exception $e) {
    echo "   ❌ WechatApiService 实例创建失败: " . $e->getMessage() . "\n";
}

echo "\n5. 语法检查:\n";

// 检查测试文件语法
$testFile = __DIR__ . '/test_wechat_access_token.php';
if (file_exists($testFile)) {
    $phpCode = file_get_contents($testFile);

    // 检查语法错误
    $syntaxCheck = @php_check_syntax($testFile);
    if ($syntaxCheck === true) {
        echo "   ✅ test_wechat_access_token.php 语法正确\n";
    } else {
        echo "   ❌ test_wechat_access_token.php 语法错误\n";
    }
} else {
    echo "   ❌ test_wechat_access_token.php 文件不存在\n";
}

echo "\n=== 诊断完成 ===\n";
