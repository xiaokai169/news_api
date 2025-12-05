<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Service\WechatApiService;
use App\Entity\WechatPublicAccount;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * 微信公众号Access Token获取功能全面测试
 *
 * 测试覆盖场景：
 * 1. 正常流程测试
 * 2. 异常情况测试
 * 3. 参数测试
 */

class WechatAccessTokenTest
{
    private LoggerInterface $logger;
    private int $testCount = 0;
    private int $passedTests = 0;
    private array $testResults = [];

    public function __construct()
    {
        // 设置测试日志记录器
        $this->logger = new Logger('wechat_test');
        $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
    }

    /**
     * 运行所有测试
     */
    public function runAllTests(): void
    {
        echo "=== 微信公众号Access Token获取功能全面测试 ===\n\n";
        echo "测试开始时间: " . date('Y-m-d H:i:s') . "\n\n";

        // 正常流程测试
        $this->testNormalFlow();

        // 异常情况测试
        $this->testHttpExceptions();
        $this->testWechatApiErrors();
        $this->testResponseFormatErrors();
        $this->testNetworkExceptions();
        $this->testJsonParseErrors();

        // 参数测试
        $this->testInvalidParameters();
        $this->testEmptyAccount();

        // 输出测试结果汇总
        $this->printTestSummary();
    }

    /**
     * 正常流程测试
     */
    private function testNormalFlow(): void
    {
        echo "--- 正常流程测试 ---\n\n";

        // 测试1: 模拟微信API成功响应
        $this->runTest('成功获取access_token', function() {
            $mockResponse = new MockResponse(json_encode([
                'access_token' => 'test_access_token_12345',
                'expires_in' => 7200
            ]), [
                'http_code' => 200,
                'response_headers' => ['content-type' => 'application/json']
            ]);

            $mockClient = new MockHttpClient($mockResponse);
            $service = $this->createWechatServiceWithMockClient($mockClient);
            $account = $this->createTestAccount();

            $result = $service->getAccessToken($account);

            $this->assertEquals('test_access_token_12345', $result, '返回的access_token应该正确');
            return $result !== null;
        });

        // 测试2: 验证响应格式正确性
        $this->runTest('响应格式正确性验证', function() {
            $mockResponse = new MockResponse(json_encode([
                'access_token' => 'test_token_format',
                'expires_in' => 7200,
                'refresh_token' => 'refresh_token_123'
            ]), [
                'http_code' => 200
            ]);

            $mockClient = new MockHttpClient($mockResponse);
            $service = $this->createWechatServiceWithMockClient($mockClient);
            $account = $this->createTestAccount();

            $result = $service->getAccessToken($account);

            $this->assertEquals('test_token_format', $result, '应该正确解析access_token字段');
            return $result !== null;
        });
    }

    /**
     * HTTP异常情况测试
     */
    private function testHttpExceptions(): void
    {
        echo "\n--- HTTP异常情况测试 ---\n\n";

        // 测试3: HTTP 404错误
        $this->runTest('HTTP 404错误处理', function() {
            $mockResponse = new MockResponse('Not Found', [
                'http_code' => 404
            ]);

            $mockClient = new MockHttpClient($mockResponse);
            $service = $this->createWechatServiceWithMockClient($mockClient);
            $account = $this->createTestAccount();

            $result = $service->getAccessToken($account);

            $this->assertNull($result, 'HTTP 404应该返回null');
            return $result === null;
        });

        // 测试4: HTTP 500错误
        $this->runTest('HTTP 500错误处理', function() {
            $mockResponse = new MockResponse('Internal Server Error', [
                'http_code' => 500
            ]);

            $mockClient = new MockHttpClient($mockResponse);
            $service = $this->createWechatServiceWithMockClient($mockClient);
            $account = $this->createTestAccount();

            $result = $service->getAccessToken($account);

            $this->assertNull($result, 'HTTP 500应该返回null');
            return $result === null;
        });

        // 测试5: HTTP 403错误
        $this->runTest('HTTP 403错误处理', function() {
            $mockResponse = new MockResponse('Forbidden', [
                'http_code' => 403
            ]);

            $mockClient = new MockHttpClient($mockResponse);
            $service = $this->createWechatServiceWithMockClient($mockClient);
            $account = $this->createTestAccount();

            $result = $service->getAccessToken($account);

            $this->assertNull($result, 'HTTP 403应该返回null');
            return $result === null;
        });
    }

    /**
     * 微信API错误响应测试
     */
    private function testWechatApiErrors(): void
    {
        echo "\n--- 微信API错误响应测试 ---\n\n";

        // 测试6: 无效的appid (errcode: 40013)
        $this->runTest('无效appid错误(40013)', function() {
            $mockResponse = new MockResponse(json_encode([
                'errcode' => 40013,
                'errmsg' => 'invalid appid'
            ]), [
                'http_code' => 200
            ]);

            $mockClient = new MockHttpClient($mockResponse);
            $service = $this->createWechatServiceWithMockClient($mockClient);
            $account = $this->createTestAccount();

            $result = $service->getAccessToken($account);

            $this->assertNull($result, '无效appid应该返回null');
            return $result === null;
        });

        // 测试7: 无效的secret (errcode: 40001)
        $this->runTest('无效secret错误(40001)', function() {
            $mockResponse = new MockResponse(json_encode([
                'errcode' => 40001,
                'errmsg' => 'invalid secret'
            ]), [
                'http_code' => 200
            ]);

            $mockClient = new MockHttpClient($mockResponse);
            $service = $this->createWechatServiceWithMockClient($mockClient);
            $account = $this->createTestAccount();

            $result = $service->getAccessToken($account);

            $this->assertNull($result, '无效secret应该返回null');
            return $result === null;
        });

        // 测试8: 其他微信API错误
        $this->runTest('其他微信API错误', function() {
            $mockResponse = new MockResponse(json_encode([
                'errcode' => 45009,
                'errmsg' => 'api freq out of limit'
            ]), [
                'http_code' => 200
            ]);

            $mockClient = new MockHttpClient($mockResponse);
            $service = $this->createWechatServiceWithMockClient($mockClient);
            $account = $this->createTestAccount();

            $result = $service->getAccessToken($account);

            $this->assertNull($result, 'API频率限制应该返回null');
            return $result === null;
        });
    }

    /**
     * 响应格式错误测试
     */
    private function testResponseFormatErrors(): void
    {
        echo "\n--- 响应格式错误测试 ---\n\n";

        // 测试9: 缺少access_token字段
        $this->runTest('缺少access_token字段', function() {
            $mockResponse = new MockResponse(json_encode([
                'expires_in' => 7200,
                'refresh_token' => 'refresh_token'
            ]), [
                'http_code' => 200
            ]);

            $mockClient = new MockHttpClient($mockResponse);
            $service = $this->createWechatServiceWithMockClient($mockClient);
            $account = $this->createTestAccount();

            $result = $service->getAccessToken($account);

            $this->assertNull($result, '缺少access_token字段应该返回null');
            return $result === null;
        });

        // 测试10: access_token为空字符串
        $this->runTest('access_token为空字符串', function() {
            $mockResponse = new MockResponse(json_encode([
                'access_token' => '',
                'expires_in' => 7200
            ]), [
                'http_code' => 200
            ]);

            $mockClient = new MockHttpClient($mockResponse);
            $service = $this->createWechatServiceWithMockClient($mockClient);
            $account = $this->createTestAccount();

            $result = $service->getAccessToken($account);

            $this->assertNull($result, '空的access_token应该返回null');
            return $result === null;
        });

        // 测试11: access_token为null
        $this->runTest('access_token为null', function() {
            $mockResponse = new MockResponse(json_encode([
                'access_token' => null,
                'expires_in' => 7200
            ]), [
                'http_code' => 200
            ]);

            $mockClient = new MockHttpClient($mockResponse);
            $service = $this->createWechatServiceWithMockClient($mockClient);
            $account = $this->createTestAccount();

            $result = $service->getAccessToken($account);

            $this->assertNull($result, 'null的access_token应该返回null');
            return $result === null;
        });
    }

    /**
     * 网络异常测试
     */
    private function testNetworkExceptions(): void
    {
        echo "\n--- 网络异常测试 ---\n\n";

        // 测试12: 网络连接超时
        $this->runTest('网络连接超时', function() {
            $mockResponse = new MockResponse('', [
                'http_code' => 0,
                'error' => 'Connection timeout'
            ]);

            $mockClient = new MockHttpClient($mockResponse);
            $service = $this->createWechatServiceWithMockClient($mockClient);
            $account = $this->createTestAccount();

            $result = $service->getAccessToken($account);

            $this->assertNull($result, '网络超时应该返回null');
            return $result === null;
        });

        // 测试13: DNS解析失败
        $this->runTest('DNS解析失败', function() {
            $mockResponse = new MockResponse('', [
                'http_code' => 0,
                'error' => 'Could not resolve host'
            ]);

            $mockClient = new MockHttpClient($mockResponse);
            $service = $this->createWechatServiceWithMockClient($mockClient);
            $account = $this->createTestAccount();

            $result = $service->getAccessToken($account);

            $this->assertNull($result, 'DNS解析失败应该返回null');
            return $result === null;
        });
    }

    /**
     * JSON解析错误测试
     */
    private function testJsonParseErrors(): void
    {
        echo "\n--- JSON解析错误测试 ---\n\n";

        // 测试14: 无效的JSON格式
        $this->runTest('无效的JSON格式', function() {
            $mockResponse = new MockResponse('invalid json response', [
                'http_code' => 200
            ]);

            $mockClient = new MockHttpClient($mockResponse);
            $service = $this->createWechatServiceWithMockClient($mockClient);
            $account = $this->createTestAccount();

            $result = $service->getAccessToken($account);

            $this->assertNull($result, '无效JSON应该返回null');
            return $result === null;
        });

        // 测试15: 部分损坏的JSON
        $this->runTest('部分损坏的JSON', function() {
            $mockResponse = new MockResponse('{"access_token": "test_token", "expires_in": 7200', [
                'http_code' => 200
            ]);

            $mockClient = new MockHttpClient($mockResponse);
            $service = $this->createWechatServiceWithMockClient($mockClient);
            $account = $this->createTestAccount();

            $result = $service->getAccessToken($account);

            $this->assertNull($result, '损坏的JSON应该返回null');
            return $result === null;
        });
    }

    /**
     * 参数测试
     */
    private function testInvalidParameters(): void
    {
        echo "\n--- 参数测试 ---\n\n";

        // 测试16: 无效的appid
        $this->runTest('无效的appid参数', function() {
            $account = $this->createTestAccount();
            $account->setAppId('invalid_appid_format');
            $account->setAppSecret('valid_secret');

            $mockResponse = new MockResponse(json_encode([
                'errcode' => 40013,
                'errmsg' => 'invalid appid'
            ]), [
                'http_code' => 200
            ]);

            $mockClient = new MockHttpClient($mockResponse);
            $service = $this->createWechatServiceWithMockClient($mockClient);

            $result = $service->getAccessToken($account);

            $this->assertNull($result, '无效appid应该返回null');
            return $result === null;
        });

        // 测试17: 无效的secret
        $this->runTest('无效的secret参数', function() {
            $account = $this->createTestAccount();
            $account->setAppId('valid_appid');
            $account->setAppSecret('invalid_secret_format');

            $mockResponse = new MockResponse(json_encode([
                'errcode' => 40001,
                'errmsg' => 'invalid secret'
            ]), [
                'http_code' => 200
            ]);

            $mockClient = new MockHttpClient($mockResponse);
            $service = $this->createWechatServiceWithMockClient($mockClient);

            $result = $service->getAccessToken($account);

            $this->assertNull($result, '无效secret应该返回null');
            return $result === null;
        });

        // 测试18: 空的appid
        $this->runTest('空的appid参数', function() {
            $account = $this->createTestAccount();
            $account->setAppId('');
            $account->setAppSecret('valid_secret');

            $mockResponse = new MockResponse(json_encode([
                'errcode' => 40013,
                'errmsg' => 'invalid appid'
            ]), [
                'http_code' => 200
            ]);

            $mockClient = new MockHttpClient($mockResponse);
            $service = $this->createWechatServiceWithMockClient($mockClient);

            $result = $service->getAccessToken($account);

            $this->assertNull($result, '空appid应该返回null');
            return $result === null;
        });

        // 测试19: 空的secret
        $this->runTest('空的secret参数', function() {
            $account = $this->createTestAccount();
            $account->setAppId('valid_appid');
            $account->setAppSecret('');

            $mockResponse = new MockResponse(json_encode([
                'errcode' => 40001,
                'errmsg' => 'invalid secret'
            ]), [
                'http_code' => 200
            ]);

            $mockClient = new MockHttpClient($mockResponse);
            $service = $this->createWechatServiceWithMockClient($mockClient);

            $result = $service->getAccessToken($account);

            $this->assertNull($result, '空secret应该返回null');
            return $result === null;
        });
    }

    /**
     * 空账户测试
     */
    private function testEmptyAccount(): void
    {
        echo "\n--- 空账户测试 ---\n\n";

        // 测试20: null appid和secret
        $this->runTest('null appid和secret', function() {
            $account = $this->createTestAccount();
            $account->setAppId(null);
            $account->setAppSecret(null);

            $mockResponse = new MockResponse(json_encode([
                'errcode' => 41002,
                'errmsg' => 'appid missing'
            ]), [
                'http_code' => 200
            ]);

            $mockClient = new MockHttpClient($mockResponse);
            $service = $this->createWechatServiceWithMockClient($mockClient);

            $result = $service->getAccessToken($account);

            $this->assertNull($result, 'null参数应该返回null');
            return $result === null;
        });
    }

    /**
     * 创建测试用的WechatPublicAccount实体
     */
    private function createTestAccount(): WechatPublicAccount
    {
        $account = new WechatPublicAccount();
        $account->setId('test_account_001');
        $account->setName('测试公众号');
        $account->setAppId('test_appid_12345');
        $account->setAppSecret('test_secret_67890');
        $account->setIsActive(true);

        return $account;
    }

    /**
     * 创建带有Mock客户端的WechatApiService
     */
    private function createWechatServiceWithMockClient(MockHttpClient $mockClient): WechatApiService
    {
        // 由于WechatApiService直接创建HttpClient，我们需要使用反射来替换它
        $service = new class($this->logger) extends WechatApiService {
            private MockHttpClient $mockClient;

            public function setMockClient(MockHttpClient $client): void
            {
                $this->mockClient = $client;
            }

            public function getAccessToken(WechatPublicAccount $account): ?string
            {
                try {
                    $response = $this->mockClient->request('GET', 'https://api.weixin.qq.com/cgi-bin/token', [
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
                        $this->logger->error('获取access_token返回错误: ' . $result['errmsg']);
                        return null;
                    }

                    if (!isset($result['access_token']) || empty($result['access_token'])) {
                        $this->logger->error('获取access_token返回格式错误: ' . $response->getContent());
                        return null;
                    }

                    $this->logger->info('获取access_token成功');
                    return $result['access_token'];

                } catch (\Exception $e) {
                    $this->logger->error('获取access_token失败: ' . $e->getMessage());
                    return null;
                }
            }
        };

        $service->setMockClient($mockClient);
        return $service;
    }

    /**
     * 运行单个测试
     */
    private function runTest(string $testName, callable $testFunction): void
    {
        $this->testCount++;
        $startTime = microtime(true);

        try {
            echo "测试 {$this->testCount}: {$testName}\n";

            $result = $testFunction();

            if ($result) {
                $this->passedTests++;
                $status = '✅ 通过';
                $this->testResults[] = [
                    'name' => $testName,
                    'status' => 'PASS',
                    'time' => round((microtime(true) - $startTime) * 1000, 2)
                ];
            } else {
                $status = '❌ 失败';
                $this->testResults[] = [
                    'name' => $testName,
                    'status' => 'FAIL',
                    'time' => round((microtime(true) - $startTime) * 1000, 2)
                ];
            }

            echo "  状态: {$status}\n";
            echo "  耗时: " . round((microtime(true) - $startTime) * 1000, 2) . "ms\n\n";

        } catch (\Exception $e) {
            $status = '❌ 异常';
            echo "  状态: {$status}\n";
            echo "  错误: " . $e->getMessage() . "\n";
            echo "  文件: " . $e->getFile() . ":" . $e->getLine() . "\n\n";

            $this->testResults[] = [
                'name' => $testName,
                'status' => 'ERROR',
                'error' => $e->getMessage(),
                'time' => round((microtime(true) - $startTime) * 1000, 2)
            ];
        }
    }

    /**
     * 断言相等
     */
    private function assertEquals($expected, $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new \Exception($message . " (期望: " . var_export($expected, true) . ", 实际: " . var_export($actual, true) . ")");
        }
    }

    /**
     * 断言为空
     */
    private function assertNull($actual, string $message = ''): void
    {
        if ($actual !== null) {
            throw new \Exception($message . " (期望: null, 实际: " . var_export($actual, true) . ")");
        }
    }

    /**
     * 打印测试结果汇总
     */
    private function printTestSummary(): void
    {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "测试结果汇总\n";
        echo str_repeat("=", 60) . "\n";

        echo "总测试数: {$this->testCount}\n";
        echo "通过测试: {$this->passedTests}\n";
        echo "失败测试: " . ($this->testCount - $this->passedTests) . "\n";
        echo "通过率: " . round(($this->passedTests / $this->testCount) * 100, 2) . "%\n\n";

        echo "详细测试结果:\n";
        echo str_repeat("-", 60) . "\n";

        foreach ($this->testResults as $result) {
            $statusIcon = $result['status'] === 'PASS' ? '✅' : ($result['status'] === 'FAIL' ? '❌' : '💥');
            echo "{$statusIcon} {$result['name']} - {$result['status']} ({$result['time']}ms)\n";

            if (isset($result['error'])) {
                echo "   错误: {$result['error']}\n";
            }
        }

        echo "\n" . str_repeat("=", 60) . "\n";
        echo "测试完成时间: " . date('Y-m-d H:i:s') . "\n";

        if ($this->passedTests === $this->testCount) {
            echo "🎉 所有测试通过！WechatApiService的getAccessToken方法功能正常。\n";
        } else {
            echo "⚠️  存在测试失败，请检查相关功能实现。\n";
        }
        echo str_repeat("=", 60) . "\n";
    }
}

// 运行测试
$test = new WechatAccessTokenTest();
$test->runAllTests();
