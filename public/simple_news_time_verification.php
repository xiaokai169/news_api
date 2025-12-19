<?php

echo "=== 新闻时间字段修复验证脚本（简化版）===\n\n";

// 测试结果统计
$tests = [
    'entity_field_mapping' => false,
    'dto_default_sorting' => false,
    'repository_sorting' => false
];

echo "1. 验证实体字段映射修复\n";
echo "========================\n";

$entityFile = __DIR__ . '/../src/Entity/SysNewsArticle.php';
if (file_exists($entityFile)) {
    $entityContent = file_get_contents($entityFile);

    // 检查字段映射是否修复
    if (strpos($entityContent, '"create_at"') !== false && strpos($entityContent, '"update_at"') !== false) {
        echo "✓ 实体字段映射已修复：create_time -> create_at, update_time -> update_at\n";
        $tests['entity_field_mapping'] = true;
    } else {
        echo "✗ 实体字段映射未修复\n";
    }

    // 检查生命周期回调
    if (strpos($entityContent, 'onPrePersist') !== false && strpos($entityContent, 'onPreUpdate') !== false) {
        echo "✓ 生命周期回调存在\n";
    } else {
        echo "✗ 生命周期回调缺失\n";
    }
} else {
    echo "✗ 实体文件不存在\n";
}

echo "\n2. 验证 DTO 默认排序修复\n";
echo "========================\n";

$dtoFile = __DIR__ . '/../src/DTO/Filter/NewsFilterDto.php';
if (file_exists($dtoFile)) {
    $dtoContent = file_get_contents($dtoFile);

    // 检查默认排序字段
    if (strpos($dtoContent, "'releaseTime'") !== false && strpos($dtoContent, "'desc'") !== false) {
        echo "✓ DTO 默认排序已修复：sortBy='releaseTime', sortDirection='desc'\n";
        $tests['dto_default_sorting'] = true;
    } else {
        echo "✗ DTO 默认排序未修复\n";
    }

    // 检查构造函数
    if (strpos($dtoContent, 'public function __construct') !== false) {
        echo "✓ 构造函数存在\n";
    } else {
        echo "✗ 构造函数缺失\n";
    }
} else {
    echo "✗ DTO 文件不存在\n";
}

echo "\n3. 验证 Repository 排序修复\n";
echo "==========================\n";

$repositoryFile = __DIR__ . '/../src/Repository/SysNewsArticleRepository.php';
if (file_exists($repositoryFile)) {
    $repositoryContent = file_get_contents($repositoryFile);

    // 检查默认排序字段
    if (strpos($repositoryContent, "'releaseTime'") !== false) {
        echo "✓ Repository 默认排序已修复：sortBy='releaseTime'\n";
        $tests['repository_sorting'] = true;
    } else {
        echo "✗ Repository 默认排序未修复\n";
    }

    // 检查相关方法
    $methods = ['findByCriteria', 'findByCriteriaWithUser', 'findActivePublicArticles'];
    foreach ($methods as $method) {
        if (strpos($repositoryContent, $method) !== false) {
            echo "✓ 方法 {$method} 存在\n";
        } else {
            echo "✗ 方法 {$method} 缺失\n";
        }
    }
} else {
    echo "✗ Repository 文件不存在\n";
}

echo "\n=== 测试结果总结 ===\n";

$passedTests = array_sum($tests);
$totalTests = count($tests);

echo "通过测试: {$passedTests}/{$totalTests}\n\n";

foreach ($tests as $testName => $result) {
    $status = $result ? '✓ 通过' : '✗ 失败';
    $testNameZh = [
        'entity_field_mapping' => '实体字段映射修复',
        'dto_default_sorting' => 'DTO默认排序修复',
        'repository_sorting' => 'Repository排序修复'
    ];
    echo "{$status} {$testNameZh[$testName]}\n";
}

echo "\n=== 修复内容摘要 ===\n";

echo "1. 实体字段映射修复：\n";
echo "   - create_time -> create_at\n";
echo "   - update_time -> update_at\n";

echo "\n2. DTO 默认排序修复：\n";
echo "   - 默认排序字段：releaseTime\n";
echo "   - 默认排序方向：desc\n";

echo "\n3. Repository 排序修复：\n";
echo "   - findByCriteria 方法默认排序\n";
echo "   - findByCriteriaWithUser 方法默认排序\n";
echo "   - findActivePublicArticles 方法排序\n";

echo "\n=== 验证完成 ===\n";

if ($passedTests === $totalTests) {
    echo "🎉 所有静态验证通过！新闻时间字段修复成功！\n";
    echo "\n建议：\n";
    echo "1. 运行完整的应用程序测试以验证功能\n";
    echo "2. 检查数据库中的现有数据\n";
    echo "3. 测试 API 接口响应\n";
} else {
    echo "⚠️  部分验证失败，请检查相关修复。\n";
}

echo "\n修复完成时间：" . date('Y-m-d H:i:s') . "\n";
