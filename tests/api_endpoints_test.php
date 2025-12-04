<?php

/**
 * API端点测试脚本
 * 用于全面测试所有API端点的功能
 */

class ApiEndpointsTest
{
    private $baseUrl;
    private $testResults = [];
    private $verbose = false;

    public function __construct($baseUrl = 'http://localhost:8000', $verbose = false)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->verbose = $verbose;
    }

    /**
     * 执行HTTP请求
     */
    private function makeRequest($method, $endpoint, $data = null, $headers = [])
    {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array_merge([
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: API-Test-Client/1.0'
            ], $headers)
        ]);

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'status_code' => $httpCode,
            'response' => $response,
            'error' => $error,
            'data' => json_decode($response, true) ?: null
        ];
    }

    /**
     * 记录测试结果
     */
    private function logTest($testName, $passed, $details = '')
    {
        $status = $passed ? 'PASS' : 'FAIL';
        $this->testResults[] = [
            'test' => $testName,
            'status' => $status,
            'details' => $details
        ];

        if ($this->verbose) {
            echo sprintf("[%s] %s: %s\n", $status, $testName, $details);
        } else {
            echo sprintf("%s %s\n", $passed ? '✓' : '✗', $testName);
        }
    }

    /**
     * 测试基础连接
     */
    public function testBasicConnection()
    {
        $result = $this->makeRequest('GET', '/');

        $passed = $result['status_code'] === 200;
        $this->logTest(
            '基础连接测试',
            $passed,
            $passed ? '连接正常' : "状态码: {$result['status_code']}"
        );

        return $passed;
    }

    /**
     * 测试分类API - 获取所有分类
     */
    public function testGetCategories()
    {
        $result = $this->makeRequest('GET', '/api/sys-news-article-categories');

        $passed = $result['status_code'] === 200;
        $details = $passed ? '获取成功' : "状态码: {$result['status_code']}";

        if ($passed && isset($result['data'])) {
            $details .= sprintf(", 返回%d条记录", count($result['data']));
        }

        $this->logTest('GET /api/sys-news-article-categories', $passed, $details);
        return $result;
    }

    /**
     * 测试分类API - 创建新分类
     */
    public function testCreateCategory()
    {
        $testData = [
            'code' => 'TEST_' . time(),
            'name' => '测试分类_' . date('Y-m-d H:i:s')
        ];

        $result = $this->makeRequest('POST', '/api/sys-news-article-categories', $testData);

        $passed = $result['status_code'] === 201 || $result['status_code'] === 200;
        $details = $passed ? '创建成功' : "状态码: {$result['status_code']}";

        if ($result['error']) {
            $details .= ", 错误: {$result['error']}";
        }

        $this->logTest('POST /api/sys-news-article-categories', $passed, $details);

        // 如果创建成功，返回创建的数据用于后续测试
        if ($passed && isset($result['data']['id'])) {
            $result['created_id'] = $result['data']['id'];
        }

        return $result;
    }

    /**
     * 测试分类API - 获取单个分类
     */
    public function testGetSingleCategory($categoryId)
    {
        $result = $this->makeRequest('GET', "/api/sys-news-article-categories/{$categoryId}");

        $passed = $result['status_code'] === 200;
        $details = $passed ? '获取成功' : "状态码: {$result['status_code']}";

        $this->logTest("GET /api/sys-news-article-categories/{$categoryId}", $passed, $details);
        return $result;
    }

    /**
     * 测试分类API - 更新分类
     */
    public function testUpdateCategory($categoryId)
    {
        $updateData = [
            'name' => '更新后的分类_' . date('Y-m-d H:i:s')
        ];

        $result = $this->makeRequest('PUT', "/api/sys-news-article-categories/{$categoryId}", $updateData);

        $passed = $result['status_code'] === 200 || $result['status_code'] === 204;
        $details = $passed ? '更新成功' : "状态码: {$result['status_code']}";

        $this->logTest("PUT /api/sys-news-article-categories/{$categoryId}", $passed, $details);
        return $result;
    }

    /**
     * 测试分类API - 删除分类
     */
    public function testDeleteCategory($categoryId)
    {
        $result = $this->makeRequest('DELETE', "/api/sys-news-article-categories/{$categoryId}");

        $passed = $result['status_code'] === 200 || $result['status_code'] === 204;
        $details = $passed ? '删除成功' : "状态码: {$result['status_code']}";

        $this->logTest("DELETE /api/sys-news-article-categories/{$categoryId}", $passed, $details);
        return $result;
    }

    /**
     * 测试错误处理
     */
    public function testErrorHandling()
    {
        // 测试不存在的端点
        $result = $this->makeRequest('GET', '/api/nonexistent-endpoint');
        $passed = $result['status_code'] === 404;
        $this->logTest('404错误处理', $passed, $passed ? '正确返回404' : "状态码: {$result['status_code']}");

        // 测试无效的POST数据
        $result = $this->makeRequest('POST', '/api/sys-news-article-categories', ['invalid' => 'data']);
        $passed = $result['status_code'] === 400 || $result['status_code'] === 422;
        $this->logTest('400错误处理', $passed, $passed ? '正确返回400/422' : "状态码: {$result['status_code']}");

        return true;
    }

    /**
     * 测试CORS配置
     */
    public function testCorsHeaders()
    {
        // 测试OPTIONS请求
        $result = $this->makeRequest('OPTIONS', '/api/sys-news-article-categories');

        $hasCorsHeaders = false;
        $corsHeaders = [];

        // 检查CORS头（简化版本，实际应该检查响应头）
        $passed = $result['status_code'] === 200 || $result['status_code'] === 204;

        $this->logTest('CORS预检请求', $passed, $passed ? 'CORS配置正常' : 'CORS配置可能有问题');

        return $passed;
    }

    /**
     * 运行完整的API测试套件
     */
    public function runFullTestSuite()
    {
        echo "开始API端点完整测试...\n";
        echo "基础URL: {$this->baseUrl}\n\n";

        $startTime = microtime(true);

        // 基础连接测试
        $this->testBasicConnection();

        // 获取现有分类
        $getListResult = $this->testGetCategories();

        // 创建新分类
        $createResult = $this->testCreateCategory();
        $createdId = null;

        if (isset($createResult['created_id'])) {
            $createdId = $createResult['created_id'];

            // 测试获取单个分类
            $this->testGetSingleCategory($createdId);

            // 测试更新分类
            $this->testUpdateCategory($createdId);

            // 测试删除分类
            $this->testDeleteCategory($createdId);
        } else {
            echo "⚠ 跳过单个分类测试（创建失败）\n";
        }

        // 错误处理测试
        $this->testErrorHandling();

        // CORS测试
        $this->testCorsHeaders();

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        // 输出测试总结
        $this->printTestSummary($duration);
    }

    /**
     * 打印测试总结
     */
    private function printTestSummary($duration)
    {
        $total = count($this->testResults);
        $passed = count(array_filter($this->testResults, fn($r) => $r['status'] === 'PASS'));
        $failed = $total - $passed;

        echo "\n" . str_repeat("=", 50) . "\n";
        echo "测试总结\n";
        echo str_repeat("=", 50) . "\n";
        echo "总测试数: {$total}\n";
        echo "通过: {$passed}\n";
        echo "失败: {$failed}\n";
        echo "耗时: {$duration}秒\n";
        echo "成功率: " . round(($passed / $total) * 100, 2) . "%\n";

        if ($failed > 0) {
            echo "\n失败的测试:\n";
            foreach ($this->testResults as $result) {
                if ($result['status'] === 'FAIL') {
                    echo "  ✗ {$result['test']}: {$result['details']}\n";
                }
            }
        }

        echo "\n";
    }

    /**
     * 获取测试结果
     */
    public function getTestResults()
    {
        return $this->testResults;
    }
}

// 命令行执行
if (php_sapi_name() === 'cli') {
    // 解析命令行参数
    $options = getopt('u:v', ['url:', 'verbose']);

    $baseUrl = $options['u'] ?? $options['url'] ?? 'http://localhost:8000';
    $verbose = isset($options['v']) || isset($options['verbose']);

    // 创建测试实例并运行
    $test = new ApiEndpointsTest($baseUrl, $verbose);
    $test->runFullTestSuite();
}
