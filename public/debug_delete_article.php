<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

// 加载环境变量
(new Dotenv())->loadEnv(__DIR__ . '/../.env');

// 创建Symfony内核
$kernel = new Kernel($_ENV['APP_ENV'] ?? 'dev', (bool) ($_ENV['APP_DEBUG'] ?? true));
$kernel->boot();

// 获取容器和服务
$container = $kernel->getContainer();
$entityManager = $container->get('doctrine.orm.entity_manager');
$articleRepository = $container->get('App\Repository\OfficialRepository');

echo "=== DELETE接口调试脚本 ===\n\n";

// 测试文章ID
$testId = 42;

try {
    echo "1. 查询文章ID {$testId} 的当前状态...\n";

    // 查询文章
    $article = $articleRepository->find($testId);

    if (!$article) {
        echo "❌ 文章ID {$testId} 不存在\n";
        exit(1);
    }

    echo "✅ 找到文章:\n";
    echo "   ID: " . $article->getId() . "\n";
    echo "   标题: " . $article->getTitle() . "\n";
    echo "   is_deleted: " . ($article->getIsDeleted() ? 'true' : 'false') . "\n";
    echo "   updated_at: " . $article->getUpdatedAt()->format('Y-m-d H:i:s') . "\n";
    echo "   article_id: " . ($article->getArticleId() ?? 'null') . "\n\n";

    echo "2. 检查数据库中的原始数据...\n";

    // 直接查询数据库
    $connection = $entityManager->getConnection();
    $sql = "SELECT id, title, is_deleted, updated_at, article_id FROM official WHERE id = :id";
    $stmt = $connection->prepare($sql);
    $result = $stmt->executeQuery(['id' => $testId]);
    $dbData = $result->fetchAssociative();

    if ($dbData) {
        echo "✅ 数据库记录:\n";
        echo "   ID: " . $dbData['id'] . "\n";
        echo "   标题: " . $dbData['title'] . "\n";
        echo "   is_deleted: " . $dbData['is_deleted'] . "\n";
        echo "   updated_at: " . $dbData['updated_at'] . "\n";
        echo "   article_id: " . ($dbData['article_id'] ?? 'null') . "\n\n";
    }

    echo "3. 模拟删除操作...\n";

    // 开始事务
    $entityManager->beginTransaction();

    try {
        // 标记为删除
        $article->setIsDeleted(true);
        $article->setUpdatedAt(new \DateTime());

        // 持久化
        $entityManager->persist($article);
        $entityManager->flush();

        // 提交事务
        $entityManager->commit();

        echo "✅ 删除操作成功提交\n\n";

    } catch (\Exception $e) {
        $entityManager->rollback();
        echo "❌ 删除操作失败: " . $e->getMessage() . "\n\n";
    }

    echo "4. 验证删除后的状态...\n";

    // 重新查询
    $entityManager->clear(); // 清理实体管理器缓存
    $deletedArticle = $articleRepository->find($testId);

    if ($deletedArticle) {
        echo "✅ 删除后状态:\n";
        echo "   ID: " . $deletedArticle->getId() . "\n";
        echo "   标题: " . $deletedArticle->getTitle() . "\n";
        echo "   is_deleted: " . ($deletedArticle->getIsDeleted() ? 'true' : 'false') . "\n";
        echo "   updated_at: " . $deletedArticle->getUpdatedAt()->format('Y-m-d H:i:s') . "\n";
    }

    // 再次查询数据库
    $result = $stmt->executeQuery(['id' => $testId]);
    $dbDataAfter = $result->fetchAssociative();

    if ($dbDataAfter) {
        echo "\n✅ 数据库删除后记录:\n";
        echo "   ID: " . $dbDataAfter['id'] . "\n";
        echo "   标题: " . $dbDataAfter['title'] . "\n";
        echo "   is_deleted: " . $dbDataAfter['is_deleted'] . "\n";
        echo "   updated_at: " . $dbDataAfter['updated_at'] . "\n";
    }

    echo "\n5. 检查查询方法是否过滤已删除文章...\n";

    // 检查findByCriteria方法
    $articles = $articleRepository->findByCriteria([], 10, 0);
    $foundInList = false;
    foreach ($articles as $a) {
        if ($a->getId() === $testId) {
            $foundInList = true;
            break;
        }
    }

    echo "   文章ID {$testId} 在列表查询中 " . ($foundInList ? "仍然出现" : "已被过滤") . "\n";

    // 检查find方法
    $foundById = $articleRepository->find($testId);
    echo "   文章ID {$testId} 通过find()方法 " . ($foundById ? "仍然可以找到" : "找不到") . "\n";

} catch (\Exception $e) {
    echo "❌ 调试过程中发生错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== 调试完成 ===\n";
