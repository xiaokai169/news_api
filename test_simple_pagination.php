<?php

echo "=== 简单分页参数测试 ===\n\n";

// 测试1: 验证新参数格式
echo "1. 测试新参数格式 page=1&size=10:\n";
$params = ['page' => 1, 'size' => 10];
echo "   输入参数: " . json_encode($params) . "\n";

// 模拟控制器的参数处理逻辑
$page = $params['page'] ?? $params['current'] ?? 1;
$size = $params['size'] ?? $params['pageSize'] ?? 20;

echo "   解析结果: page = $page, size = $size\n\n";

// 测试2: 验证旧参数格式仍然有效
echo "2. 测试旧参数格式 current=2&pageSize=20:\n";
$params2 = ['current' => 2, 'pageSize' => 20];
echo "   输入参数: " . json_encode($params2) . "\n";

$page2 = $params2['page'] ?? $params2['current'] ?? 1;
$size2 = $params2['size'] ?? $params2['pageSize'] ?? 20;

echo "   解析结果: page = $page2, size = $size2\n\n";

// 测试3: 验证混合参数时优先使用新参数
echo "3. 测试混合参数 (优先新参数):\n";
$params3 = ['page' => 5, 'size' => 15, 'current' => 1, 'pageSize' => 10];
echo "   输入参数: " . json_encode($params3) . "\n";

$page3 = $params3['page'] ?? $params3['current'] ?? 1;
$size3 = $params3['size'] ?? $params3['pageSize'] ?? 20;

echo "   解析结果: page = $page3, size = $size3\n\n";

// 测试4: 验证响应格式
echo "4. 测试响应格式:\n";
$total = 100;
$pages = ceil($total / $size);

$response = [
    'items' => ['item1', 'item2', 'item3'],
    'total' => $total,
    'page' => $page,
    'size' => $size,
    'pages' => $pages,
    // 向后兼容字段
    'current' => $page,
    'pageSize' => $size,
];

echo "   响应格式 (包含新旧字段):\n";
echo "   " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";

echo "=== 测试完成 ===\n";
