<?php

/**
 * 微信文章接口分页参数综合测试脚本
 * 测试新的 page/size 参数格式和向后兼容性
 *
 * 测试覆盖：
 * 1. 新分页参数格式测试
 * 2. 向后兼容性测试
 * 3. 参数优先级测试
 * 4. 边界情况和错误处理
 * 5. 分页计算逻辑验证
 */

class WechatPaginationTest
{
    private $baseUrl = 'https://127.0.0.1:8000';
    private $testResults = [];
    private $totalTests = 0;
    private $passedTests = 0;
    private $failedTests = 0;

    public function __construct()
    {
        echo "=== 微信文章接口分页参数综合测试 ===\n\n";
    }

    /**
     * 执行HTTP请求
     */
    private function makeRequest($url, $description = '')
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => $error,
                'http_code' => 0,
                'response' => null
            ];
        }

        $data = json_decode($response, true);

        return [
            'success' => $httpCode === 200,
            'http_code' => $httpCode,
            'response' => $data,
            'raw_response' => $response
        ];
    }

    /**
     * 记录测试结果
     */
    private function recordTest($testName, $passed, $details = '', $response = null)
    {
        $this->totalTests++;
        if ($passed) {
            $this->passedTests++;
            $status = "✅ PASS";
        } else {
            $this->failedTests++;
            $status = "❌ FAIL";
        }

        $this->testResults[] = [
            'name' => $testName,
            'status' => $status,
            'passed' => $passed,
            'details' => $details,
            'response' => $response
        ];

        echo sprintf("%s - %s\n", $status, $testName);
        if ($details) {
            echo "   详情: {$details}\n";
        }
        echo "\n";
    }

    /**
     * 测试新的分页参数格式
     */
    public function testNewPaginationFormat()
    {
        echo "=== 测试新的分页参数格式 (page/size) ===\n\n";

        // 测试1: 基本新参数格式
        $url = $this->baseUrl . '/official-api/wechat/articles?page=1&size=10';
        $result = $this->makeRequest($url, '基本新参数格式');

        if ($result['success'] && isset($result['response']['data'])) {
            $data = $result['response']['data'];
            $hasNewFields = isset($data['page']) && isset($data['size']);
            $hasOldFields = isset($data['current']) && isset($data['pageSize']);
            $correctValues = $data['page'] == 1 && $data['size'] == 10;

            $this->recordTest(
                '新参数格式基本功能',
                $hasNewFields && $correctValues,
                sprintf("新字段: %s, 旧字段: %s, 值正确: %s",
                    $hasNewFields ? '✓' : '✗',
                    $hasOldFields ? '✓' : '✗',
                    $correctValues ? '✓' : '✗'
                ),
                $data
            );
        } else {
            $this->recordTest('新参数格式基本功能', false, '请求失败或响应格式错误');
        }

        // 测试2: 不同页码和大小
        $url = $this->baseUrl . '/official-api/wechat/articles?page=2&size=5';
        $result = $this->makeRequest($url, '不同页码和大小');

        if ($result['success'] && isset($result['response']['data'])) {
            $data = $result['response']['data'];
            $correctValues = $data['page'] == 2 && $data['size'] == 5;
            $hasPagination = isset($data['total']) && isset($data['pages']);

            $this->recordTest(
                '新参数不同页码大小',
                $correctValues && $hasPagination,
                sprintf("页码: %s, 大小: %s, 分页信息: %s",
                    $data['page'],
                    $data['size'],
                    $hasPagination ? '✓' : '✗'
                ),
                $data
            );
        } else {
            $this->recordTest('新参数不同页码大小', false, '请求失败或响应格式错误');
        }

        // 测试3: 边界值测试
        $url = $this->baseUrl . '/official-api/wechat/articles?page=1&size=100';
        $result = $this->makeRequest($url, '边界值测试(size=100)');

        if ($result['success'] && isset($result['response']['data'])) {
            $data = $result['response']['data'];
            $sizeValid = $data['size'] <= 100;

            $this->recordTest(
                '新参数边界值(size=100)',
                $sizeValid,
                sprintf("size值: %s (应≤100)", $data['size']),
                $data
            );
        } else {
            $this->recordTest('新参数边界值(size=100)', false, '请求失败或响应格式错误');
        }
    }

    /**
     * 测试向后兼容性
     */
    public function testBackwardCompatibility()
    {
        echo "=== 测试向后兼容性 (current/pageSize) ===\n\n";

        // 测试1: 旧参数格式 current/pageSize
        $url = $this->baseUrl . '/official-api/wechat/articles?current=1&pageSize=10';
        $result = $this->makeRequest($url, '旧参数格式 current/pageSize');

        if ($result['success'] && isset($result['response']['data'])) {
            $data = $result['response']['data'];
            $hasOldFields = isset($data['current']) && isset($data['pageSize']);
            $hasNewFields = isset($data['page']) && isset($data['size']);
            $correctValues = $data['current'] == 1 && $data['pageSize'] == 10;

            $this->recordTest(
                '旧参数格式兼容性',
                $hasOldFields && $correctValues,
                sprintf("旧字段: %s, 新字段: %s, 值正确: %s",
                    $hasOldFields ? '✓' : '✗',
                    $hasNewFields ? '✓' : '✗',
                    $correctValues ? '✓' : '✗'
                ),
                $data
            );
        } else {
            $this->recordTest('旧参数格式兼容性', false, '请求失败或响应格式错误');
        }

        // 测试2: 旧参数格式 limit
        $url = $this->baseUrl . '/official-api/wechat/articles?current=1&limit=15';
        $result = $this->makeRequest($url, '旧参数格式 limit');

        if ($result['success'] && isset($result['response']['data'])) {
            $data = $result['response']['data'];
            $limitHandled = isset($data['size']) && $data['size'] == 15;

            $this->recordTest(
                '旧参数limit兼容性',
                $limitHandled,
                sprintf("limit转换为size: %s (期望15)", $data['size']),
                $data
            );
        } else {
            $this->recordTest('旧参数limit兼容性', false, '请求失败或响应格式错误');
        }

        // 测试3: 混合参数测试
        $url = $this->baseUrl . '/official-api/wechat/articles?page=2&pageSize=8';
        $result = $this->makeRequest($url, '混合参数测试');

        if ($result['success'] && isset($result['response']['data'])) {
            $data = $result['response']['data'];
            $priorityCorrect = $data['page'] == 2; // page应该优先于current
            $sizeFromPageSize = $data['size'] == 8; // size应该来自pageSize

            $this->recordTest(
                '混合参数优先级',
                $priorityCorrect && $sizeFromPageSize,
                sprintf("page优先级: %s, size来自pageSize: %s",
                    $priorityCorrect ? '✓' : '✗',
                    $sizeFromPageSize ? '✓' : '✗'
                ),
                $data
            );
        } else {
            $this->recordTest('混合参数优先级', false, '请求失败或响应格式错误');
        }
    }

    /**
     * 测试参数优先级
     */
    public function testParameterPriority()
    {
        echo "=== 测试参数优先级 ===\n\n";

        // 测试1: page vs current 优先级
        $url = $this->baseUrl . '/official-api/wechat/articles?page=3&current=1';
        $result = $this->makeRequest($url, 'page vs current 优先级');

        if ($result['success'] && isset($result['response']['data'])) {
            $data = $result['response']['data'];
            $pagePriority = $data['page'] == 3; // page应该优先
            $currentIgnored = $data['current'] == 3; // current应该跟随page

            $this->recordTest(
                'page参数优先级',
                $pagePriority && $currentIgnored,
                sprintf("page值: %s (期望3), current值: %s (期望3)",
                    $data['page'], $data['current']),
                $data
            );
        } else {
            $this->recordTest('page参数优先级', false, '请求失败或响应格式错误');
        }

        // 测试2: size vs pageSize vs limit 优先级
        $url = $this->baseUrl . '/official-api/wechat/articles?size=25&pageSize=10&limit=5';
        $result = $this->makeRequest($url, 'size参数优先级');

        if ($result['success'] && isset($result['response']['data'])) {
            $data = $result['response']['data'];
            $sizePriority = $data['size'] == 25; // size应该最优先

            $this->recordTest(
                'size参数优先级',
                $sizePriority,
                sprintf("size值: %s (期望25, 应优先于pageSize和limit)", $data['size']),
                $data
            );
        } else {
            $this->recordTest('size参数优先级', false, '请求失败或响应格式错误');
        }

        // 测试3: pageSize vs limit 优先级 (当size不存在时)
        $url = $this->baseUrl . '/official-api/wechat/articles?pageSize=12&limit=8';
        $result = $this->makeRequest($url, 'pageSize vs limit 优先级');

        if ($result['success'] && isset($result['response']['data'])) {
            $data = $result['response']['data'];
            $pageSizePriority = $data['size'] == 12; // pageSize应该优先于limit

            $this->recordTest(
                'pageSize vs limit优先级',
                $pageSizePriority,
                sprintf("size值: %s (期望12, pageSize应优先于limit)", $data['size']),
                $data
            );
        } else {
            $this->recordTest('pageSize vs limit优先级', false, '请求失败或响应格式错误');
        }
    }

    /**
     * 测试边界情况和错误处理
     */
    public function testEdgeCasesAndErrorHandling()
    {
        echo "=== 测试边界情况和错误处理 ===\n\n";

        // 测试1: 无效页码处理
        $url = $this->baseUrl . '/official-api/wechat/articles?page=0&size=10';
        $result = $this->makeRequest($url, '无效页码(page=0)');

        if ($result['success'] && isset($result['response']['data'])) {
            $data = $result['response']['data'];
            $pageCorrected = $data['page'] >= 1; // 应该被修正为1

            $this->recordTest(
                '无效页码修正',
                $pageCorrected,
                sprintf("页码修正为: %s (应≥1)", $data['page']),
                $data
            );
        } else {
            $this->recordTest('无效页码修正', false, '请求失败或响应格式错误');
        }

        // 测试2: 负数页码处理
        $url = $this->baseUrl . '/official-api/wechat/articles?page=-5&size=10';
        $result = $this->makeRequest($url, '负数页码(page=-5)');

        if ($result['success'] && isset($result['response']['data'])) {
            $data = $result['response']['data'];
            $pageCorrected = $data['page'] >= 1;

            $this->recordTest(
                '负数页码修正',
                $pageCorrected,
                sprintf("负数页码修正为: %s", $data['page']),
                $data
            );
        } else {
            $this->recordTest('负数页码修正', false, '请求失败或响应格式错误');
        }

        // 测试3: 超大size处理
        $url = $this->baseUrl . '/official-api/wechat/articles?page=1&size=200';
        $result = $this->makeRequest($url, '超大size(size=200)');

        if ($result['success'] && isset($result['response']['data'])) {
            $data = $result['response']['data'];
            $sizeLimited = $data['size'] <= 100; // 应该被限制为100

            $this->recordTest(
                '超大size限制',
                $sizeLimited,
                sprintf("size被限制为: %s (应≤100)", $data['size']),
                $data
            );
        } else {
            $this->recordTest('超大size限制', false, '请求失败或响应格式错误');
        }

        // 测试4: 无分页参数默认值
        $url = $this->baseUrl . '/official-api/wechat/articles';
        $result = $this->makeRequest($url, '无分页参数');

        if ($result['success'] && isset($result['response']['data'])) {
            $data = $result['response']['data'];
            $hasDefaults = isset($data['page']) && isset($data['size']);
            $correctDefaults = $data['page'] == 1 && $data['size'] == 20;

            $this->recordTest(
                '默认分页参数',
                $hasDefaults && $correctDefaults,
                sprintf("默认值: page=%s, size=%s (期望1,20)", $data['page'], $data['size']),
                $data
            );
        } else {
            $this->recordTest('默认分页参数', false, '请求失败或响应格式错误');
        }

        // 测试5: 字符串参数处理
        $url = $this->baseUrl . '/official-api/wechat/articles?page=abc&size=xyz';
        $result = $this->makeRequest($url, '字符串参数');

        if ($result['success'] && isset($result['response']['data'])) {
            $data = $result['response']['data'];
            $hasValues = isset($data['page']) && isset($data['size']);

            $this->recordTest(
                '字符串参数处理',
                $hasValues,
                sprintf("字符串参数处理结果: page=%s, size=%s", $data['page'], $data['size']),
                $data
            );
        } else {
            $this->recordTest('字符串参数处理', false, '请求失败或响应格式错误');
        }
    }

    /**
     * 测试分页计算逻辑
     */
    public function testPaginationCalculation()
    {
        echo "=== 测试分页计算逻辑 ===\n\n";

        // 测试1: 基本分页计算
        $url = $this->baseUrl . '/official-api/wechat/articles?page=1&size=5';
        $result = $this->makeRequest($url, '基本分页计算');

        if ($result['success'] && isset($result['response']['data'])) {
            $data = $result['response']['data'];
            $hasRequiredFields = isset($data['total']) && isset($data['pages']) && isset($data['offset']);
            $offsetCorrect = $data['offset'] == 0; // 第1页偏移量应为0
            $pagesCalculation = $data['total'] > 0 ? $data['pages'] == ceil($data['total'] / $data['size']) : true;

            $this->recordTest(
                '基本分页计算',
                $hasRequiredFields && $offsetCorrect,
                sprintf("偏移量: %s (期望0), 总页数计算: %s",
                    $data['offset'],
                    $pagesCalculation ? '✓' : '✗'
                ),
                $data
            );
        } else {
            $this->recordTest('基本分页计算', false, '请求失败或响应格式错误');
        }

        // 测试2: 第2页偏移量计算
        $url = $this->baseUrl . '/official-api/wechat/articles?page=2&size=10';
        $result = $this->makeRequest($url, '第2页偏移量计算');

        if ($result['success'] && isset($result['response']['data'])) {
            $data = $result['response']['data'];
            $offsetCorrect = $data['offset'] == 10; // 第2页偏移量应为10

            $this->recordTest(
                '第2页偏移量计算',
                $offsetCorrect,
                sprintf("偏移量: %s (期望10)", $data['offset']),
                $data
            );
        } else {
            $this->recordTest('第2页偏移量计算', false, '请求失败或响应格式错误');
        }

        // 测试3: 分页信息一致性
        $url = $this->baseUrl . '/official-api/wechat/articles?page=1&size=3';
        $result = $this->makeRequest($url, '分页信息一致性');

        if ($result['success'] && isset($result['response']['data'])) {
            $data = $result['response']['data'];
            $newOldConsistent = $data['page'] == $data['current'] && $data['size'] == $data['pageSize'];
            $hasFromTo = isset($data['from']) && isset($data['to']);

            $this->recordTest(
                '分页信息一致性',
                $newOldConsistent && $hasFromTo,
                sprintf("新旧字段一致: %s, 包含from/to: %s",
                    $newOldConsistent ? '✓' : '✗',
                    $hasFromTo ? '✓' : '✗'
                ),
                $data
            );
        } else {
            $this->recordTest('分页信息一致性', false, '请求失败或响应格式错误');
        }
    }

    /**
     * 测试响应格式完整性
     */
    public function testResponseFormat()
    {
        echo "=== 测试响应格式完整性 ===\n\n";

        $url = $this->baseUrl . '/official-api/wechat/articles?page=1&size=5';
        $result = $this->makeRequest($url, '响应格式检查');

        if ($result['success'] && isset($result['response']['data'])) {
            $data = $result['response']['data'];

            // 检查必需的新字段
            $requiredNewFields = ['page', 'size', 'total', 'pages', 'offset'];
            $hasNewFields = true;
            foreach ($requiredNewFields as $field) {
                if (!isset($data[$field])) {
                    $hasNewFields = false;
                    break;
                }
            }

            // 检查向后兼容的旧字段
            $requiredOldFields = ['current', 'pageSize'];
            $hasOldFields = true;
            foreach ($requiredOldFields as $field) {
                if (!isset($data[$field])) {
                    $hasOldFields = false;
                    break;
                }
            }

            // 检查数据项
            $hasItems = isset($data['items']) && is_array($data['items']);

            // 检查额外分页信息
            $hasExtraInfo = isset($data['from']) && isset($data['to']) && isset($data['filterSummary']);

            $this->recordTest(
                '响应格式完整性',
                $hasNewFields && $hasOldFields && $hasItems,
                sprintf("新字段: %s, 旧字段: %s, 数据项: %s, 额外信息: %s",
                    $hasNewFields ? '✓' : '✗',
                    $hasOldFields ? '✓' : '✗',
                    $hasItems ? '✓' : '✗',
                    $hasExtraInfo ? '✓' : '✗'
                ),
                $data
            );
        } else {
            $this->recordTest('响应格式完整性', false, '请求失败或响应格式错误');
        }
    }

    /**
     * 生成测试报告
     */
    public function generateReport()
    {
        echo "\n=== 测试报告 ===\n\n";

        echo "总测试数: {$this->totalTests}\n";
        echo "通过测试: {$this->passedTests}\n";
        echo "失败测试: {$this->failedTests}\n";
        echo "成功率: " . round(($this->passedTests / $this->totalTests) * 100, 2) . "%\n\n";

        echo "=== 详细测试结果 ===\n\n";

        foreach ($this->testResults as $result) {
            echo sprintf("%s - %s\n", $result['status'], $result['name']);
            if (!$result['passed'] && $result['details']) {
                echo "   失败原因: {$result['details']}\n";
            }
            echo "\n";
        }

        // 生成JSON报告文件
        $report = [
            'summary' => [
                'total_tests' => $this->totalTests,
                'passed_tests' => $this->passedTests,
                'failed_tests' => $this->failedTests,
                'success_rate' => round(($this->passedTests / $this->totalTests) * 100, 2),
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'tests' => $this->testResults
        ];

        file_put_contents('wechat_pagination_test_report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "详细报告已保存到: wechat_pagination_test_report.json\n";

        return $report;
    }

    /**
     * 运行所有测试
     */
    public function runAllTests()
    {
        try {
            $this->testNewPaginationFormat();
            $this->testBackwardCompatibility();
            $this->testParameterPriority();
            $this->testEdgeCasesAndErrorHandling();
            $this->testPaginationCalculation();
            $this->testResponseFormat();

            return $this->generateReport();
        } catch (Exception $e) {
            echo "测试执行出错: " . $e->getMessage() . "\n";
            return null;
        }
    }
}

// 运行测试
$test = new WechatPaginationTest();
$report = $test->runAllTests();

if ($report) {
    echo "\n=== 测试完成 ===\n";
    echo "成功率: {$report['summary']['success_rate']}%\n";

    if ($report['summary']['failed_tests'] > 0) {
        echo "\n⚠️  发现问题，需要检查以下失败的测试项:\n";
        foreach ($report['tests'] as $test) {
            if (!$test['passed']) {
                echo "- {$test['name']}\n";
            }
        }
    } else {
        echo "\n🎉 所有测试通过！分页参数修改正确实现。\n";
    }
}
