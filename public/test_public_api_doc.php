<?php
/**
 * 测试公共API文档修复结果
 */

echo "=== 公共API文档修复验证测试 ===\n\n";

// 测试1：检查API文档文件是否存在且可读
echo "1. 检查API文档文件状态:\n";
$apiDocFile = __DIR__ . '/api_doc.json';
if (file_exists($apiDocFile)) {
    echo "✓ api_doc.json 文件存在\n";
    $content = file_get_contents($apiDocFile);
    if ($content !== false) {
        echo "✓ 文件可读取\n";
        $data = json_decode($content, true);
        if ($data !== null) {
            echo "✓ JSON格式正确\n";
        } else {
            echo "✗ JSON格式错误: " . json_last_error_msg() . "\n";
        }
    } else {
        echo "✗ 文件无法读取\n";
    }
} else {
    echo "✗ api_doc.json 文件不存在\n";
}

echo "\n";

// 测试2：检查公共API路径是否在文档中
echo "2. 检查公共API路径:\n";
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

echo "\n";

// 测试3：检查具体的接口
echo "3. 检查具体接口:\n";
$expectedEndpoints = [
    '/public-api/articles' => 'GET',
    '/public-api/news/{id}' => 'GET',
    '/public-api/wechat/{id}' => 'GET'
];

foreach ($expectedEndpoints as $path => $method) {
    if (isset($data['paths'][$path][$method])) {
        $endpoint = $data['paths'][$path][$method];
        echo "✓ $path $method - {$endpoint['summary']}\n";
    } else {
        echo "✗ $path $method - 缺失\n";
    }
}

echo "\n";

// 测试4：检查标签配置
echo "4. 检查标签配置:\n";
if (isset($data['paths'])) {
    $foundPublicTag = false;
    foreach ($data['paths'] as $path => $methods) {
        foreach ($methods as $method => $details) {
            if (isset($details['tags']) && in_array('公共接口', $details['tags'])) {
                $foundPublicTag = true;
                break 2;
            }
        }
    }

    if ($foundPublicTag) {
        echo "✓ 找到 '公共接口' 标签\n";
    } else {
        echo "✗ 未找到 '公共接口' 标签\n";
    }
}

echo "\n=== 测试完成 ===\n";
