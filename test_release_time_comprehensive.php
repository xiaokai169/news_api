<?php

/**
 * Release Time 修复功能全面测试脚本
 *
 * 测试修复后的 WechatArticleSyncService.php 中的时间处理逻辑
 * 验证三层级时间处理策略是否正常工作
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Service\WechatArticleSyncService;
use App\Entity\Official;
use App\Repository\OfficialRepository;
use App\Repository\WechatPublicAccountRepository;
use App\Service\WechatApiService;
use App\Service\MediaResourceProcessor;
use App\Service\ResourceExtractor;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;

class ReleaseTimeComprehensiveTest
{
    private LoggerInterface $logger;
    private array $testResults = [];
    private array $logMessages = [];

    public function __construct()
    {
        // 创建测试专用的日志记录器
        $this->logger = new Logger('release_time_test');
        $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
        $this->logger->pushHandler(new StreamHandler(__DIR__ . '/release_time_test.log', Logger::DEBUG));
    }

    /**
     * 运行所有测试
     */
    public function runAllTests(): array
    {
        $this->logger->info('开始 Release Time 修复功能全面测试');

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

            // 测试7: 日志记录功能检查
            $this->testLoggingFunctionality();

        } catch (Exception $e) {
            $this->logger->error('测试执行过程中发生异常', ['exception' => $e]);
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

        $this->logger->info('Release Time 修复功能测试完成', [
            'execution_time_ms' => $executionTime,
            'total_tests' => count($this->testResults),
            'passed_tests' => count(array_filter($this->testResults, fn($r) => $r['status'] === 'PASSED')),
            'failed_tests' => count(array_filter($this->testResults, fn($r) => $r['status'] === 'FAILED'))
        ]);

        return $report;
    }

    /**
     * 测试1: 正常情况 - 有 publish_time
     */
    private function testNormalCaseWithPublishTime(): void
    {
        $testName = '正常情况_有publish_time';
        $this->logger->info("开始测试: {$testName}");

        try {
            $articleData = $this->createMockArticleData([
                'article_id' => 'test_normal_001',
                'title' => '测试文章-正常情况',
                'publish_time' => '1704067200', // 2024-01-01 00:00:00
                'update_time' => '1704153600'  // 2024-01-02 00:00:00
            ]);

            $result = $this->processArticleDataWithMock($articleData);

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
        $this->logger->info("开始测试: {$testName}");

        try {
            $articleData = $this->createMockArticleData([
                'article_id' => 'test_alternative_001',
                'title' => '测试文章-备选情况',
                'update_time' => '1704240000' // 2024-01-03 00:00:00
                // 故意不设置 publish_time
            ]);

            $result = $this->processArticleDataWithMock($articleData);

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
        $this->logger->info("开始测试: {$testName}");

        try {
            $articleData = $this->createMockArticleData([
                'article_id' => 'test_default_001',
                'title' => '测试文章-默认情况'
                // 故意不设置任何时间字段
            ]);

            $result = $this->processArticleDataWithMock($articleData);

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
        $this->logger->info("开始测试: {$testName}");

        try {
            $articleData = $this->createMockArticleData([
                'article_id' => 'test_exception_001',
                'title' => '测试文章-异常情况',
                'publish_time' => '', // 空字符串
                'update_time' => 'invalid_timestamp' // 无效时间戳
            ]);

            $result = $this->processArticleDataWithMock($articleData);

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
        $this->logger->info("开始测试: {$testName}");

        $boundaryTests = [
            'zero_timestamp' => [
                'publish_time' => '0',
                'expected' => '1970-01-01 08:00:00' // 考虑时区
            ],
            'max_32bit_timestamp' => [
                'publish_time' => '2147483647',
                'expected' => '2038-01-19 11:14:07'
            ],
            'recent_timestamp' => [
                'publish_time' => '1734567890',
                'expected' => '2024-12-19 08:18:10'
            ]
        ];

        foreach ($boundaryTests as $subTestName => $testData) {
            try {
                $articleData = $this->createMockArticleData([
                    'article_id' => "test_boundary_{$subTestName}",
                    'title' => "测试文章-边界情况-{$subTestName}",
                    'publish_time' => $testData['publish_time']
                ]);

                $result = $this->processArticleDataWithMock($articleData);

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
        $this->logger->info("开始测试: {$testName}");

        try {
            $articleData = $this->createMockArticleData([
                'article_id' => 'test_format_001',
                'title' => '测试文章-格式验证',
                'publish_time' => '1704067200'
            ]);

            $result = $this->processArticleDataWithMock($articleData);

            // 验证时间格式是否为 Y-m-d H:i:s
            $isValidFormat = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result['release_time']);

            $this->testResults[$testName] = [
                'status' => $isValidFormat ? 'PASSED' : 'FAILED',
                'release_time' => $result['release_time'],
                'format_valid' => $isValidFormat,
                'time_source' => $result['time_source'],
                'details' => $isValidFormat ? '时间格式正确' : '时间格式不正确'
            ];

        } catch (Exception $e) {
            $this->recordTestFailure($testName, $e->getMessage());
        }
    }

    /**
     * 测试7: 日志记录功能检查
     */
    private function testLoggingFunctionality(): void
    {
        $testName = '日志记录功能检查';
        $this->logger->info("开始测试: {$testName}");

        try {
            // 捕获日志输出
            $originalLogMessages = $this->logMessages;

            $articleData = $this->createMockArticleData([
                'article_id' => 'test_logging_001',
                'title' => '测试文章-日志检查',
                'publish_time' => '1704067200'
            ]);

            $this->processArticleDataWithMock($articleData);

            // 检查是否有相关的日志记录
            $hasTimeLogs = false;
            foreach ($this->logMessages as $message) {
                if (strpos($message, '发布时间设置成功') !== false ||
                    strpos($message, '使用发布时间') !== false) {
                    $hasTimeLogs = true;
                    break;
                }
            }

            $this->testResults[$testName] = [
                'status' => $hasTimeLogs ? 'PASSED' : 'FAILED',
                'has_time_logs' => $hasTimeLogs,
                'log_count' => count($this->logMessages) - count($originalLogMessages),
                'details' => $hasTimeLogs ? '日志记录正常' : '缺少相关日志记录'
            ];

        } catch (Exception $e) {
            $this->recordTestFailure($testName, $e->getMessage());
        }
    }

    /**
     * 创建模拟文章数据
     */
    private function createMockArticleData(array $overrides = []): array
    {
        $defaultData = [
            'article_id' => 'test_article_' . uniqid(),
            'title' => '测试文章',
            'author' => '测试作者',
            'digest' => '测试摘要',
            'content' => '<p>测试内容</p>',
            'url' => 'https://test.com/article',
            'thumb_url' => 'https://test.com/thumb.jpg',
            'thumb_media_id' => 'test_thumb_media_id',
            'show_cover_pic' => 1,
            'need_open_comment' => 0
        ];

        return array_merge($defaultData, $overrides);
    }

    /**
     * 使用模拟服务处理文章数据
     */
    private function processArticleDataWithMock(array $articleData): array
    {
        // 创建模拟的服务依赖
        $mockServices = $this->createMockServices();

        // 创建 WechatArticleSyncService 实例
        $syncService = new WechatArticleSyncService(
            $mockServices['wechatApiService'],
            $mockServices['officialRepository'],
            $mockServices['wechatPublicAccountRepository'],
            $mockServices['entityManager'],
            $mockServices['mediaResourceProcessor'],
            $mockServices['resourceExtractor'],
            $this->logger
        );

        // 使用反射调用私有方法
        $reflection = new ReflectionClass($syncService);
        $method = $reflection->getMethod('processArticleData');
        $method->setAccessible(true);

        $result = $method->invoke($syncService, $articleData, 'test_account_id', false);

        if ($result instanceof Official) {
            return [
                'article_id' => $result->getArticleId(),
                'title' => $result->getTitle(),
                'release_time' => $result->getReleaseTime(),
                'time_source' => $this->extractTimeSourceFromLogs($articleData['article_id']),
                'success' => true
            ];
        }

        return [
            'success' => false,
            'error' => '处理失败'
        ];
    }

    /**
     * 创建模拟的服务依赖
     */
    private function createMockServices(): array
    {
        return [
            'wechatApiService' => $this->createMock(WechatApiService::class),
            'officialRepository' => $this->createMock(OfficialRepository::class),
            'wechatPublicAccountRepository' => $this->createMock(WechatPublicAccountRepository::class),
            'entityManager' => $this->createMock(EntityManagerInterface::class),
            'mediaResourceProcessor' => $this->createMock(MediaResourceProcessor::class),
            'resourceExtractor' => $this->createMock(ResourceExtractor::class)
        ];
    }

    /**
     * 创建模拟对象
     */
    private function createMock(string $className): object
    {
        $mock = $this->getMockBuilder($className)
                     ->disableOriginalConstructor()
                     ->getMock();

        // 配置基本的模拟行为
        if ($className === OfficialRepository::class) {
            $mock->method('findByArticleId')->willReturn(null);
        }

        return $mock;
    }

    /**
     * 从日志中提取时间源信息
     */
    private function extractTimeSourceFromLogs(string $articleId): string
    {
        foreach ($this->logMessages as $message) {
            if (strpos($message, $articleId) !== false) {
                if (strpos($message, 'publish_time') !== false) {
                    return 'publish_time';
                } elseif (strpos($message, 'update_time') !== false) {
                    return 'update_time';
                } elseif (strpos($message, 'current_time') !== false) {
                    return 'current_time';
                }
            }
        }
        return 'unknown';
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
                'correct_time_format' => $this->validateCorrectTimeFormat(),
                'proper_logging' => $this->validateProperLogging()
            ],
            'deployment_recommendations' => $this->generateDeploymentRecommendations()
        ];

        // 保存测试报告到文件
        $reportFile = __DIR__ . '/release_time_comprehensive_test_report.json';
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->logger->info('测试报告已保存', ['file' => $reportFile]);

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
     * 验证日志记录功能
     */
    private function validateProperLogging(): bool
    {
        $loggingTest = $this->testResults['日志记录功能检查'] ?? null;
        return $loggingTest && $loggingTest['status'] === 'PASSED';
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
if (php_sapi_name() === 'cli') {
    echo "开始 Release Time 修复功能全面测试...\n\n";

    $test = new ReleaseTimeComprehensiveTest();
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

    echo "\n详细报告已保存到: release_time_comprehensive_test_report.json\n";
}
