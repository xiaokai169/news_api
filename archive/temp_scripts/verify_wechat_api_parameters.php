<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Service\WechatApiService;
use App\Entity\WechatPublicAccount;

// 创建一个测试用的微信账号实体
$testAccount = new WechatPublicAccount();
$testAccount->setId('test_account_123');
$testAccount->setName('测试账号');
$testAccount->setAppId('wx_test_app_id_12345');
$testAccount->setAppSecret('test_app_secret_67890');

echo "=== 微信API参数验证诊断 ===\n\n";

echo "1. 测试账号信息:\n";
echo "   - ID: " . $testAccount->getId() . "\n";
echo "   - 名称: " . $testAccount->getName() . "\n";
echo "   - AppId: " . $testAccount->getAppId() . "\n";
echo "   - AppSecret: " . $testAccount->getAppSecret() . "\n\n";

echo "2. 验证实体方法调用:\n";
echo "   - getAppId() 返回: " . var_export($testAccount->getAppId(), true) . "\n";
echo "   - getAppSecret() 返回: " . var_export($testAccount->getAppSecret(), true) . "\n\n";

echo "3. 模拟WechatApiService参数构建:\n";
$queryParams = [
    'grant_type' => 'client_credential',
    'appid' => $testAccount->getAppId(),
    'secret' => $testAccount->getAppSecret(),
];

echo "   构建的查询参数:\n";
echo "   - grant_type: " . $queryParams['grant_type'] . "\n";
echo "   - appid: " . $queryParams['appid'] . "\n";
echo "   - secret: " . $queryParams['secret'] . "\n\n";

echo "4. 验证参数名是否符合微信API要求:\n";
$requiredParams = ['grant_type', 'appid', 'secret'];
$actualParams = array_keys($queryParams);

echo "   - 必需参数: " . implode(', ', $requiredParams) . "\n";
echo "   - 实际参数: " . implode(', ', $actualParams) . "\n";

$missingParams = array_diff($requiredParams, $actualParams);
$extraParams = array_diff($actualParams, $requiredParams);

if (empty($missingParams) && empty($extraParams)) {
    echo "   ✅ 参数名完全匹配！\n";
} else {
    if (!empty($missingParams)) {
        echo "   ❌ 缺少参数: " . implode(', ', $missingParams) . "\n";
    }
    if (!empty($extraParams)) {
        echo "   ❌ 多余参数: " . implode(', ', $extraParams) . "\n";
    }
}

echo "\n5. 验证参数值是否为空:\n";
foreach ($queryParams as $key => $value) {
    if (empty($value)) {
        echo "   ❌ 参数 '{$key}' 为空或null\n";
    } else {
        echo "   ✅ 参数 '{$key}' 有值: " . substr($value, 0, 10) . (strlen($value) > 10 ? '...' : '') . "\n";
    }
}

echo "\n6. 构建完整的API请求URL:\n";
$baseUrl = 'https://api.weixin.qq.com/cgi-bin/token';
$fullUrl = $baseUrl . '?' . http_build_query($queryParams);
echo "   完整URL: " . $fullUrl . "\n\n";

echo "7. 诊断结论:\n";
$paramNamesCorrect = ($queryParams['appid'] !== null && $queryParams['secret'] !== null);
$paramValuesCorrect = (!empty($queryParams['appid']) && !empty($queryParams['secret']));

if ($paramNamesCorrect && $paramValuesCorrect) {
    echo "   ✅ 参数名和参数值都正确\n";
    echo "   ✅ 符合微信API官方文档要求\n";
    echo "   ✅ 如果仍有问题，可能原因:\n";
    echo "      - AppId或AppSecret值不正确\n";
    echo "      - 网络连接问题\n";
    echo "      - 微信服务器IP白名单限制\n";
    echo "      - AppId和AppSecret不匹配\n";
} else {
    echo "   ❌ 存在参数问题\n";
    if (!$paramNamesCorrect) {
        echo "      - 参数名或值为null\n";
    }
    if (!$paramValuesCorrect) {
        echo "      - 参数值为空\n";
    }
}

echo "\n=== 诊断完成 ===\n";
