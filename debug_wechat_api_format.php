<?php

// 模拟当前API返回格式
$currentFormat = [
    'success' => true,
    'data' => [
        'items' => [
            ['id' => 1, 'title' => '测试文章1'],
            ['id' => 2, 'title' => '测试文章2']
        ],
        'total' => 100,
        'page' => 1,
        'size' => 10,
        'filterSummary' => []
    ]
];

// 模拟前端期望的API返回格式
$expectedFormat = [
    'success' => true,
    'data' => [
        'items' => [
            ['id' => 1, 'title' => '测试文章1'],
            ['id' => 2, 'title' => '测试文章2']
        ],
        'currentPageNumber' => 1,
        'itemsPerPage' => 10,
        'totalCount' => 100
    ]
];

echo "=== 微信文章接口格式诊断 ===\n\n";

echo "1. 当前API返回格式:\n";
echo json_encode($currentFormat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "2. 前端期望格式:\n";
echo json_encode($expectedFormat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "3. 前端解构代码分析:\n";
echo "// 前端代码:\n";
echo "const {\n";
echo "  items: records = [],\n";
echo "  currentPageNumber: current = 1,\n";
echo "  itemsPerPage: size = 10,\n";
echo "  totalCount: total = 0\n";
echo "} = res.data || {};\n\n";

echo "4. 字段映射对比:\n";
echo "当前字段 -> 期望字段\n";
echo "items -> items ✓\n";
echo "page -> currentPageNumber ✗\n";
echo "size -> itemsPerPage ✗\n";
echo "total -> totalCount ✗\n";
echo "filterSummary -> (不需要) ✗\n\n";

echo "5. 解构测试:\n";

// 测试当前格式解构
echo "使用当前格式解构:\n";
$currentData = $currentFormat['data'];
$records = $currentData['items'] ?? [];
$current = $currentData['currentPageNumber'] ?? $currentData['page'] ?? 1;
$size = $currentData['itemsPerPage'] ?? $currentData['size'] ?? 10;
$total = $currentData['totalCount'] ?? $currentData['total'] ?? 0;

echo "  records: " . count($records) . " 条\n";
echo "  current: $current\n";
echo "  size: $size\n";
echo "  total: $total\n\n";

// 测试期望格式解构
echo "使用期望格式解构:\n";
$expectedData = $expectedFormat['data'];
$records = $expectedData['items'] ?? [];
$current = $expectedData['currentPageNumber'] ?? 1;
$size = $expectedData['itemsPerPage'] ?? 10;
$total = $expectedData['totalCount'] ?? 0;

echo "  records: " . count($records) . " 条\n";
echo "  current: $current\n";
echo "  size: $size\n";
echo "  total: $total\n\n";

echo "6. 诊断结论:\n";
echo "问题确认：字段名不匹配导致前端解构获取到默认值\n";
echo "需要修改：WechatController::getArticles() 方法的返回格式\n";
echo "解决方案：将返回字段名改为前端期望的名称\n";

echo "\n=== 诊断完成 ===\n";
