<?php
require_once 'vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\EntityManager;
use App\Entity\SysNewsArticle;
use App\Entity\SysNewsArticleCategory;

// 数据库配置
$connectionParams = [
    'dbname' => 'official_website',
    'user' => 'root',
    'password' => 'qwe147258..',
    'host' => '127.0.0.1',
    'port' => '3306',
    'driver' => 'pdo_mysql',
    'charset' => 'utf8mb4',
];

// Doctrine配置
$config = ORMSetup::createAttributeMetadataConfiguration(
    paths: [__DIR__ . '/src/Entity'],
    isDevMode: true,
);

try {
    // 创建EntityManager
    $entityManager = new EntityManager($connectionParams, $config);

    echo "=== 测试 SysNewsArticle 自动时间戳功能 ===\n\n";

    // 1. 测试创建操作
    echo "1. 测试创建文章（自动设置 created_at 和 update_at）\n";
    echo str_repeat("-", 60) . "\n";

    // 首先创建一个分类
    $category = new SysNewsArticleCategory();
    $category->setName('测试分类');
    $category->setMerchantId(1);
    $category->setUserId(1);
    $entityManager->persist($category);
    $entityManager->flush();

    $categoryId = $category->getId();
    echo "创建分类ID: {$categoryId}\n";

    // 创建新文章
    $article = new SysNewsArticle();
    $article->setName('测试文章-' . date('His'));
    $article->setCover('test-cover.jpg');
    $article->setContent('这是测试内容');
    $article->setMerchantId(1);
    $article->setUserId(1);
    $article->setCategory($category);

    $beforeCreate = new \DateTime();
    $entityManager->persist($article);
    $entityManager->flush();
    $afterCreate = new \DateTime();

    $articleId = $article->getId();
    $createdAt = $article->getCreatedAt();
    $updateTime = $article->getUpdateTime();

    echo "文章ID: {$articleId}\n";
    echo "创建时间: " . ($createdAt ? $createdAt->format('Y-m-d H:i:s') : 'NULL') . "\n";
    echo "更新时间: " . ($updateTime ? $updateTime->format('Y-m-d H:i:s') : 'NULL') . "\n";

    // 验证时间是否在合理范围内
    $createdAtValid = $createdAt && $createdAt >= $beforeCreate && $createdAt <= $afterCreate;
    $updateTimeValid = $updateTime && $updateTime >= $beforeCreate && $updateTime <= $afterCreate;

    echo "创建时间自动设置: " . ($createdAtValid ? '✓ 成功' : '✗ 失败') . "\n";
    echo "更新时间自动设置: " . ($updateTimeValid ? '✓ 成功' : '✗ 失败') . "\n\n";

    // 2. 测试更新操作
    echo "2. 测试更新文章（自动更新 update_at）\n";
    echo str_repeat("-", 60) . "\n";

    // 等待一秒确保时间差异
    sleep(1);

    $beforeUpdate = new \DateTime();
    $oldUpdateTime = $article->getUpdateTime();

    // 更新文章
    $article->setName('更新后的文章名称-' . date('His'));
    $article->setContent('更新后的内容');
    $entityManager->flush();
    $afterUpdate = new \DateTime();

    $newUpdateTime = $article->getUpdateTime();
    $newCreatedAt = $article->getCreatedAt();

    echo "旧更新时间: " . ($oldUpdateTime ? $oldUpdateTime->format('Y-m-d H:i:s') : 'NULL') . "\n";
    echo "新更新时间: " . ($newUpdateTime ? $newUpdateTime->format('Y-m-d H:i:s') : 'NULL') . "\n";
    echo "创建时间: " . ($newCreatedAt ? $newCreatedAt->format('Y-m-d H:i:s') : 'NULL') . "\n";

    // 验证更新时间是否正确更新
    $updateTimeChanged = $newUpdateTime > $oldUpdateTime;
    $updateTimeValid = $newUpdateTime >= $beforeUpdate && $newUpdateTime <= $afterUpdate;
    $createdAtUnchanged = $newCreatedAt == $createdAt;

    echo "更新时间已更新: " . ($updateTimeChanged ? '✓ 成功' : '✗ 失败') . "\n";
    echo "更新时间在合理范围: " . ($updateTimeValid ? '✓ 成功' : '✗ 失败') . "\n";
    echo "创建时间未改变: " . ($createdAtUnchanged ? '✓ 成功' : '✗ 失败') . "\n\n";

    // 3. 验证数据库中的实际值
    echo "3. 验证数据库中的字段值\n";
    echo str_repeat("-", 60) . "\n";

    $connection = DriverManager::getConnection($connectionParams);
    $sql = "SELECT id, created_at, update_at FROM sys_news_article WHERE id = :id";
    $result = $connection->fetchAssociative($sql, ['id' => $articleId]);

    if ($result) {
        echo "数据库中的值:\n";
        echo "ID: {$result['id']}\n";
        echo "created_at: " . ($result['created_at'] ?? 'NULL') . "\n";
        echo "update_at: " . ($result['update_at'] ?? 'NULL') . "\n";

        // 验证数据库中的字段名是否正确
        $dbFieldsCorrect = isset($result['created_at']) && isset($result['update_at']);
        echo "数据库字段名正确: " . ($dbFieldsCorrect ? '✓ 成功' : '✗ 失败') . "\n";
    } else {
        echo "✗ 无法从数据库获取记录\n";
    }

    // 4. 清理测试数据
    echo "\n4. 清理测试数据\n";
    echo str_repeat("-", 60) . "\n";

    $entityManager->remove($article);
    $entityManager->remove($category);
    $entityManager->flush();

    echo "测试数据清理完成\n\n";

    echo "=== 测试总结 ===\n";
    $allTestsPassed = $createdAtValid && $updateTimeValid && $updateTimeChanged && $updateTimeValid && $createdAtUnchanged && ($result && $dbFieldsCorrect);

    if ($allTestsPassed) {
        echo "🎉 所有测试通过！时间字段自动更新功能正常工作。\n";
    } else {
        echo "❌ 部分测试失败，请检查配置。\n";
    }

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
