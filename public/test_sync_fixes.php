<?php
// 简单测试，不依赖Symfony自动加载
echo "=== 微信同步API问题验证 ===\n\n";

// 模拟SyncWechatDto的核心验证逻辑
function validateSyncData($data) {
    $errors = [];

    // 设置默认值
    $syncScope = $data['syncScope'] ?? 'recent';
    $articleLimit = $data['articleLimit'] ?? null;

    // 验证recent范围的必要条件
    if ($syncScope === 'recent' && !$articleLimit) {
        $errors['recentRange'] = 'recent范围必须提供文章数量限制';
    }

    return $errors;
}

// 用户原始请求
$userRequest = [
    "accountId" => "gh_e4b07b2a992e6669",
    "force" => false
];

echo "1. 原始请求验证:\n";
echo json_encode($userRequest, JSON_UNESCAPED_UNICODE) . "\n";
$errors = validateSyncData($userRequest);
echo "结果: " . (empty($errors) ? "✓ 通过" : "✗ 失败 - " . $errors['recentRange']) . "\n\n";

// 方案1：添加articleLimit
$fix1 = [
    "accountId" => "gh_e4b07b2a992e6669",
    "force" => false,
    "articleLimit" => 50
];

echo "2. 方案1 (添加articleLimit):\n";
echo json_encode($fix1, JSON_UNESCAPED_UNICODE) . "\n";
$errors = validateSyncData($fix1);
echo "结果: " . (empty($errors) ? "✓ 通过" : "✗ 失败") . "\n\n";

// 方案2：设置syncScope为all
$fix2 = [
    "accountId" => "gh_e4b07b2a992e6669",
    "force" => false,
    "syncScope" => "all"
];

echo "3. 方案2 (设置syncScope=all):\n";
echo json_encode($fix2, JSON_UNESCAPED_UNICODE) . "\n";
$errors = validateSyncData($fix2);
echo "结果: " . (empty($errors) ? "✓ 通过" : "✗ 失败") . "\n\n";

// 方案3：完整参数
$fix3 = [
    "accountId" => "gh_e4b07b2a992e6669",
    "forceSync" => false,
    "syncScope" => "recent",
    "articleLimit" => 50
];

echo "4. 方案3 (完整参数):\n";
echo json_encode($fix3, JSON_UNESCAPED_UNICODE) . "\n";
$errors = validateSyncData($fix3);
echo "结果: " . (empty($errors) ? "✓ 通过" : "✗ 失败") . "\n\n";

echo "=== 推荐解决方案 ===\n";
echo "最简单修复：在原请求中添加 \"articleLimit\": 50\n";
echo "最佳实践：使用完整的参数名称 \"forceSync\" 而不是 \"force\"\n";
