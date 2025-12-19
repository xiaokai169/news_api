<?php

require_once 'vendor/autoload.php';

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;
use App\Entity\SysNewsArticle;
use App\Entity\SysNewsArticleCategory;

echo "=== 测试 Entity 生命周期回调 ===\n\n";

try {
    // 从 config/packages/doctrine.yaml 读取数据库配置
    $config = Setup::createAnnotationMetadataConfiguration(
        [__DIR__ . '/src/Entity'],
        true,
        null,
        null,
        false
    );

    // 数据库连接配置
    $dbParams = [
        'driver' => 'pdo_mysql',
        'host' => 'localhost',
        'dbname' => 'official_website',
        'user' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'defaultTableOptions' => [
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci'
        ]
    ];

    $entityManager = EntityManager::create($dbParams, $config);

    echo "1. 测试实体创建时的生命周期回调\n";
    echo "--------------------------------------------------\n";

    // 获取现有分类或创建新分类
    $category = $entityManager->getRepository(SysNewsArticleCategory::class)->findOneBy([]);

    if (!$category) {
        echo "没有找到现有分类，跳过测试\n";
        exit;
    }

    // 创建新文章
    $article = new SysNewsArticle();
    $article->setMerchantId(1);
    $article->setUserId(1);
    $article->setName('生命周期测试-' . date('His'));
    $article->setCover('test.jpg');
    $article->setContent('测试内容');
    $article->setCategory($category);
    $article->setStatus(1);
    $article->setIsRecommend(0);
    $article->setPerfect('');

    // 检查生命周期回调是否设置了时间
    echo "持久化之前:\n";
    echo "  createdAt: " . ($article->getCreatedAt() ? $article->getCreatedAt()->format('Y-m-d H:i:s') : 'NULL') . "\n";
    echo "  updateTime: " . ($article->getUpdateTime() ? $article->getUpdateTime()->format('Y-m-d H:i:s') : 'NULL') . "\n";

    $entityManager->persist($article);
    $entityManager->flush();

    echo "持久化之后:\n";
    echo "  createdAt: " . ($article->getCreatedAt() ? $article->getCreatedAt()->format('Y-m-d H:i:s') : 'NULL') . "\n";
    echo "  updateTime: " . ($article->getUpdateTime() ? $article->getUpdateTime()->format('Y-m-d H:i:s') : 'NULL') . "\n";

    $articleId = $article->getId();
    echo "文章ID: {$articleId}\n";

    $createdAtSet = $article->getCreatedAt() !== null;
    $updateTimeSet = $article->getUpdateTime() !== null;

    echo "创建时自动设置 createdAt: " . ($createdAtSet ? "✅ 成功" : "❌ 失败") . "\n";
    echo "创建时自动设置 updateTime: " . ($updateTimeSet ? "✅ 成功" : "❌ 失败") . "\n";

    echo "\n2. 测试实体更新时的生命周期回调\n";
    echo "--------------------------------------------------\n";

    // 记录原始时间
    $originalCreatedAt = $article->getCreatedAt() ? $article->getCreatedAt()->format('Y-m-d H:i:s') : null;
    $originalUpdateTime = $article->getUpdateTime() ? $article->getUpdateTime()->format('Y-m-d H:i:s') : null;

    echo "更新前时间:\n";
    echo "  createdAt: " . ($originalCreatedAt ?: 'NULL') . "\n";
    echo "  updateTime: " . ($originalUpdateTime ?: 'NULL') . "\n";

    // 等待1秒确保时间不同
    sleep(1);

    // 更新文章
    $article->setName('生命周期测试-已更新-' . date('His'));
    $entityManager->flush();

    $newCreatedAt = $article->getCreatedAt() ? $article->getCreatedAt()->format('Y-m-d H:i:s') : null;
    $newUpdateTime = $article->getUpdateTime() ? $article->getUpdateTime()->format('Y-m-d H:i:s') : null;

    echo "更新后时间:\n";
    echo "  createdAt: " . ($newCreatedAt ?: 'NULL') . "\n";
    echo "  updateTime: " . ($newUpdateTime ?: 'NULL') . "\n";

    $updateTimeChanged = ($originalUpdateTime !== $newUpdateTime) && ($newUpdateTime !== null);
    $createdAtUnchanged = ($originalCreatedAt === $newCreatedAt) && ($newCreatedAt !== null);

    echo "更新时间已改变: " . ($updateTimeChanged ? "✅ 成功" : "❌ 失败") . "\n";
    echo "创建时间未改变: " . ($createdAtUnchanged ? "✅ 成功" : "❌ 失败") . "\n";

    echo "\n3. 验证数据库中的实际值\n";
    echo "--------------------------------------------------\n";

    $conn = $entityManager->getConnection();
    $dbData = $conn->fetchAssociative(
        "SELECT created_at, update_at FROM sys_news_article WHERE id = :id",
        ['id' => $articleId]
    );

    echo "数据库记录:\n";
    echo "  created_at: " . ($dbData['created_at'] ?? 'NULL') . "\n";
    echo "  update_at: " . ($dbData['update_at'] ?? 'NULL') . "\n";

    echo "\n4. 清理测试数据\n";
    echo "--------------------------------------------------\n";

    $entityManager->remove($article);
    $entityManager->flush();
    echo "测试数据已清理\n";

    echo "\n=== 测试总结 ===\n";

    $allTestsPassed = $createdAtSet && $updateTimeSet && $updateTimeChanged && $createdAtUnchanged;

    if ($allTestsPassed) {
        echo "✅ Entity 生命周期回调功能完全正常\n";
        echo "✅ PrePersist 回调正确设置时间字段\n";
        echo "✅ PreUpdate 回调正确更新 update_at 字段\n";
    } else {
        echo "❌ Entity 生命周期回调功能存在问题\n";
        if (!$createdAtSet) echo "❌ PrePersist 未设置 createdAt\n";
        if (!$updateTimeSet) echo "❌ PrePersist 未设置 updateTime\n";
        if (!$updateTimeChanged) echo "❌ PreUpdate 未更新 updateTime\n";
        if (!$createdAtUnchanged) echo "❌ createdAt 在更新时被意外修改\n";
    }

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
