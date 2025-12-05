<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * 微信API综合连接测试脚本
 *
 * 测试内容包括：
 * 1. Access Token获取
 * 2. 微信API端点连接
 * 3. 网络连接和DNS解析
 * 4. API权限配置
 * 5. SSL证书验证
 */

class ComprehensiveWechatApiTest
{
    private const WECHAT_API_BASE = 'https://api.weixin.qq.com/cgi-bin';
    private const WECHAT_MP_API_BASE = 'https://mp.weixin.qq.com';

    private $client;
    private $testResults = [];
    private $startTime;

    // 从数据库获取的微信账号配置
    private $testAccounts = [
        [
            'name' => '正式账号',
            'app_id' => 'wx9248416064fab130',
            'app_secret' => '60401298c80bcd3cfd8745f117e01b14',
            'is_active' => 1
        ],
        [
            'name' => '测试账号',
            'app_id' => 'test_app_id_001',
            'app_secret' => 'test_app_secret_001',
            'is_active' => 1
        ]
    ];

    public function __construct()
    {
        $this->client = HttpClient::create([
            'timeout' => 30,
            'verify_peer' => true,
            'verify_host' => true
        ]);
        $this->startTime = microtime(true);
    }

    /**
     * 运行所有测试
     */
    public function runAllTests(): array
    {
        echo "=== 微信API综合连接测试开始 ===<br>\n";
        echo "测试时间: " . date('Y-m-d H:i:s') . "<br><br>\n";

        // 1. 网络连接测试
        $this->testNetworkConnectivity();

        // 2. DNS解析测试
        $this->testDnsResolution();

        // 3. SSL证书测试
        $this->testSslCertificates();

        // 4. Access Token获取测试
        $this->testAccessTokenRetrieval();

        // 5. API端点连接测试
        $this->testApiEndpoints();

        // 6. API权限验证
        $this->testApiPermissions();

        // 7. IP白名单测试
        $this->testIpWhitelist();

        // 8. 频率限制测试
        $this->testRateLimiting();

        // 生成测试报告
        return $this->generateTestReport();
    }

    /**
     * 测试网络连接
     */
    private function testNetworkConnectivity(): void
    {
        echo "1. 测试网络连接...<br>\n";

        $endpoints = [
            'api.weixin.qq.com' => '微信API服务器',
            'mp.weixin.qq.com' => '微信公众号平台',
            'res.wx.qq.com' => '微信资源服务器'
        ];

        foreach ($endpoints as $host => $description) {
            $startTime = microtime(true);

            try {
                $response = $this->client->request('GET', "https://{$host}", [
                    'max_redirects' => 0
                ]);

                $statusCode = $response->getStatusCode();
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);

                $this->testResults['network_connectivity'][$host] = [
                    'status' => $statusCode >= 200 && $statusCode < 400 ? 'SUCCESS' : 'FAILED',
                    'status_code' => $statusCode,
                    'response_time_ms' => $responseTime,
                    'description' => $description,
                    'error' => null
                ];

                echo "   ✓ {$description} ({$host}): {$statusCode} - {$responseTime}ms<br>\n";

            } catch (TransportExceptionInterface $e) {
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                $this->testResults['network_connectivity'][$host] = [
                    'status' => 'FAILED',
                    'status_code' => null,
                    'response_time_ms' => $responseTime,
                    'description' => $description,
                    'error' => $e->getMessage()
                ];

                echo "   ✗ {$description} ({$host}): 连接失败 - {$e->getMessage()}<br>\n";
            }
        }
        echo "<br>\n";
    }

    /**
     * 测试DNS解析
     */
    private function testDnsResolution(): void
    {
        echo "2. 测试DNS解析...<br>\n";

        $hosts = [
            'api.weixin.qq.com',
            'mp.weixin.qq.com',
            'res.wx.qq.com'
        ];

        foreach ($hosts as $host) {
            $startTime = microtime(true);

            try {
                $records = dns_get_record($host, DNS_A + DNS_AAAA);
                $resolutionTime = round((microtime(true) - $startTime) * 1000, 2);

                if (!empty($records)) {
                    $ipAddresses = array_map(function($record) {
                        return $record['ip'] ?? $record['ipv6'] ?? '';
                    }, $records);

                    $this->testResults['dns_resolution'][$host] = [
                        'status' => 'SUCCESS',
                        'resolution_time_ms' => $resolutionTime,
                        'ip_addresses' => $ipAddresses,
                        'error' => null
                    ];

                    echo "   ✓ {$host}: " . implode(', ', $ipAddresses) . " - {$resolutionTime}ms<br>\n";
                } else {
                    $this->testResults['dns_resolution'][$host] = [
                        'status' => 'FAILED',
                        'resolution_time_ms' => $resolutionTime,
                        'ip_addresses' => [],
                        'error' => 'No DNS records found'
                    ];

                    echo "   ✗ {$host}: 无DNS记录 - {$resolutionTime}ms<br>\n";
                }

            } catch (Exception $e) {
                $resolutionTime = round((microtime(true) - $startTime) * 1000, 2);
                $this->testResults['dns_resolution'][$host] = [
                    'status' => 'FAILED',
                    'resolution_time_ms' => $resolutionTime,
                    'ip_addresses' => [],
                    'error' => $e->getMessage()
                ];

                echo "   ✗ {$host}: DNS解析失败 - {$e->getMessage()}<br>\n";
            }
        }
        echo "<br>\n";
    }

    /**
     * 测试SSL证书
     */
    private function testSslCertificates(): void
    {
        echo "3. 测试SSL证书...<br>\n";

        $hosts = [
            'api.weixin.qq.com',
            'mp.weixin.qq.com'
        ];

        foreach ($hosts as $host) {
            try {
                $context = stream_context_create([
                    'ssl' => [
                        'capture_peer_cert' => true,
                        'capture_peer_chain' => true,
                        'verify_peer' => true,
                        'verify_peer_name' => true
                    ]
                ]);

                $stream = stream_socket_client(
                    "ssl://{$host}:443",
                    $errno,
                    $errstr,
                    10,
                    STREAM_CLIENT_CONNECT,
                    $context
                );

                if ($stream) {
                    $cert = stream_context_get_params($context)['options']['ssl']['peer_certificate'];
                    $certInfo = openssl_x509_parse($cert);

                    $validTo = date('Y-m-d H:i:s', $certInfo['validTo_time_t']);
                    $validFrom = date('Y-m-d H:i:s', $certInfo['validFrom_time_t']);
                    $issuer = $certInfo['issuer']['CN'] ?? 'Unknown';
                    $subject = $certInfo['subject']['CN'] ?? 'Unknown';

                    $isValid = $certInfo['validTo_time_t'] > time();

                    $this->testResults['ssl_certificates'][$host] = [
                        'status' => $isValid ? 'SUCCESS' : 'WARNING',
                        'subject' => $subject,
                        'issuer' => $issuer,
                        'valid_from' => $validFrom,
                        'valid_to' => $validTo,
                        'days_until_expiry' => intval(($certInfo['validTo_time_t'] - time()) / 86400),
                        'error' => null
                    ];

                    echo "   ✓ {$host}: {$subject} (颁发者: {$issuer})<br>\n";
                    echo "     有效期: {$validFrom} 至 {$validTo}<br>\n";

                    if (!$isValid) {
                        echo "     ⚠️ 证书已过期<br>\n";
                    }

                    fclose($stream);
                } else {
                    $this->testResults['ssl_certificates'][$host] = [
                        'status' => 'FAILED',
                        'subject' => null,
                        'issuer' => null,
                        'valid_from' => null,
                        'valid_to' => null,
                        'days_until_expiry' => null,
                        'error' => $errstr
                    ];

                    echo "   ✗ {$host}: SSL连接失败 - {$errstr}<br>\n";
                }

            } catch (Exception $e) {
                $this->testResults['ssl_certificates'][$host] = [
                    'status' => 'FAILED',
                    'subject' => null,
                    'issuer' => null,
                    'valid_from' => null,
                    'valid_to' => null,
                    'days_until_expiry' => null,
                    'error' => $e->getMessage()
                ];

                echo "   ✗ {$host}: SSL证书检查失败 - {$e->getMessage()}<br>\n";
            }
        }
        echo "<br>\n";
    }

    /**
     * 测试Access Token获取
     */
    private function testAccessTokenRetrieval(): void
    {
        echo "4. 测试Access Token获取...<br>\n";

        foreach ($this->testAccounts as $account) {
            echo "   测试账号: {$account['name']} (AppID: {$account['app_id']})<br>\n";

            $startTime = microtime(true);

            try {
                $response = $this->client->request('GET', self::WECHAT_API_BASE . '/token', [
                    'query' => [
                        'grant_type' => 'client_credential',
                        'appid' => $account['app_id'],
                        'secret' => $account['app_secret'],
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                $content = $response->getContent();
                $result = json_decode($content, true);

                if ($statusCode === 200 && isset($result['access_token'])) {
                    $this->testResults['access_token'][$account['app_id']] = [
                        'status' => 'SUCCESS',
                        'access_token' => substr($result['access_token'], 0, 20) . '...',
                        'expires_in' => $result['expires_in'] ?? null,
                        'response_time_ms' => $responseTime,
                        'error' => null,
                        'full_response' => $result
                    ];

                    echo "     ✓ Access Token获取成功 - {$responseTime}ms<br>\n";
                    echo "       Token有效期: " . ($result['expires_in'] ?? 'N/A') . " 秒<br>\n";

                } else {
                    $errorCode = $result['errcode'] ?? 'UNKNOWN';
                    $errorMessage = $result['errmsg'] ?? 'Unknown error';

                    $this->testResults['access_token'][$account['app_id']] = [
                        'status' => 'FAILED',
                        'access_token' => null,
                        'expires_in' => null,
                        'response_time_ms' => $responseTime,
                        'error' => "错误码: {$errorCode}, 错误信息: {$errorMessage}",
                        'full_response' => $result
                    ];

                    echo "     ✗ Access Token获取失败 - {$responseTime}ms<br>\n";
                    echo "       错误码: {$errorCode}<br>\n";
                    echo "       错误信息: {$errorMessage}<br>\n";
                }

            } catch (TransportExceptionInterface $e) {
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);

                $this->testResults['access_token'][$account['app_id']] = [
                    'status' => 'FAILED',
                    'access_token' => null,
                    'expires_in' => null,
                    'response_time_ms' => $responseTime,
                    'error' => $e->getMessage(),
                    'full_response' => null
                ];

                echo "     ✗ Access Token获取网络错误 - {$responseTime}ms<br>\n";
                echo "       错误信息: {$e->getMessage()}<br>\n";
            }
        }
        echo "<br>\n";
    }

    /**
     * 测试API端点连接
     */
    private function testApiEndpoints(): void
    {
        echo "5. 测试API端点连接...<br>\n";

        // 使用第一个成功获取的token进行测试
        $validToken = null;
        foreach ($this->testResults['access_token'] ?? [] as $appId => $result) {
            if ($result['status'] === 'SUCCESS') {
                $validToken = $result['full_response']['access_token'];
                break;
            }
        }

        if (!$validToken) {
            echo "   ⚠️ 没有可用的Access Token，跳过API端点测试<br><br>\n";
            return;
        }

        $endpoints = [
            [
                'name' => '获取素材列表',
                'method' => 'POST',
                'url' => self::WECHAT_API_BASE . '/material/batchget_material',
                'data' => [
                    'type' => 'news',
                    'offset' => 0,
                    'count' => 1
                ]
            ],
            [
                'name' => '获取已发布消息',
                'method' => 'POST',
                'url' => self::WECHAT_API_BASE . '/freepublish/batchget',
                'data' => [
                    'offset' => 0,
                    'count' => 1,
                    'no_content' => 1
                ]
            ],
            [
                'name' => '获取草稿箱',
                'method' => 'POST',
                'url' => self::WECHAT_API_BASE . '/draft/batchget',
                'data' => [
                    'offset' => 0,
                    'count' => 1
                ]
            ]
        ];

        foreach ($endpoints as $endpoint) {
            echo "   测试端点: {$endpoint['name']}<br>\n";

            $startTime = microtime(true);

            try {
                $options = [
                    'query' => ['access_token' => $validToken]
                ];

                if ($endpoint['method'] === 'POST' && isset($endpoint['data'])) {
                    $options['json'] = $endpoint['data'];
                }

                $response = $this->client->request($endpoint['method'], $endpoint['url'], $options);

                $statusCode = $response->getStatusCode();
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                $content = $response->getContent();
                $result = json_decode($content, true);

                if ($statusCode === 200 && (!isset($result['errcode']) || $result['errcode'] === 0)) {
                    $this->testResults['api_endpoints'][$endpoint['name']] = [
                        'status' => 'SUCCESS',
                        'status_code' => $statusCode,
                        'response_time_ms' => $responseTime,
                        'error' => null,
                        'response_summary' => $this->summarizeResponse($result)
                    ];

                    echo "     ✓ {$endpoint['name']} - {$statusCode} - {$responseTime}ms<br>\n";
                } else {
                    $errorCode = $result['errcode'] ?? 'UNKNOWN';
                    $errorMessage = $result['errmsg'] ?? 'Unknown error';

                    $this->testResults['api_endpoints'][$endpoint['name']] = [
                        'status' => 'FAILED',
                        'status_code' => $statusCode,
                        'response_time_ms' => $responseTime,
                        'error' => "错误码: {$errorCode}, 错误信息: {$errorMessage}",
                        'response_summary' => $result
                    ];

                    echo "     ✗ {$endpoint['name']} - {$statusCode} - {$responseTime}ms<br>\n";
                    echo "       错误码: {$errorCode}<br>\n";
                    echo "       错误信息: {$errorMessage}<br>\n";
                }

            } catch (TransportExceptionInterface $e) {
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);

                $this->testResults['api_endpoints'][$endpoint['name']] = [
                    'status' => 'FAILED',
                    'status_code' => null,
                    'response_time_ms' => $responseTime,
                    'error' => $e->getMessage(),
                    'response_summary' => null
                ];

                echo "     ✗ {$endpoint['name']} - 网络错误 - {$responseTime}ms<br>\n";
                echo "       错误信息: {$e->getMessage()}<br>\n";
            }
        }
        echo "<br>\n";
    }

    /**
     * 测试API权限
     */
    private function testApiPermissions(): void
    {
        echo "6. 测试API权限配置...<br>\n";

        $validToken = null;
        foreach ($this->testResults['access_token'] ?? [] as $appId => $result) {
            if ($result['status'] === 'SUCCESS') {
                $validToken = $result['full_response']['access_token'];
                break;
            }
        }

        if (!$validToken) {
            echo "   ⚠️ 没有可用的Access Token，跳过权限测试<br><br>\n";
            return;
        }

        // 测试需要特殊权限的API
        $permissionTests = [
            [
                'name' => '获取用户列表',
                'url' => self::WECHAT_API_BASE . '/user/get',
                'permission' => '用户管理权限'
            ],
            [
                'name' => '获取自定义菜单',
                'url' => self::WECHAT_API_BASE . '/menu/get',
                'permission' => '菜单管理权限'
            ],
            [
                'name' => '获取公众号信息',
                'url' => self::WECHAT_API_BASE . '/account/getaccountbasicinfo',
                'permission' => '账号信息权限'
            ]
        ];

        foreach ($permissionTests as $test) {
            echo "   测试权限: {$test['name']}<br>\n";

            $startTime = microtime(true);

            try {
                $response = $this->client->request('GET', $test['url'], [
                    'query' => ['access_token' => $validToken]
                ]);

                $statusCode = $response->getStatusCode();
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                $content = $response->getContent();
                $result = json_decode($content, true);

                $errorCode = $result['errcode'] ?? 0;
                $errorMessage = $result['errmsg'] ?? '';

                // 判断权限状态
                if ($errorCode === 0) {
                    $status = 'GRANTED';
                    $message = '权限已授权';
                } elseif (in_array($errorCode, [48001, 48002, 48003, 48004, 48005, 48006])) {
                    $status = 'DENIED';
                    $message = '权限未授权';
                } else {
                    $status = 'UNKNOWN';
                    $message = "未知错误: {$errorMessage}";
                }

                $this->testResults['api_permissions'][$test['name']] = [
                    'status' => $status,
                    'permission_type' => $test['permission'],
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'response_time_ms' => $responseTime,
                    'details' => $message
                ];

                $icon = ($status === 'GRANTED') ? '✓' : (($status === 'DENIED') ? '✗' : '?');
                echo "     {$icon} {$test['name']}: {$message}<br>\n";
                if ($errorCode !== 0) {
                    echo "       错误码: {$errorCode}, 错误信息: {$errorMessage}<br>\n";
                }

            } catch (TransportExceptionInterface $e) {
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);

                $this->testResults['api_permissions'][$test['name']] = [
                    'status' => 'FAILED',
                    'permission_type' => $test['permission'],
                    'error_code' => null,
                    'error_message' => $e->getMessage(),
                    'response_time_ms' => $responseTime,
                    'details' => '网络错误'
                ];

                echo "     ✗ {$test['name']}: 网络错误 - {$e->getMessage()}<br>\n";
            }
        }
        echo "<br>\n";
    }

    /**
     * 测试IP白名单
     */
    private function testIpWhitelist(): void
    {
        echo "7. 测试IP白名单配置...<br>\n";

        // 获取当前服务器的外网IP
        $currentIp = $this->getServerPublicIp();
        echo "   当前服务器外网IP: {$currentIp}<br>\n";

        $validToken = null;
        foreach ($this->testResults['access_token'] ?? [] as $appId => $result) {
            if ($result['status'] === 'SUCCESS') {
                $validToken = $result['full_response']['access_token'];
                break;
            }
        }

        if (!$validToken) {
            echo "   ⚠️ 没有可用的Access Token，无法测试IP白名单<br><br>\n";
            return;
        }

        // 通过API调用结果判断IP白名单状态
        // 如果IP不在白名单中，会返回特定的错误码
        $startTime = microtime(true);

        try {
            $response = $this->client->request('GET', self::WECHAT_API_BASE . '/getcallbackip', [
                'query' => ['access_token' => $validToken]
            ]);

            $statusCode = $response->getStatusCode();
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            $content = $response->getContent();
            $result = json_decode($content, true);

            if ($statusCode === 200 && isset($result['ip_list'])) {
                $this->testResults['ip_whitelist'] = [
                    'status' => 'SUCCESS',
                    'current_server_ip' => $currentIp,
                    'wechat_server_ips' => $result['ip_list'],
                    'response_time_ms' => $responseTime,
                    'error' => null,
                    'note' => '当前IP可以访问微信API，可能已在白名单中'
                ];

                echo "   ✓ IP白名单检查通过 - {$responseTime}ms<br>\n";
                echo "     微信服务器IP列表: " . implode(', ', array_slice($result['ip_list'], 0, 5)) . "...<br>\n";

            } else {
                $errorCode = $result['errcode'] ?? 'UNKNOWN';
                $errorMessage = $result['errmsg'] ?? 'Unknown error';

                $this->testResults['ip_whitelist'] = [
                    'status' => 'FAILED',
                    'current_server_ip' => $currentIp,
                    'wechat_server_ips' => null,
                    'response_time_ms' => $responseTime,
                    'error' => "错误码: {$errorCode}, 错误信息: {$errorMessage}",
                    'note' => null
                ];

                echo "   ✗ IP白名单检查失败 - {$responseTime}ms<br>\n";
                echo "     错误码: {$errorCode}, 错误信息: {$errorMessage}<br>\n";
            }

        } catch (TransportExceptionInterface $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->testResults['ip_whitelist'] = [
                'status' => 'FAILED',
                'current_server_ip' => $currentIp,
                'wechat_server_ips' => null,
                'response_time_ms' => $responseTime,
                'error' => $e->getMessage(),
                'note' => null
            ];

            echo "   ✗ IP白名单检查网络错误 - {$responseTime}ms<br>\n";
            echo "     错误信息: {$e->getMessage()}<br>\n";
        }
        echo "<br>\n";
    }

    /**
     * 测试频率限制
     */
    private function testRateLimiting(): void
    {
        echo "8. 测试API频率限制...<br>\n";

        $validToken = null;
        foreach ($this->testResults['access_token'] ?? [] as $appId => $result) {
            if ($result['status'] === 'SUCCESS') {
                $validToken = $result['full_response']['access_token'];
                break;
            }
        }

        if (!$validToken) {
            echo "   ⚠️ 没有可用的Access Token，跳过频率限制测试<br><br>\n";
            return;
        }

        // 快速连续调用API，测试频率限制
        $callCount = 5;
        $successCount = 0;
        $rateLimitHits = 0;
        $totalTime = 0;

        echo "   快速连续调用API {$callCount} 次...<br>\n";

        for ($i = 1; $i <= $callCount; $i++) {
            $startTime = microtime(true);

            try {
                $response = $this->client->request('GET', self::WECHAT_API_BASE . '/getcallbackip', [
                    'query' => ['access_token' => $validToken]
                ]);

                $statusCode = $response->getStatusCode();
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                $totalTime += $responseTime;

                $content = $response->getContent();
                $result = json_decode($content, true);

                if ($statusCode === 200 && (!isset($result['errcode']) || $result['errcode'] === 0)) {
                    $successCount++;
                    echo "     调用 {$i}: ✓ 成功 - {$responseTime}ms<br>\n";
                } else {
                    $errorCode = $result['errcode'] ?? 'UNKNOWN';
                    $errorMessage = $result['errmsg'] ?? 'Unknown error';

                    // 检查是否为频率限制错误
                    if (in_array($errorCode, [45009, 45010, 10001, 10002])) {
                        $rateLimitHits++;
                        echo "     调用 {$i}: ⚠️ 频率限制 - 错误码: {$errorCode}, {$responseTime}ms<br>\n";
                    } else {
                        echo "     调用 {$i}: ✗ 其他错误 - 错误码: {$errorCode}, {$responseTime}ms<br>\n";
                    }
                }

            } catch (TransportExceptionInterface $e) {
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                $totalTime += $responseTime;
                echo "     调用 {$i}: ✗ 网络错误 - {$responseTime}ms<br>\n";
            }

            // 短暂延迟
            usleep(100000); // 0.1秒
        }

        $avgResponseTime = round($totalTime / $callCount, 2);

        $this->testResults['rate_limiting'] = [
            'total_calls' => $callCount,
            'successful_calls' => $successCount,
            'rate_limit_hits' => $rateLimitHits,
            'success_rate' => round(($successCount / $callCount) * 100, 2),
            'average_response_time_ms' => $avgResponseTime,
            'status' => $rateLimitHits > 0 ? 'RATE_LIMITED' : 'NORMAL'
        ];

        echo "   频率限制测试结果:<br>\n";
        echo "     总调用次数: {$callCount}<br>\n";
        echo "     成功次数: {$successCount}<br>\n";
        echo "     频率限制次数: {$rateLimitHits}<br>\n";
        echo "     成功率: " . round(($successCount / $callCount) * 100, 2) . "%<br>\n";
        echo "     平均响应时间: {$avgResponseTime}ms<br>\n";
        echo "<br>\n";
    }

    /**
     * 获取服务器公网IP
     */
    private function getServerPublicIp(): string
    {
        try {
            $response = $this->client->request('GET', 'https://httpbin.org/ip', [
                'timeout' => 5
            ]);
            $content = $response->getContent();
            $result = json_decode($content, true);
            return $result['origin'] ?? 'Unknown';
        } catch (Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * 总结API响应
     */
    private function summarizeResponse(array $result): array
    {
        $summary = [];

        if (isset($result['errcode'])) {
            $summary['error_code'] = $result['errcode'];
        }

        if (isset($result['errmsg'])) {
            $summary['error_message'] = $result['errmsg'];
        }

        if (isset($result['item'])) {
            $summary['item_count'] = count($result['item']);
        }

        if (isset($result['total_count'])) {
            $summary['total_count'] = $result['total_count'];
        }

        return $summary;
    }

    /**
     * 生成测试报告
     */
    private function generateTestReport(): array
    {
        $totalTime = round((microtime(true) - $this->startTime) * 1000, 2);

        // 统计测试结果
        $totalTests = 0;
        $passedTests = 0;
        $failedTests = 0;
        $warnings = 0;

        foreach ($this->testResults as $category => $tests) {
            if (is_array($tests)) {
                foreach ($tests as $test) {
                    if (isset($test['status'])) {
                        $totalTests++;
                        switch ($test['status']) {
                            case 'SUCCESS':
                            case 'GRANTED':
                            case 'NORMAL':
                                $passedTests++;
                                break;
                            case 'FAILED':
                            case 'DENIED':
                            case 'RATE_LIMITED':
                                $failedTests++;
                                break;
                            case 'WARNING':
                                $warnings++;
                                break;
                        }
                    }
                }
            }
        }

        $report = [
            'test_summary' => [
                'total_tests' => $totalTests,
                'passed_tests' => $passedTests,
                'failed_tests' => $failedTests,
                'warnings' => $warnings,
                'success_rate' => $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0,
                'total_execution_time_ms' => $totalTime,
                'test_date' => date('Y-m-d H:i:s')
            ],
            'test_results' => $this->testResults,
            'recommendations' => $this->generateRecommendations()
        ];

        // 输出测试摘要
        echo "=== 测试完成 ===<br>\n";
        echo "总测试数: {$totalTests}<br>\n";
        echo "通过: {$passedTests}<br>\n";
        echo "失败: {$failedTests}<br>\n";
        echo "警告: {$warnings}<br>\n";
        echo "成功率: {$report['test_summary']['success_rate']}%<br>\n";
        echo "总执行时间: {$totalTime}ms<br>\n";
        echo "<br>\n";

        return $report;
    }

    /**
     * 生成修复建议
     */
    private function generateRecommendations(): array
    {
        $recommendations = [];

        // 检查Access Token问题
        foreach ($this->testResults['access_token'] ?? [] as $appId => $result) {
            if ($result['status'] === 'FAILED') {
                if (strpos($result['error'], 'invalid appid') !== false) {
                    $recommendations[] = "AppID {$appId} 无效，请检查公众号配置";
                } elseif (strpos($result['error'], 'invalid appsecret') !== false) {
                    $recommendations[] = "AppSecret 错误，请检查 {$appId} 的AppSecret配置";
                } elseif (strpos($result['error'], 'connect') !== false) {
                    $recommendations[] = "网络连接失败，请检查网络连接和防火墙设置";
                }
            }
        }

        // 检查网络连接问题
        foreach ($this->testResults['network_connectivity'] ?? [] as $host => $result) {
            if ($result['status'] === 'FAILED') {
                $recommendations[] = "无法连接到 {$host}，请检查DNS解析和网络连接";
            }
        }

        // 检查SSL证书问题
        foreach ($this->testResults['ssl_certificates'] ?? [] as $host => $result) {
            if ($result['status'] === 'FAILED') {
                $recommendations[] = "{$host} 的SSL证书验证失败，请检查系统时间和证书配置";
            } elseif ($result['status'] === 'WARNING' && ($result['days_until_expiry'] ?? 30) < 7) {
                $recommendations[] = "{$host} 的SSL证书即将过期，请及时更新";
            }
        }

        // 检查API权限问题
        foreach ($this->testResults['api_permissions'] ?? [] as $test => $result) {
            if ($result['status'] === 'DENIED') {
                $recommendations[] = "缺少 {$result['permission_type']}，请在微信公众平台开启相应权限";
            }
        }

        // 检查IP白名单问题
        if (isset($this->testResults['ip_whitelist']) && $this->testResults['ip_whitelist']['status'] === 'FAILED') {
            $recommendations[] = "当前服务器IP可能不在微信API白名单中，请在微信公众平台添加IP白名单";
        }

        // 检查频率限制问题
        if (isset($this->testResults['rate_limiting']) && $this->testResults['rate_limiting']['status'] === 'RATE_LIMITED') {
            $recommendations[] = "API调用达到频率限制，请优化调用策略或申请提高频率限制";
        }

        return $recommendations;
    }
}

// 运行测试
$test = new ComprehensiveWechatApiTest();
$report = $test->runAllTests();

// 尝试保存测试报告，如果失败则直接输出
$reportFile = __DIR__ . '/wechat_api_connection_test_report_' . date('Ymd_His') . '.json';
$saved = @file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

if ($saved) {
    echo "详细测试报告已保存到: {$reportFile}<br>\n";
} else {
    echo "无法保存报告文件，直接输出测试结果：<br>\n";
    echo "<pre>" . json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>\n";
}

// 输出修复建议
if (!empty($report['recommendations'])) {
    echo "<br>=== 修复建议 ===<br>\n";
    foreach ($report['recommendations'] as $index => $recommendation) {
        echo ($index + 1) . ". {$recommendation}<br>\n";
    }
} else {
    echo "<br>✅ 所有测试通过，无需修复<br>\n";
}
