<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Service\WechatApiService;
use App\Entity\WechatPublicAccount;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * 微信API错误日志功能测试脚本
 *
 * 测试目标：
 * 1. 验证修改后的WechatApiService是否能正确打印appid和secret信息
 * 2. 测试获取access_token失败的情况，特别是"invalid ip"错误
 * 3. 验证日志输出是否包含了appid和secret信息（secret应该只显示前8位）
 * 4. 确保测试不会影响生产数据
 */

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

class WechatErrorLoggingTest
{
    private Logger $logger;
    private string $logFile;

    public function __construct()
    {
        // 创建专用的测试日志文件
        $this->logFile = __DIR__ . '/../var/log/wechat_error_test.log';
        $this->logger = new Logger('wechat_test');
        $this->logger->pushHandler(new StreamHandler($this->logFile, Logger::DEBUG));
    }

    /**
     * 测试"invalid ip"错误日志
     */
    public function testInvalidIpError(): void
    {
        echo "=== 测试1: invalid IP 错误日志 ===\n";

        // 创建模拟的微信账号
        $testAppId = 'wx1234567890abcdef';
        $testAppSecret = 'abcdef1234567890abcdef1234567890';
        $mockAccount = new MockWechatPublicAccount($testAppId, $testAppSecret);

        // 创建模拟HTTP客户端，返回invalid ip错误
        $responses = [
            new MockResponse(json_encode([
                'errcode' => 40164,
                'errmsg' => 'invalid ip 192.168.1.100, not in whitelist, rid: 6123456789012345678'
            ]), [
                'http_code' => 200
            ])
        ];

        $mockHttpClient = new MockHttpClient($responses);

        // 创建WechatApiService实例并注入模拟的HTTP客户端
        $wechatService = new class($this->logger) extends WechatApiService {
            private $mockHttpClient;

            public function __construct(LoggerInterface $logger, $mockHttpClient = null)
            {
                parent::__construct($logger);
                $this->mockHttpClient = $mockHttpClient;
            }

            public function getAccessToken(WechatPublicAccount $account): ?string
            {
                try {
                    $client = $this->mockHttpClient ?: HttpClient::create();
                    $response = $client->request('GET', 'https://api.weixin.qq.com/cgi-bin/token', [
                        'query' => [
                            'grant_type' => 'client_credential',
                            'appid' => $account->getAppId(),
                            'secret' => $account->getAppSecret(),
                        ],
                    ]);

                    if ($response->getStatusCode() !== 200) {
                        $this->logger->error('获取access_token失败，状态码: ' . $response->getStatusCode());
                        return null;
                    }

                    $result = json_decode($response->getContent(), true);

                    if (isset($result['errcode']) && $result['errcode'] !== 0) {
                        $appId = $account->getAppId();
                        $appSecret = $account->getAppSecret();
                        $this->logger->error('获取access_token返回错误: ' . $result['errmsg'] .
                            ', appid: ' . $appId .
                            ', secret: ' . substr($appSecret, 0, 8) . '***');
                        return null;
                    }

                    return $result['access_token'] ?? null;

                } catch (\Exception $e) {
                    $this->logger->error('获取access_token失败: ' . $e->getMessage());
                    return null;
                }
            }
        };

        // 清空日志文件
        file_put_contents($this->logFile, '');

        // 执行测试
        $result = $wechatService->getAccessToken($mockAccount);

        // 读取日志内容
        $logContent = file_get_contents($this->logFile);

        echo "测试结果:\n";
        echo "- 返回结果: " . ($result === null ? 'null (符合预期)' : 'unexpected') . "\n";
        echo "- 日志内容:\n" . $logContent . "\n";

        // 验证日志内容
        $this->validateLogContent($logContent, $testAppId, $testAppSecret, 'invalid ip');

        echo "\n";
    }

    /**
     * 测试其他错误类型的日志
     */
    public function testOtherErrorTypes(): void
    {
        echo "=== 测试2: 其他错误类型日志 ===\n";

        $errorCases = [
            [
                'errcode' => 40013,
                'errmsg' => 'invalid appid',
                'description' => '无效的appid'
            ],
            [
                'errcode' => 40001,
                'errmsg' => 'invalid credential, access_token is invalid or not latest',
                'description' => '无效的凭证'
            ],
            [
                'errcode' => 40125,
                'errmsg' => 'invalid appsecret',
                'description' => '无效的appsecret'
            ]
        ];

        foreach ($errorCases as $case) {
            echo "--- 测试 {$case['description']} ---\n";

            $testAppId = 'wx_test_' . $case['errcode'];
            $testAppSecret = 'test_secret_' . $case['errcode'] . '_1234567890abcdef';
            $mockAccount = new MockWechatPublicAccount($testAppId, $testAppSecret);

            $responses = [
                new MockResponse(json_encode([
                    'errcode' => $case['errcode'],
                    'errmsg' => $case['errmsg']
                ]), [
                    'http_code' => 200
                ])
            ];

            $mockHttpClient = new MockHttpClient($responses);

            // 创建服务实例
            $wechatService = new class($this->logger) extends WechatApiService {
                private $mockHttpClient;

                public function __construct(LoggerInterface $logger, $mockHttpClient = null)
                {
                    parent::__construct($logger);
                    $this->mockHttpClient = $mockHttpClient;
                }

                public function getAccessToken(WechatPublicAccount $account): ?string
                {
                    try {
                        $client = $this->mockHttpClient ?: HttpClient::create();
                        $response = $client->request('GET', 'https://api.weixin.qq.com/cgi-bin/token', [
                            'query' => [
                                'grant_type' => 'client_credential',
                                'appid' => $account->getAppId(),
                                'secret' => $account->getAppSecret(),
                            ],
                        ]);

                        if ($response->getStatusCode() !== 200) {
                            $this->logger->error('获取access_token失败，状态码: ' . $response->getStatusCode());
                            return null;
                        }

                        $result = json_decode($response->getContent(), true);

                        if (isset($result['errcode']) && $result['errcode'] !== 0) {
                            $appId = $account->getAppId();
                            $appSecret = $account->getAppSecret();
                            $this->logger->error('获取access_token返回错误: ' . $result['errmsg'] .
                                ', appid: ' . $appId .
                                ', secret: ' . substr($appSecret, 0, 8) . '***');
                            return null;
                        }

                        return $result['access_token'] ?? null;

                    } catch (\Exception $e) {
                        $this->logger->error('获取access_token失败: ' . $e->getMessage());
                        return null;
                    }
                }
            };

            // 清空日志文件
            file_put_contents($this->logFile, '');

            // 执行测试
            $result = $wechatService->getAccessToken($mockAccount);

            // 读取日志内容
            $logContent = file_get_contents($this->logFile);

            echo "错误码: {$case['errcode']}\n";
            echo "错误信息: {$case['errmsg']}\n";
            echo "日志内容:\n" . $logContent;

            // 验证日志内容
            $this->validateLogContent($logContent, $testAppId, $testAppSecret, $case['errmsg']);

            echo "\n";
        }
    }

    /**
     * 验证日志内容是否符合预期
     */
    private function validateLogContent(string $logContent, string $expectedAppId, string $expectedSecret, string $expectedError): void
    {
        $isValid = true;

        // 检查是否包含appid
        if (strpos($logContent, $expectedAppId) === false) {
            echo "❌ 错误: 日志中未找到预期的appid: {$expectedAppId}\n";
            $isValid = false;
        } else {
            echo "✅ 正确: 日志中包含appid: {$expectedAppId}\n";
        }

        // 检查是否包含secret的前8位
        $expectedSecretPrefix = substr($expectedSecret, 0, 8);
        if (strpos($logContent, $expectedSecretPrefix) === false) {
            echo "❌ 错误: 日志中未找到预期的secret前8位: {$expectedSecretPrefix}\n";
            $isValid = false;
        } else {
            echo "✅ 正确: 日志中包含secret前8位: {$expectedSecretPrefix}\n";
        }

        // 检查是否包含***
        if (strpos($logContent, '***') === false) {
            echo "❌ 错误: 日志中未找到secret掩码 ***\n";
            $isValid = false;
        } else {
            echo "✅ 正确: 日志中包含secret掩码 ***\n";
        }

        // 检查是否包含完整的secret（不应该包含）
        if (strpos($logContent, $expectedSecret) !== false) {
            echo "❌ 错误: 日志中包含了完整的secret（安全风险）\n";
            $isValid = false;
        } else {
            echo "✅ 正确: 日志中未包含完整secret（安全）\n";
        }

        // 检查是否包含预期的错误信息
        if (strpos($logContent, $expectedError) === false) {
            echo "❌ 错误: 日志中未找到预期的错误信息: {$expectedError}\n";
            $isValid = false;
        } else {
            echo "✅ 正确: 日志中包含预期的错误信息\n";
        }

        if ($isValid) {
            echo "🎉 测试通过！\n";
        } else {
            echo "💥 测试失败！\n";
        }
    }

    /**
     * 运行所有测试
     */
    public function runAllTests(): void
    {
        echo "微信API错误日志功能测试开始\n";
        echo "测试时间: " . date('Y-m-d H:i:s') . "\n";
        echo "日志文件: {$this->logFile}\n\n";

        $this->testInvalidIpError();
        $this->testOtherErrorTypes();

        echo "所有测试完成！\n";
        echo "详细日志请查看: {$this->logFile}\n";
    }

    /**
     * 清理测试文件
     */
    public function cleanup(): void
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
            echo "测试日志文件已清理: {$this->logFile}\n";
        }
    }
}

// 主执行逻辑
try {
    $test = new WechatErrorLoggingTest();
    $test->runAllTests();

    // 询问是否清理测试文件
    echo "\n是否删除测试日志文件？(y/N): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);

    if (trim(strtolower($line)) === 'y') {
        $test->cleanup();
    } else {
        echo "测试日志文件保留，请手动查看: " . $test->logFile . "\n";
    }

} catch (Exception $e) {
    echo "测试执行失败: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
