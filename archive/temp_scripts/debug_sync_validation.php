<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\DTO\Request\Wechat\SyncWechatDto;

// 模拟用户请求
$userRequest = [
    "accountId" => "gh_e4b07b2a992e6669",
    "force" => false
];

echo "=== 微信同步API验证调试 ===\n\n";

echo "1. 用户原始请求:\n";
echo json_encode($userRequest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// 创建DTO实例
$dto = new SyncWechatDto($userRequest);

echo "2. DTO创建后的状态:\n";
echo "- accountId: " . var_export($dto->getAccountId(), true) . "\n";
echo "- forceSync: " . var_export($dto->isForceSync(), true) . "\n";
echo "- syncScope: " . var_export($dto->getSyncScope(), true) . "\n";
echo "- articleLimit: " . var_export($dto->getArticleLimit(), true) . "\n\n";

echo "3. 验证同步数据:\n";
$validationErrors = $dto->validateSyncData();

if (!empty($validationErrors)) {
    echo "验证失败:\n";
    foreach ($validationErrors as $field => $error) {
        echo "- $field: $error\n";
    }
} else {
    echo "验证通过\n";
}

echo "\n4. 测试修复方案:\n";

// 方案1：添加articleLimit
$fixedRequest1 = [
    "accountId" => "gh_e4b07b2a992e6669",
    "force" => false,
    "articleLimit" => 50
];

$dto1 = new SyncWechatDto($fixedRequest1);
$errors1 = $dto1->validateSyncData();
echo "方案1 (添加articleLimit): " . (empty($errors1) ? "✓ 通过" : "✗ 失败") . "\n";

// 方案2：明确设置syncScope为all
$fixedRequest2 = [
    "accountId" => "gh_e4b07b2a992e6669",
    "force" => false,
    "syncScope" => "all"
];

$dto2 = new SyncWechatDto($fixedRequest2);
$errors2 = $dto2->validateSyncData();
echo "方案2 (设置syncScope=all): " . (empty($errors2) ? "✓ 通过" : "✗ 失败") . "\n";

// 方案3：同时修复force和articleLimit
$fixedRequest3 = [
    "accountId" => "gh_e4b07b2a992e6669",
    "forceSync" => false,
    "articleLimit" => 50
];

$dto3 = new SyncWechatDto($fixedRequest3);
$errors3 = $dto3->validateSyncData();
echo "方案3 (修复forceSync+articleLimit): " . (empty($errors3) ? "✓ 通过" : "✗ 失败") . "\n";

echo "\n5. DTO摘要信息:\n";
echo json_encode($dto->getSyncSummary(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
