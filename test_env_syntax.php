<?php

// 简单的.env文件语法验证脚本
require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

try {
    $dotenv = new Dotenv();
    $dotenv->load('.env.prod');
    echo "✅ .env.prod 文件语法正确，可以正常解析！\n";
    echo "✅ 所有环境变量加载成功\n";

    // 特别检查我们修复的三个变量
    $requiredVars = [
        'X_XSS_PROTECTION',
        'STRICT_TRANSPORT_SECURITY',
        'CONTENT_SECURITY_POLICY'
    ];

    foreach ($requiredVars as $var) {
        if (isset($_ENV[$var])) {
            echo "✅ $var = " . $_ENV[$var] . "\n";
        } else {
            echo "❌ $var 未找到\n";
        }
    }

} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 语法验证完成！\n";
