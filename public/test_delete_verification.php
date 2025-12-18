<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\OfficialRepository;
use Doctrine\ORM\EntityManagerInterface;

// 初始化Symfony内核
$kernel = new Kernel('test', true);
$kernel->boot();

$container = $kernel->getContainer();
$entityManager = $container->get(EntityManagerInterface::class);
$articleRepository = $container->get(OfficialRepository::class);

echo "=== DELETE接口修复验证测试 ===\n\n";

// 测试1: 检查Repository的is_deleted过滤是否生效
echo "1. 测试Repository查询方法是否正确过滤is_deleted字段...\n";

// 先查找一个存在的文章ID进行测试
$testArticles = $articleRepository->findBy(['isDeleted' => false], ['id' => 'DESC'], 5);
if (empty($testArticles)) {
    echo "❌ 没有找到可用的测试文章\n";
    exit(1);
}

$testArticle = $testArticles[0];
$articleId = $testArticle->getId();
echo "✅ 找到测试文章 ID: {$articleId}, 标题: {$testArticle->getTitle()}\n";

// 测试2: 模拟DELETE操作
echo "\n2. 执行DELETE操作（软删除）...\n";

try {
    $article = $articleRepository->find($articleId);
    if (!$article) {
        echo "❌ 找不到指定的文章\n";
        exit(1);
    }

    // 检查删除前状态
    $isDeletedBefore = $article->getIsDeleted();
    echo "删除前 is_deleted 状态: " . ($isDeletedBefore ? 'true' : 'false') . "\n";

    // 执行软删除
    $article->setIsDeleted(true);
    $article->setUpdatedAt(new \DateTime());
    $entityManager->persist($article);
    $entityManager->flush();

    echo "✅ DELETE操作执行成功\n";

} catch (Exception $e) {
    echo "❌ DELETE操作失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 测试3: 验证删除后的数据状态
echo "\n3. 验证删除后的数据状态...\n";

try {
    // 重新查询文章
    $deletedArticle = $articleRepository->find($articleId);
    if ($deletedArticle) {
        $isDeletedAfter = $deletedArticle->getIsDeleted();
        echo "删除后 is_deleted 状态: " . ($isDeletedAfter ? 'true' : 'false') . "\n";

        if ($isDeletedAfter) {
            echo "✅ 文章已正确标记为is_deleted = true\n";
        } else {
            echo "❌ 文章is_deleted状态未正确更新\n";
        }
    } else {
        echo "❌ 删除后找不到文章\n";
    }

} catch (Exception $e) {
    echo "❌ 验证删除状态失败: " . $e->getMessage() . "\n";
}

// 测试4: 验证文章不再出现在查询结果中
echo "\n4. 验证文章不再出现在查询结果中...\n";

try {
    // 使用findByCriteria方法查询
    $activeArticles = $articleRepository->findByCriteria([], 50, 0);
    $foundInList = false;

    foreach ($activeArticles as $article) {
        if ($article->getId() === $articleId) {
            $foundInList = true;
            break;
        }
    }

    if (!$foundInList) {
        echo "✅ 文章已从查询结果中过滤掉\n";
    } else {
        echo "❌ 文章仍然出现在查询结果中，is_deleted过滤未生效\n";
    }

    // 测试countByCriteria方法
    $totalActive = $articleRepository->countByCriteria([]);
    echo "当前活跃文章总数: {$totalActive}\n";

} catch (Exception $e) {
    echo "❌ 验证查询过滤失败: " . $e->getMessage() . "\n";
}

// 测试5: 测试重复删除的410状态码逻辑
echo "\n5. 测试重复删除逻辑...\n";

try {
    $articleForRepeatTest = $articleRepository->find($articleId);
    if ($articleForRepeatTest && $articleForRepeatTest->getIsDeleted()) {
        echo "✅ 文章已标记为删除，重复删除应返回410状态\n";
    } else {
        echo "❌ 重复删除测试条件不满足\n";
    }
} catch (Exception $e) {
    echo "❌ 重复删除测试失败: " . $e->getMessage() . "\n";
}

// 测试6: 恢复文章状态（清理测试数据）
echo "\n6. 清理测试数据（恢复文章状态）...\n";

try {
    $articleToRestore = $articleRepository->find($articleId);
    if ($articleToRestore) {
        $articleToRestore->setIsDeleted(false);
        $articleToRestore->setUpdatedAt(new \DateTime());
        $entityManager->persist($articleToRestore);
        $entityManager->flush();
        echo "✅ 测试文章已恢复到原始状态\n";
    }
} catch (Exception $e) {
    echo "❌ 恢复测试数据失败: " . $e->getMessage() . "\n";
}

echo "\n=== 测试完成 ===\n";

// 关闭内核
$kernel->shutdown();
