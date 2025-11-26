<?php

// 启用错误显示
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>\n";
echo "<html>\n";
echo "<head>\n";
echo "    <meta charset=\"utf-8\"/>\n";
echo "    <title>API 文档测试</title>\n";
echo "    <style>\n";
echo "        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }\n";
echo "        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 800px; margin: 0 auto; }\n";
echo "        h1 { color: #333; margin-bottom: 20px; }\n";
echo "        h2 { color: #666; margin-top: 30px; }\n";
echo "        p { color: #666; line-height: 1.6; }\n";
echo "        .button { display: inline-block; background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin: 5px; }\n";
echo "        .button:hover { background: #0056b3; }\n";
echo "        .success { color: #28a745; }\n";
echo "        .error { color: #dc3545; }\n";
echo "        .code { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 10px 0; font-family: monospace; }\n";
echo "    </style>\n";
echo "</head>\n";
echo "<body>\n";
echo "    <div class=\"card\">\n";
echo "        <h1>🚀 API 文档系统</h1>\n";
echo "        <p>欢迎使用官方网站后台 API 文档系统！</p>\n";

// 检查环境
echo "<h2>🔍 环境检查</h2>\n";

$checks = [
    'PHP 版本' => PHP_VERSION,
    '服务器软件' => $_SERVER['SERVER_SOFTWARE'] ?? '未知',
    '文档根目录' => $_SERVER['DOCUMENT_ROOT'] ?? '未知',
    '请求 URI' => $_SERVER['REQUEST_URI'] ?? '未知'
];

foreach ($checks as $name => $value) {
    echo "<p><strong>$name:</strong> $value</p>\n";
}

// 检查文件
echo "<h2>📁 文件检查</h2>\n";
$files = [
    '../vendor/autoload.php' => 'Composer 自动加载',
    '../src/Kernel.php' => 'Symfony 内核',
    '../config/packages/nelmio_api_doc.yaml' => 'NelmioApiDoc 配置',
    '../config/routes/nelmio_api_doc.yaml' => 'Swagger UI 路由'
];

foreach ($files as $file => $description) {
    if (file_exists($file)) {
        echo "<p class=\"success\">✓ $description - 存在</p>\n";
    } else {
        echo "<p class=\"error\">✗ $description - 不存在</p>\n";
    }
}

// 测试简单 API
echo "<h2>🔗 API 测试链接</h2>\n";

$baseUri = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$apis = [
    '健康检查' => $baseUri . '/api/health',
    '测试接口' => $baseUri . '/api/test',
    'API 信息' => $baseUri . '/api/info',
    'Swagger UI (尝试1)' => $baseUri . '/api/doc',
    'Swagger UI (尝试2)' => $baseUri . '/docs',
    'OpenAPI JSON' => $baseUri . '/api/doc.json'
];

foreach ($apis as $name => $url) {
    echo "<a href=\"$url\" class=\"button\" target=\"_blank\">$name</a>\n";
}

// 显示调试信息
echo "<h2>🐛 调试信息</h2>\n";
echo "<div class=\"code\">\n";
echo "当前工作目录: " . getcwd() . "\n";
echo "脚本文件路径: " . __FILE__ . "\n";
echo "包含路径: " . get_include_path() . "\n";
echo "</div>\n";

// 尝试加载 Symfony
echo "<h2>⚙️ Symfony 测试</h2>\n";
try {
    if (file_exists('../vendor/autoload.php')) {
        require_once '../vendor/autoload.php';
        echo "<p class=\"success\">✓ Composer 自动加载成功</p>\n";

        if (class_exists('App\Kernel')) {
            echo "<p class=\"success\">✓ App\Kernel 类存在</p>\n";

            try {
                $kernel = new \App\Kernel('dev', true);
                echo "<p class=\"success\">✓ Symfony 内核创建成功</p>\n";
            } catch (Exception $e) {
                echo "<p class=\"error\">✗ Symfony 内核创建失败: " . $e->getMessage() . "</p>\n";
            }
        } else {
            echo "<p class=\"error\">✗ App\Kernel 类不存在</p>\n";
        }
    } else {
        echo "<p class=\"error\">✗ vendor/autoload.php 不存在</p>\n";
    }
} catch (Exception $e) {
    echo "<p class=\"error\">✗ 加载失败: " . $e->getMessage() . "</p>\n";
}

// 手动创建简单的 Swagger UI
echo "<h2>📚 手动 Swagger UI</h2>\n";
echo "<p>如果自动 Swagger UI 无法工作，可以尝试手动版本：</p>\n";
echo "<a href=\"swagger_manual.html\" class=\"button\" target=\"_blank\">手动 Swagger UI</a>\n";

echo "<h2>📝 下一步</h2>\n";
echo "<p>如果上述链接都无法正常工作，请：</p>\n";
echo "<ol>\n";
echo "<li>检查 PHP 错误日志</li>\n";
echo "<li>确保在正确的目录运行服务器</li>\n";
echo "<li>运行 'composer install' 安装依赖</li>\n";
echo "<li>检查文件权限</li>\n";
echo "</ol>\n";

echo "    </div>\n";
echo "</body>\n";
echo "</html>\n";
