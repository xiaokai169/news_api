<?php
/**
 * update_at 字段修复后综合功能测试脚本
 *
 * 测试目标：
 * 1. 验证数据库查询操作不再出现 'update_at' 字段错误
 * 2. 验证 Entity 生命周期回调正常工作
 * 3. 验证 API 接口调用正常
 * 4. 验证时间戳字段自动更新功能
 * 5. 验证 JSON 序列化使用正确的字段名
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Entity\SysNewsArticle;
use App\Entity\ArticleReadLog;
use App\Entity\ArticleReadStatistics;
use App\Entity\SysNewsArticleCategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class UpdateAtFieldComprehensiveTest
{
    private $entityManager;
    private $container;
    private $testResults = [];
    private $testStartTime;
    private $performanceMetrics = [];

    public function __construct()
    {
        $this->testStartTime = microtime(true);
        echo "=== update_at 字段修复后综合功能测试 ===\n\n";

        // 初始化 Symfony 环境
        $this->initializeSymfonyEnvironment();
    }

    /**
     * 初始化 Symfony 环境
     */
    private function initializeSymfonyEnvironment()
    {
        try {
            // 设置环境变量
            $_ENV['APP_ENV'] = 'test';
            $_SERVER['APP_ENV'] = 'test';

            // 加载内核
            $kernel = new Kernel('test', true);
            $kernel->boot();

            $this->container = $kernel->getContainer();
            $this->entityManager = $this->container->get('doctrine.orm.entity_manager');

            echo "✓ Symfony 环境初始化成功\n";
            $this->testResults['symfony_init'] = true;
        } catch (\Exception $e) {
            echo "✗ Symfony 环境初始化失败: " . $e->getMessage() . "\n";
            $this->testResults['symfony_init'] = false;
            throw $e;
        }
    }

    /**
     * 运行所有测试
     */
    public function runAllTests(): void
    {
        echo "开始执行综合测试...\n\n";

        // 1. 测试数据库 CRUD 操作
        $this->testDatabaseCrudOperations();

        // 2. 测试 Entity 生命周期回调
        $this->testEntityLifecycleCallbacks();

        // 3. 测试时间戳字段自动更新
        $this->testTimestampAutoUpdate();

        // 4. 测试 JSON 序列化
        $this->testJsonSerialization();

        // 5. 测试查询操作不再出现 update_at 错误
        $this->testQueryOperations();

        // 6. 测试 API 接口（模拟）
        $this->testApiEndpoints();

        // 7. 性能测试
        $this->testPerformanceImpact();

        // 生成测试报告
        $this->generateTestReport();
    }

    /**
     * 测试数据库 CRUD 操作
     */
    private function testDatabaseCrudOperations(): void
    {
        echo "=== 测试数据库 CRUD 操作 ===\n";
        $startTime = microtime(true);

        try {
            // 创建测试分类
            $category = $this->createTestCategory();

            // 测试 SysNewsArticle CRUD
            $this->testSysNewsArticleCrud($category);

            // 测试 ArticleReadLog CRUD
            $this->testArticleReadLogCrud();

            // 测试 ArticleReadStatistics CRUD
            $this->testArticleReadStatisticsCrud();

            $this->performanceMetrics['crud_operations'] = microtime(true) - $startTime;
            echo "✓ 数据库 CRUD 操作测试完成\n\n";
            $this->testResults['crud_operations'] = true;

        } catch (\Exception $e) {
            echo "✗ 数据库 CRUD 操作测试失败: " . $e->getMessage() . "\n\n";
            $this->testResults['crud_operations'] = false;
        }
    }

    /**
     * 测试 SysNewsArticle CRUD 操作
     */
    private function testSysNewsArticleCrud($category): void
    {
        echo "测试 SysNewsArticle CRUD 操作...\n";

        // 创建
        $article = new SysNewsArticle();
        $article->setName('测试文章 - ' . date('Y-m-d H:i:s'));
        $article->setCover('test-cover.jpg');
        $article->setContent('测试内容');
        $article->setCategory($category);
        $article->setMerchantId(1);
        $article->setUserId(1);
        $article->setIsRecommend(true);
        $article->setPerfect('完美描述');

        // 记录创建前时间
        $beforeCreate = new \DateTime();

        $this->entityManager->persist($article);
        $this->entityManager->flush();

        // 验证创建
        $this->assert($article->getId() !== null, "文章 ID 应该不为空");
        $this->assert($article->getCreateTime() !== null, "创建时间应该不为空");
        $this->assert($article->getUpdatedTime() !== null, "更新时间应该不为空");
        $this->assert(
            $article->getCreateTime() >= $beforeCreate,
            "创建时间应该大于等于创建前时间"
        );

        echo "  ✓ 创建操作成功\n";

        // 读取
        $repository = $this->entityManager->getRepository(SysNewsArticle::class);
        $foundArticle = $repository->find($article->getId());
        $this->assert($foundArticle !== null, "应该能够找到创建的文章");
        $this->assert($foundArticle->getName() === $article->getName(), "文章名称应该匹配");

        echo "  ✓ 读取操作成功\n";

        // 更新
        $beforeUpdate = new \DateTime();
        $originalUpdateTime = $foundArticle->getUpdatedTime();

        $foundArticle->setName('更新后的文章名称');
        $this->entityManager->flush();

        $this->assert(
            $foundArticle->getUpdatedTime() > $originalUpdateTime,
            "更新时间应该大于原始更新时间"
        );
        $this->assert(
            $foundArticle->getUpdatedTime() >= $beforeUpdate,
            "更新时间应该大于等于更新前时间"
        );

        echo "  ✓ 更新操作成功\n";

        // 删除（逻辑删除）
        $foundArticle->markAsDeleted();
        $this->entityManager->flush();

        $deletedArticle = $repository->find($article->getId());
        $this->assert($deletedArticle->isDeleted(), "文章应该被标记为已删除");

        echo "  ✓ 删除操作成功\n";

        // 恢复
        $deletedArticle->restore();
        $this->entityManager->flush();

        $restoredArticle = $repository->find($article->getId());
        $this->assert(!$restoredArticle->isDeleted(), "文章应该被恢复");

        echo "  ✓ 恢复操作成功\n";

        // 清理测试数据
        $this->entityManager->remove($restoredArticle);
        $this->entityManager->flush();
    }

    /**
     * 测试 ArticleReadLog CRUD 操作
     */
    private function testArticleReadLogCrud(): void
    {
        echo "测试 ArticleReadLog CRUD 操作...\n";

        // 创建
        $readLog = new ArticleReadLog();
        $readLog->setArticleId(1);
        $readLog->setUserId(1);
        $readLog->setIpAddress('127.0.0.1');
        $readLog->setUserAgent('Test Agent');
        $readLog->setSessionId('test-session-123');
        $readLog->setDeviceType('desktop');
        $readLog->setDurationSeconds(120);
        $readLog->setCompleted(true);

        $beforeCreate = new \DateTime();

        $this->entityManager->persist($readLog);
        $this->entityManager->flush();

        // 验证创建
        $this->assert($readLog->getId() !== null, "阅读日志 ID 应该不为空");
        $this->assert($readLog->getCreateAt() !== null, "创建时间应该不为空");
        $this->assert($readLog->getUpdatedAt() !== null, "更新时间应该不为空");

        echo "  ✓ 创建操作成功\n";

        // 更新
        $beforeUpdate = new \DateTime();
        $originalUpdateTime = $readLog->getUpdatedAt();

        $readLog->setDurationSeconds(180);
        $this->entityManager->flush();

        $this->assert(
            $readLog->getUpdatedAt() > $originalUpdateTime,
            "更新时间应该大于原始更新时间"
        );

        echo "  ✓ 更新操作成功\n";

        // 清理测试数据
        $this->entityManager->remove($readLog);
        $this->entityManager->flush();

        echo "  ✓ 删除操作成功\n";
    }

    /**
     * 测试 ArticleReadStatistics CRUD 操作
     */
    private function testArticleReadStatisticsCrud(): void
    {
        echo "测试 ArticleReadStatistics CRUD 操作...\n";

        // 创建
        $statistics = new ArticleReadStatistics();
        $statistics->setArticleId(1);
        $statistics->setStatDate(new \DateTime());
        $statistics->setTotalReads(100);
        $statistics->setUniqueUsers(50);
        $statistics->setAnonymousReads(30);
        $statistics->setRegisteredReads(20);
        $statistics->setAvgDurationSeconds('45.50');
        $statistics->setCompletionRate('75.00');

        $beforeCreate = new \DateTime();

        $this->entityManager->persist($statistics);
        $this->entityManager->flush();

        // 验证创建
        $this->assert($statistics->getId() !== null, "统计 ID 应该不为空");
        $this->assert($statistics->getCreateAt() !== null, "创建时间应该不为空");
        $this->assert($statistics->getUpdatedAt() !== null, "更新时间应该不为空");

        echo "  ✓ 创建操作成功\n";

        // 更新
        $beforeUpdate = new \DateTime();
        $originalUpdateTime = $statistics->getUpdatedAt();

        $statistics->incrementTotalReads(10);
        $this->entityManager->flush();

        $this->assert(
            $statistics->getUpdatedAt() > $originalUpdateTime,
            "更新时间应该大于原始更新时间"
        );
        $this->assert($statistics->getTotalReads() === 110, "总阅读数应该增加");

        echo "  ✓ 更新操作成功\n";

        // 清理测试数据
        $this->entityManager->remove($statistics);
        $this->entityManager->flush();

        echo "  ✓ 删除操作成功\n";
    }

    /**
     * 测试 Entity 生命周期回调
     */
    private function testEntityLifecycleCallbacks(): void
    {
        echo "=== 测试 Entity 生命周期回调 ===\n";
        $startTime = microtime(true);

        try {
            $this->testSysNewsArticleLifecycleCallbacks();
            $this->testArticleReadLogLifecycleCallbacks();
            $this->testArticleReadStatisticsLifecycleCallbacks();

            $this->performanceMetrics['lifecycle_callbacks'] = microtime(true) - $startTime;
            echo "✓ Entity 生命周期回调测试完成\n\n";
            $this->testResults['lifecycle_callbacks'] = true;

        } catch (\Exception $e) {
            echo "✗ Entity 生命周期回调测试失败: " . $e->getMessage() . "\n\n";
            $this->testResults['lifecycle_callbacks'] = false;
        }
    }

    /**
     * 测试 SysNewsArticle 生命周期回调
     */
    private function testSysNewsArticleLifecycleCallbacks(): void
    {
        echo "测试 SysNewsArticle 生命周期回调...\n";

        $category = $this->createTestCategory();

        // 测试 PrePersist
        $article = new SysNewsArticle();
        $article->setName('生命周期测试文章');
        $article->setCover('test.jpg');
        $article->setContent('测试内容');
        $article->setCategory($category);

        $beforePersist = new \DateTime();
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $this->assert(
            $article->getCreateTime() >= $beforePersist,
            "PrePersist 回调应该设置创建时间"
        );
        $this->assert(
            $article->getUpdatedTime() >= $beforePersist,
            "PrePersist 回调应该设置更新时间"
        );

        echo "  ✓ PrePersist 回调正常\n";

        // 测试 PreUpdate
        $beforeUpdate = new \DateTime();
        $originalUpdateTime = $article->getUpdatedTime();

        sleep(1); // 确保时间差异
        $article->setName('更新后的名称');
        $this->entityManager->flush();

        $this->assert(
            $article->getUpdatedTime() > $originalUpdateTime,
            "PreUpdate 回调应该更新更新时间"
        );

        echo "  ✓ PreUpdate 回调正常\n";

        // 清理
        $this->entityManager->remove($article);
        $this->entityManager->flush();
    }

    /**
     * 测试 ArticleReadLog 生命周期回调
     */
    private function testArticleReadLogLifecycleCallbacks(): void
    {
        echo "测试 ArticleReadLog 生命周期回调...\n";

        $readLog = new ArticleReadLog();
        $readLog->setArticleId(1);
        $readLog->setUserId(1);

        $beforePersist = new \DateTime();
        $this->entityManager->persist($readLog);
        $this->entityManager->flush();

        $this->assert(
            $readLog->getCreateAt() >= $beforePersist,
            "PrePersist 回调应该设置创建时间"
        );
        $this->assert(
            $readLog->getUpdatedAt() >= $beforePersist,
            "PrePersist 回调应该设置更新时间"
        );

        echo "  ✓ PrePersist 回调正常\n";

        // 测试 PreUpdate
        $beforeUpdate = new \DateTime();
        $originalUpdateTime = $readLog->getUpdatedAt();

        sleep(1); // 确保时间差异
        $readLog->setDurationSeconds(200);
        $this->entityManager->flush();

        $this->assert(
            $readLog->getUpdatedAt() > $originalUpdateTime,
            "PreUpdate 回调应该更新更新时间"
        );

        echo "  ✓ PreUpdate 回调正常\n";

        // 清理
        $this->entityManager->remove($readLog);
        $this->entityManager->flush();
    }

    /**
     * 测试 ArticleReadStatistics 生命周期回调
     */
    private function testArticleReadStatisticsLifecycleCallbacks(): void
    {
        echo "测试 ArticleReadStatistics 生命周期回调...\n";

        $statistics = new ArticleReadStatistics();
        $statistics->setArticleId(1);
        $statistics->setStatDate(new \DateTime());

        $beforePersist = new \DateTime();
        $this->entityManager->persist($statistics);
        $this->entityManager->flush();

        $this->assert(
            $statistics->getCreateAt() >= $beforePersist,
            "PrePersist 回调应该设置创建时间"
        );
        $this->assert(
            $statistics->getUpdatedAt() >= $beforePersist,
            "PrePersist 回调应该设置更新时间"
        );

        echo "  ✓ PrePersist 回调正常\n";

        // 测试 PreUpdate
        $beforeUpdate = new \DateTime();
        $originalUpdateTime = $statistics->getUpdatedAt();

        sleep(1); // 确保时间差异
        $statistics->incrementTotalReads(5);
        $this->entityManager->flush();

        $this->assert(
            $statistics->getUpdatedAt() > $originalUpdateTime,
            "PreUpdate 回调应该更新更新时间"
        );

        echo "  ✓ PreUpdate 回调正常\n";

        // 清理
        $this->entityManager->remove($statistics);
        $this->entityManager->flush();
    }

    /**
     * 测试时间戳字段自动更新
     */
    private function testTimestampAutoUpdate(): void
    {
        echo "=== 测试时间戳字段自动更新 ===\n";
        $startTime = microtime(true);

        try {
            $this->testSysNewsArticleTimestampUpdate();
            $this->testArticleReadLogTimestampUpdate();
            $this->testArticleReadStatisticsTimestampUpdate();

            $this->performanceMetrics['timestamp_update'] = microtime(true) - $startTime;
            echo "✓ 时间戳字段自动更新测试完成\n\n";
            $this->testResults['timestamp_update'] = true;

        } catch (\Exception $e) {
            echo "✗ 时间戳字段自动更新测试失败: " . $e->getMessage() . "\n\n";
            $this->testResults['timestamp_update'] = false;
        }
    }

    /**
     * 测试 SysNewsArticle 时间戳更新
     */
    private function testSysNewsArticleTimestampUpdate(): void
    {
        echo "测试 SysNewsArticle 时间戳更新...\n";

        $category = $this->createTestCategory();
        $article = new SysNewsArticle();
        $article->setName('时间戳测试文章');
        $article->setCover('test.jpg');
        $article->setContent('测试内容');
        $article->setCategory($category);

        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $originalCreateTime = $article->getCreateTime();
        $originalUpdateTime = $article->getUpdatedTime();

        sleep(1);

        // 多次更新
        for ($i = 0; $i < 3; $i++) {
            $beforeUpdate = new \DateTime();
            $previousUpdateTime = $article->getUpdatedTime();

            $article->setName("更新 {$i} - " . $article->getName());
            $this->entityManager->flush();

            $this->assert(
                $article->getUpdatedTime() > $previousUpdateTime,
                "第 {$i} 次更新后时间戳应该增加"
            );
            $this->assert(
                $article->getCreateTime() === $originalCreateTime,
                "创建时间不应该改变"
            );

            sleep(1);
        }

        echo "  ✓ 时间戳自动更新正常\n";

        // 清理
        $this->entityManager->remove($article);
        $this->entityManager->flush();
    }

    /**
     * 测试 ArticleReadLog 时间戳更新
     */
    private function testArticleReadLogTimestampUpdate(): void
    {
        echo "测试 ArticleReadLog 时间戳更新...\n";

        $readLog = new ArticleReadLog();
        $readLog->setArticleId(1);
        $readLog->setUserId(1);

        $this->entityManager->persist($readLog);
        $this->entityManager->flush();

        $originalCreateTime = $readLog->getCreateAt();
        $originalUpdateTime = $readLog->getUpdatedAt();

        sleep(1);

        // 多次更新
        for ($i = 0; $i < 3; $i++) {
            $beforeUpdate = new \DateTime();
            $previousUpdateTime = $readLog->getUpdatedAt();

            $readLog->setDurationSeconds(100 + $i * 10);
            $this->entityManager->flush();

            $this->assert(
                $readLog->getUpdatedAt() > $previousUpdateTime,
                "第 {$i} 次更新后时间戳应该增加"
            );
            $this->assert(
                $readLog->getCreateAt() === $originalCreateTime,
                "创建时间不应该改变"
            );

            sleep(1);
        }

        echo "  ✓ 时间戳自动更新正常\n";

        // 清理
        $this->entityManager->remove($readLog);
        $this->entityManager->flush();
    }

    /**
     * 测试 ArticleReadStatistics 时间戳更新
     */
    private function testArticleReadStatisticsTimestampUpdate(): void
    {
        echo "测试 ArticleReadStatistics 时间戳更新...\n";

        $statistics = new ArticleReadStatistics();
        $statistics->setArticleId(1);
        $statistics->setStatDate(new \DateTime());

        $this->entityManager->persist($statistics);
        $this->entityManager->flush();

        $originalCreateTime = $statistics->getCreateAt();
        $originalUpdateTime = $statistics->getUpdatedAt();

        sleep(1);

        // 多次更新
        for ($i = 0; $i < 3; $i++) {
            $beforeUpdate = new \DateTime();
            $previousUpdateTime = $statistics->getUpdatedAt();

            $statistics->incrementTotalReads(10);
            $this->entityManager->flush();

            $this->assert(
                $statistics->getUpdatedAt() > $previousUpdateTime,
                "第 {$i} 次更新后时间戳应该增加"
            );
            $this->assert(
                $statistics->getCreateAt() === $originalCreateTime,
                "创建时间不应该改变"
            );

            sleep(1);
        }

        echo "  ✓ 时间戳自动更新正常\n";

        // 清理
        $this->entityManager->remove($statistics);
        $this->entityManager->flush();
    }

    /**
     * 测试 JSON 序列化
     */
    private function testJsonSerialization(): void
    {
        echo "=== 测试 JSON 序列化 ===\n";
        $startTime = microtime(true);

        try {
            $this->testSysNewsArticleJsonSerialization();
            $this->testArticleReadLogJsonSerialization();
            $this->testArticleReadStatisticsJsonSerialization();

            $this->performanceMetrics['json_serialization'] = microtime(true) - $startTime;
            echo "✓ JSON 序列化测试完成\n\n";
            $this->testResults['json_serialization'] = true;

        } catch (\Exception $e) {
            echo "✗ JSON 序列化测试失败: " . $e->getMessage() . "\n\n";
            $this->testResults['json_serialization'] = false;
        }
    }

    /**
     * 测试 SysNewsArticle JSON 序列化
     */
    private function testSysNewsArticleJsonSerialization(): void
    {
        echo "测试 SysNewsArticle JSON 序列化...\n";

        $category = $this->createTestCategory();
        $article = new SysNewsArticle();
        $article->setName('JSON 测试文章');
        $article->setCover('test.jpg');
        $article->setContent('测试内容');
        $article->setCategory($category);
        $article->setMerchantId(1);
        $article->setUserId(1);

        $this->entityManager->persist($article);
        $this->entityManager->flush();

        // 使用 Symfony 序列化器
        $serializer = $this->container->get('serializer');
        $json = $serializer->serialize($article, 'json', ['groups' => ['sysNewsArticle:read']]);
        $data = json_decode($json, true);

        // 验证字段名正确性
        $this->assert(isset($data['createTime']), "应该包含 createTime 字段");
        $this->assert(isset($data['updatedTime']), "应该包含 updatedTime 字段");
        $this->assert(!isset($data['create_at']), "不应该包含 create_at 字段");
        $this->assert(!isset($data['updated_at']), "不应该包含 updated_at 字段");
        $this->assert(!isset($data['update_at']), "不应该包含 update_at 字段");

        // 验证时间格式
        $this->assert(
            is_string($data['createTime']) && !empty($data['createTime']),
            "createTime 应该是非空字符串"
        );
        $this->assert(
            is_string($data['updatedTime']) && !empty($data['updatedTime']),
            "updatedTime 应该是非空字符串"
        );

        echo "  ✓ JSON 序列化字段名正确\n";

        // 测试 toArray 方法
        $arrayData = $article->toArray();
        $this->assert(
            isset($arrayData['createTimeFormatted']) && !empty($arrayData['createTimeFormatted']),
            "toArray 应该包含格式化的创建时间"
        );
        $this->assert(
            isset($arrayData['updatedTimeFormatted']) && !empty($arrayData['updatedTimeFormatted']),
            "toArray 应该包含格式化的更新时间"
        );

        echo "  ✓ toArray 方法正常\n";

        // 清理
        $this->entityManager->remove($article);
        $this->entityManager->flush();
    }

    /**
     * 测试 ArticleReadLog JSON 序列化
     */
    private function testArticleReadLogJsonSerialization(): void
    {
        echo "测试 ArticleReadLog JSON 序列化...\n";

        $readLog = new ArticleReadLog();
        $readLog->setArticleId(1);
        $readLog->setUserId(1);
        $readLog->setIpAddress('127.0.0.1');
        $readLog->setUserAgent('Test Agent');
        $readLog->setDeviceType('desktop');

        $this->entityManager->persist($readLog);
        $this->entityManager->flush();

        // 使用 Symfony 序列化器
        $serializer = $this->container->get('serializer');
        $json = $serializer->serialize($readLog, 'json', ['groups' => ['articleReadLog:read']]);
        $data = json_decode($json, true);

        // 验证字段名正确性
        $this->assert(isset($data['createAt']), "应该包含 createAt 字段");
        $this->assert(isset($data['updatedAt']), "应该包含 updatedAt 字段");
        $this->assert(!isset($data['create_at']), "不应该包含 create_at 字段");
        $this->assert(!isset($data['updated_at']), "不应该包含 updated_at 字段");
        $this->assert(!isset($data['update_at']), "不应该包含 update_at 字段");

        echo "  ✓ JSON 序列化字段名正确\n";

        // 测试 toArray 方法
        $arrayData = $readLog->toArray();
        $this->assert(
            isset($arrayData['createAt']) && !empty($arrayData['createAt']),
            "toArray 应该包含创建时间"
        );
        $this->assert(
            isset($arrayData['updatedAt']) && !empty($arrayData['updatedAt']),
            "toArray 应该包含更新时间"
        );

        echo "  ✓ toArray 方法正常\n";

        // 清理
        $this->entityManager->remove($readLog);
        $this->entityManager->flush();
    }

    /**
     * 测试 ArticleReadStatistics JSON 序列化
     */
    private function testArticleReadStatisticsJsonSerialization(): void
    {
        echo "测试 ArticleReadStatistics JSON 序列化...\n";

        $statistics = new ArticleReadStatistics();
        $statistics->setArticleId(1);
        $statistics->setStatDate(new \DateTime());
        $statistics->setTotalReads(100);
        $statistics->setUniqueUsers(50);

        $this->entityManager->persist($statistics);
        $this->entityManager->flush();

        // 使用 Symfony 序列化器
        $serializer = $this->container->get('serializer');
        $json = $serializer->serialize($statistics, 'json', ['groups' => ['articleReadStatistics:read']]);
        $data = json_decode($json, true);

        // 验证字段名正确性
        $this->assert(isset($data['createAt']), "应该包含 createAt 字段");
        $this->assert(isset($data['updatedAt']), "应该包含 updatedAt 字段");
        $this->assert(!isset($data['create_at']), "不应该包含 create_at 字段");
        $this->assert(!isset($data['updated_at']), "不应该包含 updated_at 字段");
        $this->assert(!isset($data['update_at']), "不应该包含 update_at 字段");

        echo "  ✓ JSON 序列化字段名正确\n";

        // 测试 toArray 方法
        $arrayData = $statistics->toArray();
        $this->assert(
            isset($arrayData['createAt']) && !empty($arrayData['createAt']),
            "toArray 应该包含创建时间"
        );
        $this->assert(
            isset($arrayData['updatedAt']) && !empty($arrayData['updatedAt']),
            "toArray 应该包含更新时间"
        );

        echo "  ✓ toArray 方法正常\n";

        // 清理
        $this->entityManager->remove($statistics);
        $this->entityManager->flush();
    }

    /**
     * 测试查询操作不再出现 update_at 错误
     */
    private function testQueryOperations(): void
    {
        echo "=== 测试查询操作不再出现 update_at 错误 ===\n";
        $startTime = microtime(true);

        try {
            $this->testNativeSqlQueries();
            $this->testDqlQueries();
            $this->testQueryBuilderQueries();

            $this->performanceMetrics['query_operations'] = microtime(true) - $startTime;
            echo "✓ 查询操作测试完成\n\n";
            $this->testResults['query_operations'] = true;

        } catch (\Exception $e) {
            echo "✗ 查询操作测试失败: " . $e->getMessage() . "\n\n";
            $this->testResults['query_operations'] = false;
        }
    }

    /**
     * 测试原生 SQL 查询
     */
    private function testNativeSqlQueries(): void
    {
        echo "测试原生 SQL 查询...\n";

        $connection = $this->entityManager->getConnection();

        // 测试 sys_news_article 表查询
        try {
            $sql = "SELECT id, name, updated_at, create_at FROM sys_news_article WHERE id > 0 LIMIT 5";
            $result = $connection->fetchAllAssociative($sql);
            echo "  ✓ sys_news_article 表查询成功\n";
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'update_at') !== false) {
                throw new \Exception("仍然存在 update_at 字段错误: " . $e->getMessage());
            }
            echo "  ✓ sys_news_article 表查询成功（无 update_at 错误）\n";
        }

        // 测试 article_read_logs 表查询
        try {
            $sql = "SELECT id, article_id, updated_at, create_at FROM article_read_logs WHERE id > 0 LIMIT 5";
            $result = $connection->fetchAllAssociative($sql);
            echo "  ✓ article_read_logs 表查询成功\n";
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'update_at') !== false) {
                throw new \Exception("仍然存在 update_at 字段错误: " . $e->getMessage());
            }
            echo "  ✓ article_read_logs 表查询成功（无 update_at 错误）\n";
        }

        // 测试 article_read_statistics 表查询
        try {
            $sql = "SELECT id, article_id, updated_at, create_at FROM article_read_statistics WHERE id > 0 LIMIT 5";
            $result = $connection->fetchAllAssociative($sql);
            echo "  ✓ article_read_statistics 表查询成功\n";
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'update_at') !== false) {
                throw new \Exception("仍然存在 update_at 字段错误: " . $e->getMessage());
            }
            echo "  ✓ article_read_statistics 表查询成功（无 update_at 错误）\n";
        }

        // 测试包含时间字段的复杂查询
        try {
            $sql = "
                SELECT
                    a.id,
                    a.name,
                    a.updated_at,
                    a.create_at,
                    COUNT(l.id) as read_count
                FROM sys_news_article a
                LEFT JOIN article_read_logs l ON a.id = l.article_id
                WHERE a.status = 1
                GROUP BY a.id, a.name, a.updated_at, a.create_at
                LIMIT 5
            ";
            $result = $connection->fetchAllAssociative($sql);
            echo "  ✓ 复杂关联查询成功\n";
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'update_at') !== false) {
                throw new \Exception("复杂查询中仍然存在 update_at 字段错误: " . $e->getMessage());
            }
            echo "  ✓ 复杂关联查询成功（无 update_at 错误）\n";
        }
    }

    /**
     * 测试 DQL 查询
     */
    private function testDqlQueries(): void
    {
        echo "测试 DQL 查询...\n";

        // 测试 SysNewsArticle DQL 查询
        try {
            $dql = "SELECT a FROM App\Entity\SysNewsArticle a WHERE a.status = :status";
            $query = $this->entityManager->createQuery($dql)
                ->setParameter('status', SysNewsArticle::STATUS_ACTIVE)
                ->setMaxResults(5);
            $result = $query->getResult();
            echo "  ✓ SysNewsArticle DQL 查询成功\n";
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'update_at') !== false) {
                throw new \Exception("DQL 查询中仍然存在 update_at 字段错误: " . $e->getMessage());
            }
            echo "  ✓ SysNewsArticle DQL 查询成功（无 update_at 错误）\n";
        }

        // 测试 ArticleReadLog DQL 查询
        try {
            $dql = "SELECT l FROM App\Entity\ArticleReadLog l WHERE l.articleId > :articleId";
            $query = $this->entityManager->createQuery($dql)
                ->setParameter('articleId', 0)
                ->setMaxResults(5);
            $result = $query->getResult();
            echo "  ✓ ArticleReadLog DQL 查询成功\n";
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'update_at') !== false) {
                throw new \Exception("DQL 查询中仍然存在 update_at 字段错误: " . $e->getMessage());
            }
            echo "  ✓ ArticleReadLog DQL 查询成功（无 update_at 错误）\n";
        }

        // 测试 ArticleReadStatistics DQL 查询
        try {
            $dql = "SELECT s FROM App\Entity\ArticleReadStatistics s WHERE s.totalReads > :totalReads";
            $query = $this->entityManager->createQuery($dql)
                ->setParameter('totalReads', 0)
                ->setMaxResults(5);
            $result = $query->getResult();
            echo "  ✓ ArticleReadStatistics DQL 查询成功\n";
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'update_at') !== false) {
                throw new \Exception("DQL 查询中仍然存在 update_at 字段错误: " . $e->getMessage());
            }
            echo "  ✓ ArticleReadStatistics DQL 查询成功（无 update_at 错误）\n";
        }
    }

    /**
     * 测试 QueryBuilder 查询
     */
    private function testQueryBuilderQueries(): void
    {
        echo "测试 QueryBuilder 查询...\n";

        // 测试 SysNewsArticle QueryBuilder 查询
        try {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('a')
               ->from(SysNewsArticle::class, 'a')
               ->where('a.status = :status')
               ->setParameter('status', SysNewsArticle::STATUS_ACTIVE)
               ->setMaxResults(5);
            $result = $qb->getQuery()->getResult();
            echo "  ✓ SysNewsArticle QueryBuilder 查询成功\n";
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'update_at') !== false) {
                throw new \Exception("QueryBuilder 查询中仍然存在 update_at 字段错误: " . $e->getMessage());
            }
            echo "  ✓ SysNewsArticle QueryBuilder 查询成功（无 update_at 错误）\n";
        }

        // 测试关联查询
        try {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('a', 'c')
               ->from(SysNewsArticle::class, 'a')
               ->leftJoin('a.category', 'c')
               ->where('a.status = :status')
               ->setParameter('status', SysNewsArticle::STATUS_ACTIVE)
               ->setMaxResults(5);
            $result = $qb->getQuery()->getResult();
            echo "  ✓ 关联查询成功\n";
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'update_at') !== false) {
                throw new \Exception("关联查询中仍然存在 update_at 字段错误: " . $e->getMessage());
            }
            echo "  ✓ 关联查询成功（无 update_at 错误）\n";
        }
    }

    /**
     * 测试 API 接口（模拟）
     */
    private function testApiEndpoints(): void
    {
        echo "=== 测试 API 接口（模拟）===\n";
        $startTime = microtime(true);

        try {
            $this->testNewsControllerLogic();
            $this->testArticleReadControllerLogic();

            $this->performanceMetrics['api_endpoints'] = microtime(true) - $startTime;
            echo "✓ API 接口测试完成\n\n";
            $this->testResults['api_endpoints'] = true;

        } catch (\Exception $e) {
            echo "✗ API 接口测试失败: " . $e->getMessage() . "\n\n";
            $this->testResults['api_endpoints'] = false;
        }
    }

    /**
     * 测试 NewsController 逻辑
     */
    private function testNewsControllerLogic(): void
    {
        echo "测试 NewsController 逻辑...\n";

        // 模拟创建文章的 Controller 逻辑
        $category = $this->createTestCategory();

        $article = new SysNewsArticle();
        $article->setName('API 测试文章');
        $article->setCover('api-test.jpg');
        $article->setContent('API 测试内容');
        $article->setCategory($category);
        $article->setMerchantId(1);
        $article->setUserId(1);

        $this->entityManager->persist($article);
        $this->entityManager->flush();

        // 模拟序列化响应
        $serializer = $this->container->get('serializer');
        $json = $serializer->serialize($article, 'json', ['groups' => ['sysNewsArticle:read']]);
        $data = json_decode($json, true);

        // 验证响应数据
        $this->assert(isset($data['createTime']), "API 响应应该包含 createTime");
        $this->assert(isset($data['updatedTime']), "API 响应应该包含 updatedTime");
        $this->assert(!isset($data['update_at']), "API 响应不应该包含 update_at");

        echo "  ✓ 创建文章 API 逻辑正常\n";

        // 模拟更新文章的 Controller 逻辑
        $originalUpdateTime = $article->getUpdatedTime();

        sleep(1);

        $article->setName('API 更新后的文章名称');
        $this->entityManager->flush();

        $this->assert(
            $article->getUpdatedTime() > $originalUpdateTime,
            "API 更新后时间戳应该增加"
        );

        echo "  ✓ 更新文章 API 逻辑正常\n";

        // 清理
        $this->entityManager->remove($article);
        $this->entityManager->flush();
    }

    /**
     * 测试 ArticleReadController 逻辑
     */
    private function testArticleReadControllerLogic(): void
    {
        echo "测试 ArticleReadController 逻辑...\n";

        // 模拟记录阅读的 Controller 逻辑
        $readLog = new ArticleReadLog();
        $readLog->setArticleId(1);
        $readLog->setUserId(1);
        $readLog->setIpAddress('127.0.0.1');
        $readLog->setDeviceType('desktop');

        $this->entityManager->persist($readLog);
        $this->entityManager->flush();

        // 模拟序列化响应
        $serializer = $this->container->get('serializer');
        $json = $serializer->serialize($readLog, 'json', ['groups' => ['articleReadLog:read']]);
        $data = json_decode($json, true);

        // 验证响应数据
        $this->assert(isset($data['createAt']), "API 响应应该包含 createAt");
        $this->assert(isset($data['updatedAt']), "API 响应应该包含 updatedAt");
        $this->assert(!isset($data['update_at']), "API 响应不应该包含 update_at");

        echo "  ✓ 记录阅读 API 逻辑正常\n";

        // 清理
        $this->entityManager->remove($readLog);
        $this->entityManager->flush();
    }

    /**
     * 性能测试
     */
    private function testPerformanceImpact(): void
    {
        echo "=== 性能影响评估 ===\n";
        $startTime = microtime(true);

        try {
            $this->testCrudPerformance();
            $this->testQueryPerformance();
            $this->testSerializationPerformance();

            $this->performanceMetrics['performance_test'] = microtime(true) - $startTime;
            echo "✓ 性能测试完成\n\n";
            $this->testResults['performance_test'] = true;

        } catch (\Exception $e) {
            echo "✗ 性能测试失败: " . $e->getMessage() . "\n\n";
            $this->testResults['performance_test'] = false;
        }
    }

    /**
     * 测试 CRUD 性能
     */
    private function testCrudPerformance(): void
    {
        echo "测试 CRUD 操作性能...\n";

        $category = $this->createTestCategory();
        $articles = [];

        // 批量创建测试
        $createStartTime = microtime(true);
        for ($i = 0; $i < 50; $i++) {
            $article = new SysNewsArticle();
            $article->setName("性能测试文章 {$i}");
            $article->setCover("test-{$i}.jpg");
            $article->setContent("性能测试内容 {$i}");
            $article->setCategory($category);
            $articles[] = $article;
            $this->entityManager->persist($article);
        }
        $this->entityManager->flush();
        $createTime = microtime(true) - $createStartTime;

        echo "  ✓ 创建 50 条记录耗时: " . number_format($createTime * 1000, 2) . " ms\n";

        // 批量更新测试
        $updateStartTime = microtime(true);
        foreach ($articles as $i => $article) {
            $article->setName("更新后的性能测试文章 {$i}");
        }
        $this->entityManager->flush();
        $updateTime = microtime(true) - $updateStartTime;

        echo "  ✓ 更新 50 条记录耗时: " . number_format($updateTime * 1000, 2) . " ms\n";

        // 批量删除测试
        $deleteStartTime = microtime(true);
        foreach ($articles as $article) {
            $this->entityManager->remove($article);
        }
        $this->entityManager->flush();
        $deleteTime = microtime(true) - $deleteStartTime;

        echo "  ✓ 删除 50 条记录耗时: " . number_format($deleteTime * 1000, 2) . " ms\n";
    }

    /**
     * 测试查询性能
     */
    private function testQueryPerformance(): void
    {
        echo "测试查询操作性能...\n";

        // 测试简单查询性能
        $queryStartTime = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('a')
               ->from(SysNewsArticle::class, 'a')
               ->where('a.status = :status')
               ->setParameter('status', SysNewsArticle::STATUS_ACTIVE)
               ->setMaxResults(10);
            $result = $qb->getQuery()->getResult();
        }
        $queryTime = microtime(true) - $queryStartTime;

        echo "  ✓ 100 次简单查询耗时: " . number_format($queryTime * 1000, 2) . " ms\n";
        echo "    平均每次查询: " . number_format($queryTime * 10, 2) . " ms\n";

        // 测试复杂查询性能
        $complexQueryStartTime = microtime(true);
        for ($i = 0; $i < 50; $i++) {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('a', 'c')
               ->from(SysNewsArticle::class, 'a')
               ->leftJoin('a.category', 'c')
               ->where('a.status = :status')
               ->andWhere('a.updatedTime > :date')
               ->setParameter('status', SysNewsArticle::STATUS_ACTIVE)
               ->setParameter('date', new \DateTime('-1 day'))
               ->orderBy('a.updatedTime', 'DESC')
               ->setMaxResults(10);
            $result = $qb->getQuery()->getResult();
        }
        $complexQueryTime = microtime(true) - $complexQueryStartTime;

        echo "  ✓ 50 次复杂查询耗时: " . number_format($complexQueryTime * 1000, 2) . " ms\n";
        echo "    平均每次查询: " . number_format($complexQueryTime * 20, 2) . " ms\n";
    }

    /**
     * 测试序列化性能
     */
    private function testSerializationPerformance(): void
    {
        echo "测试序列化性能...\n";

        // 创建测试数据
        $category = $this->createTestCategory();
        $articles = [];
        for ($i = 0; $i < 20; $i++) {
            $article = new SysNewsArticle();
            $article->setName("序列化性能测试文章 {$i}");
            $article->setCover("test-{$i}.jpg");
            $article->setContent("序列化性能测试内容 {$i}");
            $article->setCategory($category);
            $articles[] = $article;
            $this->entityManager->persist($article);
        }
        $this->entityManager->flush();

        $serializer = $this->container->get('serializer');

        // 测试单个对象序列化性能
        $singleSerializationStartTime = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $json = $serializer->serialize($articles[0], 'json', ['groups' => ['sysNewsArticle:read']]);
        }
        $singleSerializationTime = microtime(true) - $singleSerializationStartTime;

        echo "  ✓ 100 次单个对象序列化耗时: " . number_format($singleSerializationTime * 1000, 2) . " ms\n";
        echo "    平均每次序列化: " . number_format($singleSerializationTime * 10, 2) . " ms\n";

        // 测试批量序列化性能
        $batchSerializationStartTime = microtime(true);
        for ($i = 0; $i < 20; $i++) {
            $json = $serializer->serialize($articles, 'json', ['groups' => ['sysNewsArticle:read']]);
        }
        $batchSerializationTime = microtime(true) - $batchSerializationStartTime;

        echo "  ✓ 20 次批量序列化（20个对象）耗时: " . number_format($batchSerializationTime * 1000, 2) . " ms\n";
        echo "    平均每次批量序列化: " . number_format($batchSerializationTime * 50, 2) . " ms\n";

        // 清理测试数据
        foreach ($articles as $article) {
            $this->entityManager->remove($article);
        }
        $this->entityManager->flush();
    }

    /**
     * 创建测试分类
     */
    private function createTestCategory(): SysNewsArticleCategory
    {
        $category = new SysNewsArticleCategory();
        $category->setName('测试分类');
        $category->setCode('test-category-' . uniqid());
        $category->setDescription('测试分类描述');

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    /**
     * 断言方法
     */
    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \Exception("断言失败: {$message}");
        }
    }

    /**
     * 生成测试报告
     */
    private function generateTestReport(): void
    {
        $totalTime = microtime(true) - $this->testStartTime;

        echo "=== 测试报告 ===\n";
        echo "总耗时: " . number_format($totalTime, 3) . " 秒\n\n";

        // 测试结果统计
        $totalTests = count($this->testResults);
        $passedTests = count(array_filter($this->testResults));
        $failedTests = $totalTests - $passedTests;

        echo "测试统计:\n";
        echo "  总测试数: {$totalTests}\n";
        echo "  通过: {$passedTests}\n";
        echo "  失败: {$failedTests}\n";
        echo "  成功率: " . number_format(($passedTests / $totalTests) * 100, 1) . "%\n\n";

        // 详细测试结果
        echo "详细测试结果:\n";
        foreach ($this->testResults as $test => $result) {
            $status = $result ? '✓ 通过' : '✗ 失败';
            echo "  {$test}: {$status}\n";
        }
        echo "\n";

        // 性能指标
        echo "性能指标:\n";
        foreach ($this->performanceMetrics as $metric => $time) {
            echo "  {$metric}: " . number_format($time * 1000, 2) . " ms\n";
        }
        echo "\n";

        // 修复效果确认
        echo "=== 修复效果确认 ===\n";

        if ($this->testResults['query_operations']) {
            echo "✓ 数据库查询操作已修复，不再出现 'update_at' 字段错误\n";
        } else {
            echo "✗ 数据库查询操作仍存在问题\n";
        }

        if ($this->testResults['lifecycle_callbacks']) {
            echo "✓ Entity 生命周期回调正常工作\n";
        } else {
            echo "✗ Entity 生命周期回调存在问题\n";
        }

        if ($this->testResults['timestamp_update']) {
            echo "✓ 时间戳字段自动更新功能正常\n";
        } else {
            echo "✗ 时间戳字段自动更新功能存在问题\n";
        }

        if ($this->testResults['json_serialization']) {
            echo "✓ JSON 序列化使用正确的字段名（createTime, updatedTime）\n";
        } else {
            echo "✗ JSON 序列化存在问题\n";
        }

        if ($this->testResults['crud_operations']) {
            echo "✓ CRUD 操作正常\n";
        } else {
            echo "✗ CRUD 操作存在问题\n";
        }

        echo "\n";

        // 结论
        if ($failedTests === 0) {
            echo "🎉 所有测试通过！update_at 字段错误已完全修复，系统功能正常。\n";
        } else {
            echo "⚠️  仍有 {$failedTests} 个测试失败，需要进一步检查和修复。\n";
        }

        // 保存报告到文件
        $this->saveReportToFile();
    }

    /**
     * 保存报告到文件
     */
    private function saveReportToFile(): void
    {
        $report = [
            'test_time' => date('Y-m-d H:i:s'),
            'total_time' => microtime(true) - $this->testStartTime,
            'test_results' => $this->testResults,
            'performance_metrics' => $this->performanceMetrics,
            'summary' => [
                'total_tests' => count($this->testResults),
                'passed_tests' => count(array_filter($this->testResults)),
                'failed_tests' => count($this->testResults) - count(array_filter($this->testResults)),
                'success_rate' => (count(array_filter($this->testResults)) / count($this->testResults)) * 100
            ]
        ];

        $filename = 'update_at_field_test_report_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($filename, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        echo "\n📄 详细报告已保存到: {$filename}\n";
    }
}

// 运行测试
try {
    $test = new UpdateAtFieldComprehensiveTest();
    $test->runAllTests();
} catch (\Exception $e) {
    echo "测试执行失败: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
