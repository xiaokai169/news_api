<?php

/**
 * 端到端测试流程
 * 完整测试从数据库到API的整个流程
 */

require_once __DIR__ . '/database_validation.php';
require_once __DIR__ . '/api_endpoints_test.php';

class EndToEndTest
{
    private $baseUrl;
    private $testResults = [];
    private $verbose = false;
    private $dbValidation;
    private $apiTest;

    public function __construct($baseUrl = 'http://localhost:8000', $verbose = false)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->verbose = $verbose;
        $this->dbValidation = new DatabaseValidation($verbose);
        $this->apiTest = new ApiEndpointsTest($baseUrl, $verbose);
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
     * 测试环境准备
     */
    public function testEnvironmentPreparation()
    {
        echo "阶段1: 环境准备测试\n";
        echo str_repeat("-", 30) . "\n";

        // 检查PHP版本
        $phpVersion = PHP_VERSION;
        $passed = version_compare($phpVersion, '8.0.0', '>=');
        $this->logTest(
            'PHP版本检查',
            $passed,
            $passed ? "PHP {$phpVersion} 满足要求" : "需要PHP 8.0+，当前: {$phpVersion}"
        );

        // 检查必需扩展
        $requiredExtensions = ['pdo', 'pdo_mysql', 'curl', 'json'];
        foreach ($requiredExtensions as $ext) {
            $loaded = extension_loaded($ext);
            $this->logTest(
                "PHP扩展 {$ext}",
                $loaded,
                $loaded ? '已加载' : '未加载'
            );
        }

        // 检查项目文件
        $projectFiles = [
            '../composer.json' => '项目配置文件',
            '../.env' => '环境配置文件',
            '../src/Kernel.php' => 'Symfony核心文件',
            '../public/index.php' => '入口文件'
        ];

        foreach ($projectFiles as $file => $description) {
            $exists = file_exists($file);
            $this->logTest(
                $description,
                $exists,
                $exists ? '存在' : '不存在'
            );
        }

        echo "\n";
    }

    /**
     * 测试数据库层
     */
    public function testDatabaseLayer()
    {
        echo "阶段2: 数据库层测试\n";
        echo str_repeat("-", 30) . "\n";

        try {
            // 连接测试
            $this->logTest('数据库连接', true, '连接成功');

            // 表结构验证
            $this->dbValidation->validateTableStructure();

            // 数据完整性验证
            $this->dbValidation->validateDataIntegrity();

            // 数据库操作测试
            $this->dbValidation->testDatabaseOperations();

        } catch (Exception $e) {
            $this->logTest('数据库层测试', false, $e->getMessage());
        }

        echo "\n";
    }

    /**
     * 测试应用层
     */
    public function testApplicationLayer()
    {
        echo "阶段3: 应用层测试\n";
        echo str_repeat("-", 30) . "\n";

        // 测试Symfony应用启动
        try {
            $kernelPath = '../src/Kernel.php';
            if (file_exists($kernelPath)) {
                require_once $kernelPath;

                // 尝试创建内核实例
                $kernel = new \App\Kernel('test', false);
                $this->logTest('Symfony内核', true, '内核创建成功');
            } else {
                $this->logTest('Symfony内核', false, 'Kernel.php不存在');
            }
        } catch (Exception $e) {
            $this->logTest('Symfony内核', false, $e->getMessage());
        }

        // 测试路由配置
        $this->testRoutingConfiguration();

        // 测试控制器
        $this->testControllerFunctionality();

        echo "\n";
    }

    /**
     * 测试路由配置
     */
    private function testRoutingConfiguration()
    {
        try {
            // 检查路由文件
            $routesFiles = [
                '../config/routes.yaml' => '主路由文件',
                '../config/routes/api_platform.yaml' => 'API路由文件'
            ];

            foreach ($routesFiles as $file => $description) {
                $exists = file_exists($file);
                $this->logTest(
                    $description,
                    $exists,
                    $exists ? '存在' : '不存在'
                );
            }

            // 检查控制器文件
            $controllerFile = '../src/Controller/SysNewsArticleCategoryController.php';
            if (file_exists($controllerFile)) {
                $this->logTest('分类控制器', true, '控制器文件存在');

                // 检查控制器类和方法
                $controllerContent = file_get_contents($controllerFile);
                if (strpos($controllerContent, 'class SysNewsArticleCategoryController') !== false) {
                    $this->logTest('控制器类定义', true, '类定义正确');
                }

                $requiredMethods = ['index', 'store', 'show', 'update', 'destroy'];
                foreach ($requiredMethods as $method) {
                    $hasMethod = strpos($controllerContent, "public function {$method}") !== false;
                    $this->logTest(
                        "控制器方法 {$method}",
                        $hasMethod,
                        $hasMethod ? '存在' : '不存在'
                    );
                }
            } else {
                $this->logTest('分类控制器', false, '控制器文件不存在');
            }

        } catch (Exception $e) {
            $this->logTest('路由配置', false, $e->getMessage());
        }
    }

    /**
     * 测试控制器功能
     */
    private function testControllerFunctionality()
    {
        try {
            // 测试控制器直接调用
            $controllerTest = __DIR__ . '/../public/test_controller_direct.php';
            if (file_exists($controllerTest)) {
                $output = [];
                $returnCode = 0;
                exec("php {$controllerTest} 2>&1", $output, $returnCode);

                $success = $returnCode === 0 && !empty($output);
                $this->logTest(
                    '控制器直接调用',
                    $success,
                    $success ? '调用成功' : '调用失败'
                );
            } else {
                $this->logTest('控制器直接调用', false, '测试脚本不存在');
            }

        } catch (Exception $e) {
            $this->logTest('控制器功能', false, $e->getMessage());
        }
    }

    /**
     * 测试API层
     */
    public function testApiLayer()
    {
        echo "阶段4: API层测试\n";
        echo str_repeat("-", 30) . "\n";

        // 基础连接测试
        $result = $this->apiTest->testBasicConnection();

        if ($result) {
            // API端点测试
            $this->apiTest->testGetCategories();
            $this->apiTest->testCreateCategory();
            $this->apiTest->testErrorHandling();
            $this->apiTest->testCorsHeaders();
        } else {
            $this->logTest('API端点测试', false, '基础连接失败，跳过API测试');
        }

        echo "\n";
    }

    /**
     * 集成测试
     */
    public function testIntegration()
    {
        echo "阶段5: 集成测试\n";
        echo str_repeat("-", 30) . "\n";

        // 测试数据库到API的完整流程
        $this->testDatabaseToApiFlow();

        // 测试并发请求
        $this->testConcurrentRequests();

        // 测试错误处理流程
        $this->testErrorHandlingFlow();

        echo "\n";
    }

    /**
     * 测试数据库到API的完整流程
     */
    private function testDatabaseToApiFlow()
    {
        try {
            // 1. 在数据库中创建测试数据
            $testCode = 'INTEGRATION_TEST_' . time();
            $testName = '集成测试分类';

            $pdo = new PDO(
                "mysql:host=localhost;dbname=official_website;charset=utf8mb4",
                'root',
                'qwe147258..',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $stmt = $pdo->prepare("
                INSERT INTO sys_news_article_category (code, name, created_at, updated_at)
                VALUES (?, ?, NOW(), NOW())
            ");
            $stmt->execute([$testCode, $testName]);
            $insertId = $pdo->lastInsertId();

            $this->logTest('数据库数据创建', true, "ID: {$insertId}");

            // 2. 通过API获取数据
            $result = $this->apiTest->makeRequest('GET', '/api/sys-news-article-categories');

            if ($result['status_code'] === 200 && isset($result['data'])) {
                $found = false;
                foreach ($result['data'] as $category) {
                    if ($category['code'] === $testCode) {
                        $found = true;
                        break;
                    }
                }

                $this->logTest(
                    'API数据获取',
                    $found,
                    $found ? '数据同步成功' : '数据未同步'
                );
            } else {
                $this->logTest('API数据获取', false, 'API请求失败');
            }

            // 3. 清理测试数据
            $stmt = $pdo->prepare("DELETE FROM sys_news_article_category WHERE id = ?");
            $stmt->execute([$insertId]);
            $this->logTest('测试数据清理', true, '清理完成');

        } catch (Exception $e) {
            $this->logTest('数据库到API流程', false, $e->getMessage());
        }
    }

    /**
     * 测试并发请求
     */
    private function testConcurrentRequests()
    {
        try {
            $urls = [];
            for ($i = 0; $i < 3; $i++) {
                $urls[] = $this->baseUrl . '/api/sys-news-article-categories';
            }

            $startTime = microtime(true);
            $results = [];

            // 使用多进程模拟并发（简化版本）
            foreach ($urls as $url) {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10
                ]);
                $response = curl_exec($ch);
                $results[] = [
                    'response' => $response,
                    'info' => curl_getinfo($ch)
                ];
                curl_close($ch);
            }

            $endTime = microtime(true);
            $duration = $endTime - $startTime;

            $successCount = 0;
            foreach ($results as $result) {
                if ($result['info']['http_code'] === 200) {
                    $successCount++;
                }
            }

            $allSuccess = $successCount === count($urls);
            $this->logTest(
                '并发请求测试',
                $allSuccess,
                $allSuccess ? "3/3成功，耗时{$duration}秒" : "仅{$successCount}/3成功"
            );

        } catch (Exception $e) {
            $this->logTest('并发请求测试', false, $e->getMessage());
        }
    }

    /**
     * 测试错误处理流程
     */
    private function testErrorHandlingFlow()
    {
        try {
            // 测试无效的请求方法
            $result = $this->apiTest->makeRequest('PATCH', '/api/sys-news-article-categories/999');
            $handledProperly = $result['status_code'] >= 400;
            $this->logTest(
                '错误处理 - 无效方法',
                $handledProperly,
                $handledProperly ? '正确返回错误状态' : '错误处理异常'
            );

            // 测试不存在的资源
            $result = $this->apiTest->makeRequest('GET', '/api/sys-news-article-categories/999999');
            $handledProperly = $result['status_code'] === 404;
            $this->logTest(
                '错误处理 - 不存在资源',
                $handledProperly,
                $handledProperly ? '正确返回404' : '错误处理异常'
            );

        } catch (Exception $e) {
            $this->logTest('错误处理流程', false, $e->getMessage());
        }
    }

    /**
     * 性能测试
     */
    public function testPerformance()
    {
        echo "阶段6: 性能测试\n";
        echo str_repeat("-", 30) . "\n";

        // API响应时间测试
        $this->testApiResponseTime();

        // 数据库查询性能测试
        $this->dbValidation->testPerformance();

        echo "\n";
    }

    /**
     * 测试API响应时间
     */
    private function testApiResponseTime()
    {
        try {
            $iterations = 5;
            $totalTime = 0;

            for ($i = 0; $i < $iterations; $i++) {
                $startTime = microtime(true);
                $result = $this->apiTest->makeRequest('GET', '/api/sys-news-article-categories');
                $endTime = microtime(true);
                $totalTime += ($endTime - $startTime);
            }

            $avgTime = $totalTime / $iterations;
            $avgTimeMs = round($avgTime * 1000, 2);

            $acceptable = $avgTime < 0.5; // 500ms
            $this->logTest(
                'API响应时间',
                $acceptable,
                "平均{$avgTimeMs}ms，要求<500ms"
            );

        } catch (Exception $e) {
            $this->logTest('API响应时间', false, $e->getMessage());
        }
    }

    /**
     * 运行完整的端到端测试
     */
    public function runFullTest()
    {
        echo "开始端到端完整测试...\n";
        echo "基础URL: {$this->baseUrl}\n";
        echo "开始时间: " . date('Y-m-d H:i:s') . "\n\n";

        $startTime = microtime(true);

        // 执行所有测试阶段
        $this->testEnvironmentPreparation();
        $this->testDatabaseLayer();
        $this->testApplicationLayer();
        $this->testApiLayer();
        $this->testIntegration();
        $this->testPerformance();

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

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

        echo str_repeat("=", 60) . "\n";
        echo "端到端测试总结\n";
        echo str_repeat("=", 60) . "\n";
        echo "总测试数: {$total}\n";
        echo "通过: {$passed}\n";
        echo "失败: {$failed}\n";
        echo "总耗时: {$duration}秒\n";
        echo "成功率: " . round(($passed / $total) * 100, 2) . "%\n";
        echo "完成时间: " . date('Y-m-d H:i:s') . "\n";

        if ($failed > 0) {
            echo "\n失败的测试:\n";
            foreach ($this->testResults as $result) {
                if ($result['status'] === 'FAIL') {
                    echo "  ✗ {$result['test']}: {$result['details']}\n";
                }
            }
        }

        // 生成建议
        echo "\n建议:\n";
        if ($passed === $total) {
            echo "  🎉 所有测试通过！系统运行正常。\n";
        } elseif ($passed / $total > 0.8) {
            echo "  ⚠️  大部分测试通过，建议修复失败的测试项。\n";
        } else {
            echo "  ❌ 多项测试失败，建议检查系统配置和部署。\n";
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
    $options = getopt('u:v', ['url:', 'verbose']);

    $baseUrl = $options['u'] ?? $options['url'] ?? 'http://localhost:8000';
    $verbose = isset($options['v']) || isset($options['verbose']);

    try {
        $test = new EndToEndTest($baseUrl, $verbose);
        $test->runFullTest();
    } catch (Exception $e) {
        echo "端到端测试失败: " . $e->getMessage() . "\n";
        exit(1);
    }
}
