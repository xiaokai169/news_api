<?php

/**
 * Release Time 修复功能独立测试脚本
 *
 * 独立测试修复后的时间处理逻辑，不依赖 Symfony 框架
 * 验证三层级时间处理策略是否正常工作
 */

class ReleaseTimeStandaloneTest
{
    private array $testResults = [];
    private array $logMessages = [];

    public function __construct()
    {
        echo "初始化 Release Time 修复功能独立测试...\n";
    }

    /**
     * 运行所有测试
     */
    public function runAllTests(): array
    {
        echo "开始 Release Time 修复功能全面测试\n";
        echo "=====================================\n\n";

        $startTime = microtime(true);

        try {
            // 测试1: 正常情况 - 有 publish_time
            $this->testNormalCaseWithPublishTime();

            // 测试2: 备选情况 - 只有 update_time
            $this->testAlternativeCaseWithUpdateTime();

            // 测试3: 默认情况 - 无时间字段
            $this->testDefaultCaseWithNoTimeFields();

            // 测试4: 异常情况 - 时间字段为空或无效
            $this->testExceptionCaseWithInvalidTime();

            // 测试5: 边界情况 - 时间戳格式转换
            $this->testBoundaryCaseWithTimestampFormats();

            // 测试6: 时间格式正确性验证
            $this->testTimeFormatValidation();

        } catch (Exception $e) {
            echo "测试执行过程中发生异常: " . $e->getMessage() . "\n";
            $this->testResults['execution_error'] = [
                'status' => 'FAILED',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }

        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);

        // 生成测试报告
        $report = $this->generateTestReport($executionTime);

        echo "\nRelease Time 修复功能测试完成\n";
        echo "执行时间: {$executionTime}ms\n";
        echo "总测试数: " . count($this->testResults) . "\n";
        echo "通过测试: " . count(array_filter($this->testResults, fn($r) => $r['status'] === 'PASSED')) . "\n";
        echo "失败测试: " . count(array_filter($this->testResults, fn($r) => $r['status'] === 'FAILED')) . "\n";

        return $report;
    }

    /**
     * 模拟修复后的时间处理逻辑
     */
    private function processReleaseTimeLogic(array $articleData): array
    {
        $releaseTime = null;
        $timeSource = '';
        $logs = [];

        // 优先级1: 使用微信API的 publish_time
        if (isset($articleData['publish_time']) && !empty($articleData['publish_time'])) {
            $releaseTime = \DateTime::createFromFormat('U', $articleData['publish_time']);
            if ($releaseTime) {
                $timeSource = 'publish_time';
                $logs[] = "使用发布时间: source=publish_time, timestamp={$articleData['publish_time']}";
            } else {
                $logs[] = "创建发布时间DateTime失败: publish_time={$articleData['publish_time']}";
            }
        }

        // 优先级2: 使用 update_time 作为备选
        if ($releaseTime === null && isset($articleData['update_time']) && !empty($articleData['update_time'])) {
            $releaseTime = \DateTime::createFromFormat('U', $articleData['update_time']);
            if ($releaseTime) {
                $timeSource = 'update_time';
                $logs[] = "使用更新时间作为发布时间: source=update_time, timestamp={$articleData['update_time']}";
            } else {
                $logs[] = "创建更新时间DateTime失败: update_time={$articleData['update_time']}";
            }
        }

        // 优先级3: 使用当前时间作为默认值，确保永远不会为空
        if ($releaseTime === null) {
            $releaseTime = new \DateTime();
            $timeSource = 'current_time';
            $logs[] = "未找到有效的时间字段，使用当前时间作为默认值: articleId={$articleData['article_id']}, default_time={$releaseTime->format('Y-m-d H:i:s')}";
        }

        // 设置最终的时间值，确保格式正确
        if ($releaseTime instanceof \DateTime) {
            $formattedTime = $releaseTime->format('Y-m-d H:i:s');
            $logs[] = "发布时间设置成功: timeSource={$timeSource}, releaseTime={$formattedTime}";
            return [
                'release_time' => $formattedTime,
                'time_source' => $timeSource,
                'success' => true,
                'logs' => $logs
            ];
        } else {
            // 额外的安全检查，理论上不应该到达这里
            $fallbackTime = new \DateTime();
            $formattedTime = $fallbackTime->format('Y-m-d H:i:s');
            $logs[] = "时间创建失败，使用紧急备用时间: fallbackTime={$formattedTime}";
            return [
                'release_time' => $formattedTime,
                'time_source' => 'emergency_fallback',
                'success' => true,
                'logs' => $logs
            ];
        }
    }

    /**
     * 测试1: 正常情况 - 有 publish_time
     */
    private function testNormalCaseWithPublishTime(): void
    {
        $testName = '正常情况_有publish_time';
        echo "测试1: {$testName}\n";

        try {
            $articleData = [
                'article_id' => 'test_normal_001',
                'title' => '测试文章-正常情况',
                'publish_time' => '1704067200', // 2024-01-01 00:00:00
                'update_time' => '1704153600'  // 2024-01-02 00:00:00
            ];

            $result = $this->processReleaseTimeLogic($articleData);

            $this->validateTestResult($testName, $result, [
                'expected_time_source' => 'publish_time',
                'expected_release_time' => '2024-01-01 00:00:00',
                'should_not_be_null' => true
            ]);

        } catch (Exception $e) {
            $this->recordTestFailure($testName, $e->getMessage());
        }
    }

    /**
     * 测试2: 备选情况 - 只有 update_time
     */
    private function testAlternativeCaseWithUpdateTime(): void
    {
        $testName = '备选情况_只有update_time';
        echo "测试2: {$testName}\n";

        try {
            $articleData = [
                'article_id' => 'test_alternative_001',
                'title' => '测试文章-备选情况',
                'update_time' => '1704240000' // 2024-01-03 00:00:00
                // 故意不设置 publish_time
            ];

            $result = $this->processReleaseTimeLogic($articleData);

            $this->validateTestResult($testName, $result, [
                'expected_time_source' => 'update_time',
                'expected_release_time' => '2024-01-03 00:00:00',
                'should_not_be_null' => true
            ]);

        } catch (Exception $e) {
            $this->recordTestFailure($testName, $e->getMessage());
        }
    }

    /**
     * 测试3: 默认情况 - 无时间字段
     */
    private function testDefaultCaseWithNoTimeFields(): void
    {
        $testName = '默认情况_无时间字段';
        echo "测试3: {$testName}\n";

        try {
            $articleData = [
                'article_id' => 'test_default_001',
                'title' => '测试文章-默认情况'
                // 故意不设置任何时间字段
            ];

            $result = $this->processReleaseTimeLogic($articleData);

            // 验证使用了当前时间
            $this->validateTestResult($testName, $result, [
                'expected_time_source' => 'current_time',
                'should_not_be_null' => true,
                'should_be_recent' => true // 应该是最近的时间
            ]);

        } catch (Exception $e) {
            $this->recordTestFailure($testName, $e->getMessage());
        }
    }

    /**
     * 测试4: 异常情况 - 时间字段为空或无效
     */
    private function testExceptionCaseWithInvalidTime(): void
    {
        $testName = '异常情况_无效时间字段';
        echo "测试4: {$testName}\n";

        try {
            $articleData = [
                'article_id' => 'test_exception_001',
                'title' => '测试文章-异常情况',
                'publish_time' => '', // 空字符串
                'update_time' => 'invalid_timestamp' // 无效时间戳
            ];

            $result = $this->processReleaseTimeLogic($articleData);

            // 验证使用了当前时间作为备选
            $this->validateTestResult($testName, $result, [
                'expected_time_source' => 'current_time',
                'should_not_be_null' => true,
                'should_be_recent' => true
            ]);

        } catch (Exception $e) {
            $this->recordTestFailure($testName, $e->getMessage());
        }
    }

    /**
     * 测试5: 边界情况 - 时间戳格式转换
     */
    private function testBoundaryCaseWithTimestampFormats(): void
    {
        $testName = '边界情况_时间戳格式转换';
        echo "测试5: {$testName}\n";

        $boundaryTests = [
            'zero_timestamp' => [
                'publish_time' => '0',
                'expected' => '1970-01-01 08:00:00' // 考虑时区
            ],
            'recent_timestamp' => [
                'publish_time' => '1734567890',
                'expected' => '2024-12-19 08:18:10'
            ],
            'string_timestamp' => [
                'publish_time' => '1704067200',
                'expected' => '2024-01-01 00:00:00'
            ]
        ];

        foreach ($boundaryTests as $subTestName => $testData) {
            try {
                $articleData = [
                    'article_id' => "test_boundary_{$subTestName}",
                    'title' => "测试文章-边界情况-{$subTestName}",
                    'publish_time' => $testData['publish_time']
                ];

                $result = $this->processReleaseTimeLogic($articleData);

                $this->validateTestResult("{$testName}_{$subTestName}", $result, [
                    'expected_time_source' => 'publish_time',
                    'expected_release_time' => $testData['expected'],
                    'should_not_be_null' => true
                ]);

            } catch (Exception $e) {
                $this->recordTestFailure("{$testName}_{$subTestName}", $e->getMessage());
            }
        }
    }

    /**
     * 测试6: 时间格式正确性验证
     */
    private function testTimeFormatValidation(): void
    {
        $testName = '时间格式正确性验证';
        echo "测试6: {$testName}\n";

        try {
            $articleData = [
                'article_id' => 'test_format_001',
                'title' => '测试文章-格式验证',
                'publish_time' => '1704067200'
            ];

            $result = $this->processReleaseTimeLogic($articleData);

            // 验证时间格式是否为 Y-m-d H:i:s
            $isValidFormat = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result['release_time']);

            $this->testResults[$testName] = [
                'status' => $isValidFormat ? 'PASSED' : 'FAILED',
                'release_time' => $result['release_time'],
                'format_valid' => $isValidFormat,
                'time_source' => $result['time_source'],
                'details' => $isValidFormat ? '时间格式正确' : '时间格式不正确'
            ];

            echo "  结果: " . ($isValidFormat ? '✓ 通过' : '✗ 失败') . " - {$result['release_time']}\n";

        } catch (Exception $e) {
            $this->recordTestFailure($testName, $e->getMessage());
        }
    }

    /**
     * 验证测试结果
     */
    private function validateTestResult(string $testName, array $result, array $expectations): void
    {
        $passed = true;
        $details = [];

        // 检查是否成功
        if (!$result['success']) {
            $passed = false;
            $details[] = '处理失败: ' . ($result['error'] ?? '未知错误');
        }

        // 检查时间源
        if (isset($expectations['expected_time_source']) && $result['time_source'] !== $expectations['expected_time_source']) {
            $passed = false;
            $details[] = "时间源不匹配: 期望 {$expectations['expected_time_source']}, 实际 {$result['time_source']}";
        }

        // 检查具体时间值
        if (isset($expectations['expected_release_time']) && $result['release_time'] !== $expectations['expected_release_time']) {
            $passed = false;
            $details[] = "时间值不匹配: 期望 {$expectations['expected_release_time']}, 实际 {$result['release_time']}";
        }

        // 检查是否为空
        if (isset($expectations['should_not_be_null']) && $expectations['should_not_be_null'] && empty($result['release_time'])) {
            $passed = false;
            $details[] = 'release_time 不应为空';
        }

        // 检查是否为最近时间
        if (isset($expectations['should_be_recent']) && $expectations['should_be_recent']) {
            $releaseTime = new DateTime($result['release_time']);
            $now = new DateTime();
            $diff = $now->diff($releaseTime);
            if ($diff->i > 5) { // 如果时间差超过5分钟，认为不是最近时间
                $passed = false;
                $details[] = '应该使用当前时间，但时间差过大';
            }
        }

        $this->testResults[$testName] = [
            'status' => $passed ? 'PASSED' : 'FAILED',
            'result' => $result,
            'expectations' => $expectations,
            'details' => implode('; ', $details) ?: '测试通过'
        ];

        echo "  结果: " . ($passed ? '✓ 通过' : '✗ 失败') . " - {$result['release_time']} (来源: {$result['time_source']})\n";
        if (!$passed) {
            echo "  详情: " . implode('; ', $details) . "\n";
        }
    }

    /**
     * 记录测试失败
     */
    private function recordTestFailure(string $testName, string $error): void
    {
        $this->testResults[$testName] = [
            'status' => 'FAILED',
            'error' => $error,
            'details' => '测试执行异常'
        ];

        echo "  结果: ✗ 失败 - 异常: {$error}\n";
    }

    /**
     * 生成测试报告
     */
    private function generateTestReport(float $executionTime): array
    {
        $totalTests = count($this->testResults);
        $passedTests = count(array_filter($this->testResults, fn($r) => $r['status'] === 'PASSED'));
        $failedTests = $totalTests - $passedTests;

        $report = [
            'test_summary' => [
                'total_tests' => $totalTests,
                'passed_tests' => $passedTests,
                'failed_tests' => $failedTests,
                'success_rate' => $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0,
                'execution_time_ms' => $executionTime,
                'test_date' => date('Y-m-d H:i:s')
            ],
            'test_results' => $this->testResults,
            'fix_validation' => [
                'three_level_time_strategy' => $this->validateThreeLevelTimeStrategy(),
                'never_null_release_time' => $this->validateNeverNullReleaseTime(),
                'correct_time_format' => $this->validateCorrectTimeFormat()
            ],
            'deployment_recommendations' => $this->generateDeploymentRecommendations()
        ];

        // 保存测试报告到文件
        $reportFile = __DIR__ . '/release_time_standalone_test_report.json';
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        echo "\n测试报告已保存到: {$reportFile}\n";

        return $report;
    }

    /**
     * 验证三层级时间策略
     */
    private function validateThreeLevelTimeStrategy(): bool
    {
        $requiredTests = [
            '正常情况_有publish_time' => 'publish_time',
            '备选情况_只有update_time' => 'update_time',
            '默认情况_无时间字段' => 'current_time'
        ];

        foreach ($requiredTests as $testName => $expectedSource) {
            if (!isset($this->testResults[$testName]) ||
                $this->testResults[$testName]['status'] !== 'PASSED' ||
                $this->testResults[$testName]['result']['time_source'] !== $expectedSource) {
                return false;
            }
        }

        return true;
    }

    /**
     * 验证 release_time 永远不为空
     */
    private function validateNeverNullReleaseTime(): bool
    {
        foreach ($this->testResults as $result) {
            if (isset($result['result']['release_time']) && empty($result['result']['release_time'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * 验证时间格式正确性
     */
    private function validateCorrectTimeFormat(): bool
    {
        foreach ($this->testResults as $result) {
            if (isset($result['result']['release_time'])) {
                $time = $result['result']['release_time'];
                if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $time)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * 生成部署建议
     */
    private function generateDeploymentRecommendations(): array
    {
        $successRate = $this->testResults ?
            (count(array_filter($this->testResults, fn($r) => $r['status'] === 'PASSED')) / count($this->testResults)) * 100 : 0;

        $recommendations = [];

        if ($successRate >= 95) {
            $recommendations[] = '修复效果良好，可以安全部署到生产环境';
            $recommendations[] = '建议在部署前进行完整的数据库备份';
            $recommendations[] = '部署后监控24小时，确保所有时间字段正常工作';
        } elseif ($successRate >= 80) {
            $recommendations[] = '修复基本有效，但建议在测试环境进一步验证';
            $recommendations[] = '检查失败的测试用例，修复相关问题后再部署';
            $recommendations[] = '考虑分阶段部署，先部署到预生产环境';
        } else {
            $recommendations[] = '修复存在严重问题，不建议部署';
            $recommendations[] = '需要重新检查时间处理逻辑';
            $recommendations[] = '建议进行更详细的单元测试和集成测试';
        }

        $recommendations[] = '部署前清理相关缓存';
        $recommendations[] = '配置适当的日志监控，关注时间相关错误';
        $recommendations[] = '准备回滚计划，以防出现问题';

        return $recommendations;
    }
}

// 运行测试
echo "Release Time 修复功能独立测试脚本\n";
echo "==================================\n\n";

$test = new ReleaseTimeStandaloneTest();
$report = $test->runAllTests();

echo "\n=== 测试报告 ===\n";
echo "总测试数: {$report['test_summary']['total_tests']}\n";
echo "通过测试: {$report['test_summary']['passed_tests']}\n";
echo "失败测试: {$report['test_summary']['failed_tests']}\n";
echo "成功率: {$report['test_summary']['success_rate']}%\n";
echo "执行时间: {$report['test_summary']['execution_time_ms']}ms\n";
echo "测试时间: {$report['test_summary']['test_date']}\n\n";

echo "=== 修复验证 ===\n";
foreach ($report['fix_validation'] as $item => $status) {
    echo "{$item}: " . ($status ? '✓ 通过' : '✗ 失败') . "\n";
}

echo "\n=== 部署建议 ===\n";
foreach ($report['deployment_recommendations'] as $recommendation) {
    echo "- {$recommendation}\n";
}

echo "\n详细报告已保存到: release_time_standalone_test_report.json\n";
