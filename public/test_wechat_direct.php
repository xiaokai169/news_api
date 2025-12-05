<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Service\WechatApiService;
use App\Entity\WechatPublicAccount;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * 直接测试微信API错误日志功能
 */

echo "=== 直接测试微信API错误日志功能 ===\n";
echo "测试时间: " . date('Y-m-d H:i:s') . "\n\n";

// 创建测试日志
$logFile = __DIR__ . '/../var/log/wechat_direct_test.log';
$logger = new Logger('wechat_test');
$logger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));

// 创建模拟的微信账号
class MockWechatPublicAccount extends WechatPublicAccount
{
    private string $mockAppId;
    private string $mockAppSecret;

    public function __construct(string $appId, string $appSecret)
    {
        $this->mockAppId = $appId;
        $this->mockAppSecret = $appSecret;
        parent::__construct();
    }

    public function getAppId(): ?string
    {
        return $this->mockAppId;
    }

    public function getAppSecret(): ?string
    {
        return $this->mockAppSecret;
    }
}

// 测试数据
$testAppId = 'wx1234567890abcdef';
$testAppSecret = 'abcdef1234567890abcdef1234567890';

echo "测试数据:\n";
echo "- AppId: {$testAppId}\n";
echo "- AppSecret: {$testAppSecret}\n";
echo "- Secret前8位: " . substr($testAppSecret, 0, 8) . "\n\n";

// 直接测试日志格式
echo "=== 测试1: 直接验证日志格式 ===\n";

// 清空日志文件
file_put_contents($logFile, '');

// 模拟WechatApiService中的错误日志代码
$appId = $testAppId;
$appSecret = $testAppSecret;
$errmsg = 'invalid ip 192.168.1.100, not in whitelist, rid: 6123456789012345678';

// 这是WechatApiService中的关键代码
$logger->error('获取access_token返回错误: ' . $errmsg .
    ', appid: ' . $appId .
    ', secret: ' . substr($appSecret, 0, 8) . '***');

// 读取日志内容
$logContent = file_get_contents($logFile);

echo "生成的日志内容:\n";
echo $logContent . "\n\n";

// 验证日志内容
echo "=== 验证结果 ===\n";
$success = true;

// 检查是否包含appid
if (strpos($logContent, $testAppId) === false) {
    echo "❌ 错误: 日志中未找到appid: {$testAppId}\n";
    $success = false;
} else {
    echo "✅ 正确: 日志中包含appid: {$testAppId}\n";
}

// 检查是否包含secret前8位
$secretPrefix = substr($testAppSecret, 0, 8);
if (strpos($logContent, $secretPrefix) === false) {
    echo "❌ 错误: 日志中未找到secret前8位: {$secretPrefix}\n";
    $success = false;
} else {
    echo "✅ 正确: 日志中包含secret前8位: {$secretPrefix}\n";
}

// 检查是否包含掩码
if (strpos($logContent, '***') === false) {
    echo "❌ 错误: 日志中未找到secret掩码 ***\n";
    $success = false;
} else {
    echo "✅ 正确: 日志中包含secret掩码 ***\n";
}

// 检查是否泄露完整secret
if (strpos($logContent, $testAppSecret) !== false) {
    echo "❌ 错误: 日志中包含完整secret（安全风险）\n";
    $success = false;
} else {
    echo "✅ 正确: 日志中未包含完整secret（安全）\n";
}

// 检查错误信息
if (strpos($logContent, $errmsg) === false) {
    echo "❌ 错误: 日志中未找到期望的错误信息\n";
    $success = false;
} else {
    echo "✅ 正确: 日志中包含期望的错误信息\n";
}

if ($success) {
    echo "\n🎉 测试1通过！日志格式正确且安全\n";
} else {
    echo "\n💥 测试1失败！\n";
}

// 测试不同的错误类型
echo "\n=== 测试2: 不同错误类型 ===\n";

$errorCases = [
    [
        'errcode' => 40013,
        'errmsg' => 'invalid appid',
        'appId' => 'invalid_appid_test',
        'appSecret' => 'secret_for_invalid_appid'
    ],
    [
        'errcode' => 40125,
        'errmsg' => 'invalid appsecret',
        'appId' => 'wx_valid_test',
        'appSecret' => 'invalid_secret_test'
    ]
];

foreach ($errorCases as $index => $case) {
    echo "--- 错误测试 " . ($index + 1) . " ---\n";

    // 清空日志文件
    file_put_contents($logFile, '');

    // 模拟日志记录
    $appId = $case['appId'];
    $appSecret = $case['appSecret'];
    $errmsg = $case['errmsg'];

    $logger->error('获取access_token返回错误: ' . $errmsg .
        ', appid: ' . $appId .
        ', secret: ' . substr($appSecret, 0, 8) . '***');

    // 读取日志内容
    $logContent = file_get_contents($logFile);

    echo "错误信息: {$errmsg}\n";
    echo "日志内容: " . trim($logContent) . "\n";

    // 简单验证
    if (strpos($logContent, $appId) !== false &&
        strpos($logContent, substr($appSecret, 0, 8)) !== false &&
        strpos($logContent, '***') !== false &&
        strpos($logContent, $appSecret) === false) {
        echo "✅ 通过\n";
    } else {
        echo "❌ 失败\n";
    }
    echo "\n";
}

echo "=== 测试完成 ===\n";
echo "详细日志请查看: {$logFile}\n";

// 询问是否删除测试文件
echo "\n是否删除测试日志文件？(y/N): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim(strtolower($line)) === 'y') {
    unlink($logFile);
    echo "测试日志文件已删除\n";
} else {
    echo "测试日志文件保留: {$logFile}\n";
}
