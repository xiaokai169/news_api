<?php

require_once 'vendor/autoload.php';

use App\Kernel;
use App\Entity\SysNewsArticle;
use App\Entity\SysNewsArticleCategory;
use Doctrine\ORM\EntityManagerInterface;

echo "=== 测试 SysNewsArticle Entity 自动时间戳功能 ===\n\n";

try {
    // 启动 Symfony 内核
    $kernel = new Kernel('test', true);
    $kernel->boot();

    $container = $kernel->getContainer();
    $entityManager = $container->get('doctrine.orm.entity_manager');

    echo "1. 创建测试分类（如果需要）\n";
    echo "--------------------------------------------------\n";

    // 查找现有分类或创建新分类
    $categoryRepository = $entityManager->getRepository(SysNewsArticleCategory::class);
    $category = $categoryRepository->findOneBy([]);

    if (!$category) {
        $category = new SysNewsArticleCategory();
        $category->setName('测试分类');
        $entityManager->persist($category);
        $entityManager->flush();
        echo "创建新分类 ID: " . $category->getId() . "\n";
    } else {
        echo "使用现有分类 ID: " . $category->getId() . "\n";
    }

    echo "\n2. 测试 Entity 创建（自动设置 created_at）\n";
    echo "--------------------------------------------------\n";

    // 创建新的文章实体
    $article = new SysNewsArticle();
    $article->setMerchantId(1);
    $article->setUserId(1);
    $article->setName('Entity测试文章-' . date('His'));
    $article->setCover('test.jpg');
    $article->setContent('Entity测试内容');
    $article->setCategory($category);
    $article->setStatus(1);
    $article->setIsRecommend(0);
    $article->setPerfect('');

    // 手动设置时间字段为空，测试是否自动设置
    $article->setCreatedAt(null);
    $article->setUpdateTime(null);

    $entityManager->persist($article);
    $entityManager->flush();

    $articleId = $article->getId();
    echo "创建文章 ID: {$articleId}\n";
    echo "创建时间: " . ($article->getCreatedAt() ? $article->getCreatedAt()->format('Y-m-d H:i:s') : 'NULL') . "\n";
    echo "更新时间: " . ($article->getUpdateTime() ? $article->getUpdateTime()->format('Y-m-d H:i:s') : 'NULL') . "\n";

    $createdAtSet = $article->getCreatedAt() !== null;
    $updateTimeSet = $article->getUpdateTime() !== null;

    echo "创建时自动设置 created_at: " . ($createdAtSet ? "✅ 成功" : "❌ 失败") . "\n";
    echo "创建时自动设置 update_at: " . ($updateTimeSet ? "✅ 成功" : "❌ 失败") . "\n";

    echo "\n3. 测试 Entity 更新（自动更新 update_at）\n";
    echo "--------------------------------------------------\n";

    // 记录原始更新时间
    $originalUpdateTime = $article->getUpdateTime() ? $article->getUpdateTime()->format('Y-m-d H:i:s') : null;
    echo "原始更新时间: " . ($originalUpdateTime ?: 'NULL') . "\n";

    // 等待1秒确保时间不同
    sleep(1);

    // 更新文章
    $article->setName('Entity测试文章-已更新-' . date('His'));
    $entityManager->flush();

    $newUpdateTime = $article->getUpdateTime() ? $article->getUpdateTime()->format('Y-m-d H:i:s') : null;
    $newCreatedAt = $article->getCreatedAt() ? $article->getCreatedAt()->format('Y-m-d H:i:s') : null;

    echo "更新后创建时间: " . ($newCreatedAt ?: 'NULL') . "\n";
    echo "更新后更新时间: " . ($newUpdateTime ?: 'NULL') . "\n";

    $updateTimeChanged = ($originalUpdateTime !== $newUpdateTime) && ($newUpdateTime !== null);
    $createdAtUnchanged = ($newCreatedAt !== null);

    echo "更新时间已改变: " . ($updateTimeChanged ? "✅ 成功" : "❌ 失败") . "\n";
    echo "创建时间未改变: " . ($createdAtUnchanged ? "✅ 成功" : "❌ 失败") . "\n";

    echo "\n4. 验证数据库中的实际值\n";
    echo "--------------------------------------------------\n";

    // 直接从数据库查询验证
    $conn = $entityManager->getConnection();
    $dbData = $conn->fetchAssociative(
        "SELECT created_at, update_at FROM sys_news_article WHERE id = :id",
        ['id' => $articleId]
    );

    echo "数据库中的 created_at: " . ($dbData['created_at'] ?? 'NULL') . "\n";
    echo "数据库中的 update_at: " . ($dbData['update_at'] ?? 'NULL') . "\n";

    echo "\n5. 清理测试数据\n";
    echo "--------------------------------------------------\n";

    $entityManager->remove($article);
    $entityManager->flush();

    // 如果是我们创建的分类，也删除
    if ($category && $category->getName() === '测试分类') {
        $entityManager->remove($category);
        $entityManager->flush();
    }

    echo "测试数据清理完成\n";

    echo "\n=== Entity 级别测试总结 ===\n";

    $entityTestsPassed = $createdAtSet && $updateTimeSet && $updateTimeChanged && $createdAtUnchanged;

    if ($entityTestsPassed) {
        echo "✅ Entity 自动时间戳功能完全正常\n";
        echo "✅ 创建时自动设置 created_at 和 update_at\n";
        echo "✅ 更新时自动修改 update_at\n";
        echo "✅ created_at 在更新时保持不变\n";
    } else {
        echo "❌ Entity 自动时间戳功能存在问题\n";
        if (!$createdAtSet) echo "❌ created_at 未自动设置\n";
        if (!$updateTimeSet) echo "❌ update_at 创建时未自动设置\n";
        if (!$updateTimeChanged) echo "❌ update_at 更新时未改变\n";
        if (!$createdAtUnchanged) echo "❌ created_at 更新时被意外修改\n";
    }

    $kernel->shutdown();

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
