<?php
echo "=== 接口文档修复验证 ===\n\n";

// 检查NelmioApiDoc配置
$configFile = __DIR__ . '/../config/packages/nelmio_api_doc.yaml';
if (file_exists($configFile)) {
    $config = file_get_contents($configFile);
    if (strpos($config, 'public-api') !== false) {
        echo "✓ NelmioApiDoc配置已添加 ^/public-api 路径\n";
    } else {
        echo "✗ NelmioApiDoc配置中未找到 ^/public-api 路径\n";
    }

    if (strpos($config, '公共接口') !== false) {
        echo "✓ 已添加 '公共接口' 标签\n";
    } else {
        echo "✗ 未找到 '公共接口' 标签\n";
    }
} else {
    echo "✗ NelmioApiDoc配置文件不存在\n";
}

echo "\n";

// 检查PublicController
$controllerFile = __DIR__ . '/../src/Controller/PublicController.php';
if (file_exists($controllerFile)) {
    $controller = file_get_contents($controllerFile);
    if (strpos($controller, '#[Route(\'/public-api\')]') !== false) {
        echo "✓ PublicController路由前缀正确\n";
    } else {
        echo "✗ PublicController路由前缀不正确\n";
    }

    if (strpos($controller, 'error_log') !== false) {
        echo "✓ 已添加调试日志\n";
    } else {
        echo "✗ 未找到调试日志\n";
    }
} else {
    echo "✗ PublicController文件不存在\n";
}

echo "\n";

// 检查API文档文件
$apiDocFile = __DIR__ . '/api_doc.json';
if (file_exists($apiDocFile)) {
    $content = file_get_contents($apiDocFile);
    $data = json_decode($content, true);

    if ($data !== null) {
        echo "✓ API文档JSON格式正确\n";

        if (isset($data['paths'])) {
            $publicApiPaths = array_filter(array_keys($data['paths']), function($path) {
                return strpos($path, '/public-api') === 0;
            });

            if (!empty($publicApiPaths)) {
                echo "✓ 找到公共API路径:\n";
                foreach ($publicApiPaths as $path) {
                    echo "  - $path\n";
                }
            } else {
                echo "✗ 未找到任何 /public-api 路径\n";
            }
        } else {
            echo "✗ 文档中未找到 paths 部分\n";
        }
    } else {
        echo "✗ API文档JSON格式错误: " . json_last_error_msg() . "\n";
    }
} else {
    echo "✗ API文档文件不存在\n";
}

echo "\n=== 验证完成 ===\n";
