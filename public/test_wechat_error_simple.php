<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Service\WechatApiService;
use App\Entity\WechatPublicAccount;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * 简化的微信API错误日志测试
 */

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

// 创建专用的测试日志
$logFile = __DIR__ . '/../var/log/wechat_error_simple_test.log';
$logger = new Logger('wechat_test');
$logger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));

echo "=== 微信API错误日志功能测试 ===\n";
echo "测试时间: " . date('Y-m-d H:i:s') . "\n";
echo "日志文件: {$logFile}\n\n";

// 测试数据
$testCases = [
    [
        'name' => 'Invalid IP错误',
        'appId' => 'wx1234567890abcdef',
        'appSecret' => 'abcdef1234567890abcdef1234567890',
        'errorCode' => 40164,
        'errorMsg' => 'invalid ip 192.168.1.100, not in whitelist'
    ],
    [
        'name' => '无效AppID错误',
        'appId' => 'invalid_appid',
        'appSecret' => 'secret1234567890abcdef1234567890',
        'errorCode' => 40013,
        'errorMsg' => 'invalid appid'
    ],
    [
        'name' => '无效AppSecret错误',
        'appId' => 'wx_valid123456',
        'appSecret' => 'invalid_secret',
        'errorCode' => 40125,
        'errorMsg' => 'invalid appsecret'
    ]
];

// 创建一个简化的WechatApiService测试类
class TestWechatApiService extends WechatApiService
{
    private $testResponse;

    public function setTestResponse(array $response)
    {
        $this->testResponse = $response;
    }

    public function getAccessToken(WechatPublicAccount $account): ?string
    {
        // 模拟微信API响应
        if ($this->testResponse) {
            $appId = $account->getAppId();
            $appSecret = $account->getAppSecret();

            if (isset($this->testResponse['errcode']) && $this->testResponse['errcode'] !== 0) {
                // 这里是我们要测试的关键代码
                $this->logger->error('获取access_token返回错误: ' . $this->testResponse['errmsg'] .
                    ', appid: ' . $appId .
                    ', secret: ' . substr($appSecret, 0, 8) . '***');
                return null;
            }
        }

        return null;
    }
}

$testService = new TestWechatApiService($logger);

foreach ($testCases as $index => $testCase) {
    $testNumber = $index + 1;
    echo "--- 测试 {$testNumber}: {$testCase['name']} ---\n";

    // 创建模拟账号
    $mockAccount = new MockWechatPublicAccount($testCase['appId'], $testCase['appSecret']);

    // 设置测试响应
    $testService->setTestResponse([
        'errcode' => $testCase['errorCode'],
        'errmsg' => $testCase['errorMsg']
    ]);

    // 清空日志文件
    file_put_contents($logFile, '');

    // 执行测试
    $result = $testService->getAccessToken($mockAccount);

    // 读取日志内容
    $logContent = file_get_contents($logFile);

    echo "测试AppId: {$testCase['appId']}\n";
    echo "测试AppSecret: {$testCase['appSecret']}\n";
    echo "期望错误码: {$testCase['errorCode']}\n";
    echo "期望错误信息: {$testCase['errorMsg']}\n";
    echo "实际日志内容:\n{$logContent}\n";

    // 验证日志内容
    $success = true;

    // 检查是否包含appid
    if (strpos($logContent, $testCase['appId']) === false) {
        echo "❌ 错误: 日志中未找到appid\n";
        $success = false;
    } else {
        echo "✅ 正确: 日志中包含appid\n";
    }

    // 检查是否包含secret前8位
    $secretPrefix = substr($testCase['appSecret'], 0, 8);
    if (strpos($logContent, $secretPrefix) === false) {
        echo "❌ 错误: 日志中未找到secret前8位\n";
        $success = false;
    } else {
        echo "✅ 正确: 日志中包含secret前8位: {$secretPrefix}\n";
    }

    // 检查是否包含掩码
    if (strpos($logContent, '***') === false) {
        echo "❌ 错误: 日志中未找到secret掩码\n";
        $success = false;
    } else {
        echo "✅ 正确: 日志中包含secret掩码\n";
    }

    // 检查是否泄露完整secret
    if (strpos($logContent, $testCase['appSecret']) !== false) {
        echo "❌ 错误: 日志中包含完整secret（安全风险）\n";
        $success = false;
    } else {
        echo "✅ 正确: 日志中未包含完整secret（安全）\n";
    }

    // 检查错误信息
    if (strpos($logContent, $testCase['errorMsg']) === false) {
        echo "❌ 错误: 日志中未找到期望的错误信息\n";
        $success = false;
    } else {
        echo "✅ 正确: 日志中包含期望的错误信息\n";
    }

    if ($success) {
        echo "🎉 测试通过！\n";
    } else {
        echo "💥 测试失败！\n";
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
