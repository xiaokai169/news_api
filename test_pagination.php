<?php

// 简单的测试脚本来验证分页参数处理逻辑

// 模拟请求参数
function testParameterHandling($queryParams) {
    echo "测试参数: " . json_encode($queryParams) . "\n";

    // 支持新旧分页参数名，优先使用新参数
    $current = $queryParams['current'] ?? null;
    $pageSize = $queryParams['pageSize'] ?? null;

    // 如果新参数不存在，则尝试使用旧参数
    if ($current === null) {
        $current = $queryParams['page'] ?? 1;
    }
    if ($pageSize === null) {
        $pageSize = $queryParams['limit'] ?? 20;
    }

    // 参数验证和默认值处理
    $current = max(1, (int)$current);
    $pageSize = max(1, min(100, (int)$pageSize));

    echo "结果: current = $current, pageSize = $pageSize\n\n";

    return ['current' => $current, 'pageSize' => $pageSize];
}

// 测试用例
echo "=== 分页参数处理测试 ===\n\n";

// 测试1: 使用新参数
testParameterHandling(['current' => 2, 'pageSize' => 10]);

// 测试2: 使用旧参数
testParameterHandling(['page' => 3, 'limit' => 15]);

// 测试3: 新旧参数都存在，优先使用新参数
testParameterHandling(['current' => 5, 'pageSize' => 25, 'page' => 1, 'limit' => 20]);

// 测试4: 没有分页参数，使用默认值
testParameterHandling([]);

// 测试5: 参数边界测试
testParameterHandling(['current' => 0, 'pageSize' => 150]);

// 测试6: 非数字参数
testParameterHandling(['current' => 'abc', 'pageSize' => 'xyz']);

echo "=== 响应格式测试 ===\n\n";

// 模拟响应格式
function testResponseFormat($current, $pageSize, $total = 100) {
    $pages = (int)ceil($total / $pageSize);

    $response = [
        'items' => ['item1', 'item2'], // 模拟数据
        'total' => $total,
        'current' => $current,
        'pageSize' => $pageSize,
        'pages' => $pages,
    ];

    echo "响应格式: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";

    return $response;
}

// 测试响应格式
testResponseFormat(1, 20);
testResponseFormat(2, 10);

echo "=== 测试完成 ===\n";
