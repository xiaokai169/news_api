<?php

require_once dirname(__DIR__).'/vendor/autoload.php';

use App\Kernel;
use App\Entity\SysNewsArticle;
use App\Entity\SysNewsArticleCategory;
use App\DTO\Filter\NewsFilterDto;
use App\Repository\SysNewsArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Dotenv\Dotenv;

echo "=== 新闻时间字段修复验证脚本 ===\n\n";

// 初始化环境
$dotenv = new Dotenv();
$dotenv->loadEnv(dirname(__DIR__).'/.env');

$kernel = new Kernel('test', true);
$kernel->boot();

$container = $kernel->getContainer();
$entityManager = $container->get(EntityManagerInterface::class);
$newsRepository = $entityManager->getRepository(SysNewsArticle::class);

// 测试结果统计
$tests = [
    'field_mapping' => false,
    'release_time_sorting' => false,
    'dto_default_sorting' => false,
    'time_fields_not_null' => false
];

echo "1. 测试实体字段映射修复\n";
echo "========================\n";

try {
    // 创建测试分类
    $testCategory = new SysNewsArticleCategory();
    $testCategory->setName('测试分类');
    $testCategory->setCode('test_category');
    $testCategory->setMerchantId(1);
    $testCategory->setStatus(SysNewsArticleCategory::STATUS_ACTIVE);
    $testCategory->setCreateAt(new \DateTime());
    $testCategory->setUpdateAt(new \DateTime());

    $entityManager->persist($testCategory);
    $entityManager->flush();

    echo "✓ 创建测试分类成功\n";

    // 创建测试新闻文章
    $testNews1 = new SysNewsArticle();
    $testNews1->setName('测试新闻1 - 最早发布');
    $testNews1->setContent('这是第一篇测试新闻内容');
    $testNews1->setMerchantId(1);
    $testNews1->setUserId(1);
    $testNews1->setStatus(SysNewsArticle::STATUS_ACTIVE);
    $testNews1->setIsRecommend(false);
    $testNews1->setCategory($testCategory);
    $testNews1->setReleaseTime(new \DateTime('2024-01-01 10:00:00'));

    $entityManager->persist($testNews1);

    $testNews2 = new SysNewsArticle();
    $testNews2->setName('测试新闻2 - 中间发布');
    $testNews2->setContent('这是第二篇测试新闻内容');
    $testNews2->setMerchantId(1);
    $testNews2->setUserId(1);
    $testNews2->setStatus(SysNewsArticle::STATUS_ACTIVE);
    $testNews2->setIsRecommend(false);
    $testNews2->setCategory($testCategory);
    $testNews2->setReleaseTime(new \DateTime('2024-01-02 10:00:00'));

    $entityManager->persist($testNews2);

    $testNews3 = new SysNewsArticle();
    $testNews3->setName('测试新闻3 - 最新发布');
    $testNews3->setContent('这是第三篇测试新闻内容');
    $testNews3->setMerchantId(1);
    $testNews3->setUserId(1);
    $testNews3->setStatus(SysNewsArticle::STATUS_ACTIVE);
    $testNews3->setIsRecommend(false);
    $testNews3->setCategory($testCategory);
    $testNews3->setReleaseTime(new \DateTime('2024-01-03 10:00:00'));

    $entityManager->persist($testNews3);

    $entityManager->flush();

    echo "✓ 创建3篇测试新闻成功\n";

    // 验证时间字段是否正确设置
    $testNews1Id = $testNews1->getId();
    $savedNews1 = $newsRepository->find($testNews1Id);

    if ($savedNews1 && $savedNews1->getCreateAt() && $savedNews1->getUpdateAt()) {
        echo "✓ 创建时间和更新时间字段正确设置\n";
        echo "  - 创建时间: " . $savedNews1->getCreateAt()->format('Y-m-d H:i:s') . "\n";
        echo "  - 更新时间: " . $savedNews1->getUpdateAt()->format('Y-m-d H:i:s') . "\n";
        echo "  - 发布时间: " . ($savedNews1->getReleaseTime() ? $savedNews1->getReleaseTime()->format('Y-m-d H:i:s') : 'NULL') . "\n";
        $tests['field_mapping'] = true;
    } else {
        echo "✗ 时间字段设置失败\n";
    }

} catch (Exception $e) {
    echo "✗ 字段映射测试失败: " . $e->getMessage() . "\n";
}

echo "\n2. 测试按发布时间倒序查询\n";
echo "==========================\n";

try {
    // 使用 Repository 方法测试
    $articles = $newsRepository->findByCriteria([], 10, 0, 'releaseTime', 'desc');

    if (count($articles) >= 3) {
        echo "✓ 查询到 " . count($articles) . " 篇文章\n";

        // 检查排序是否正确
        $releaseTimes = [];
        foreach ($articles as $article) {
            if ($article->getReleaseTime()) {
                $releaseTimes[] = $article->getReleaseTime()->getTimestamp();
            }
        }

        if (count($releaseTimes) >= 3) {
            $isCorrectOrder = ($releaseTimes[0] > $releaseTimes[1]) && ($releaseTimes[1] > $releaseTimes[2]);
            if ($isCorrectOrder) {
                echo "✓ 文章按发布时间倒序排列正确\n";
                foreach ($articles as $index => $article) {
                    if ($article->getReleaseTime()) {
                        echo "  " . ($index + 1) . ". " . $article->getName() . " - " . $article->getReleaseTime()->format('Y-m-d H:i:s') . "\n";
                    }
                }
                $tests['release_time_sorting'] = true;
            } else {
                echo "✗ 文章排序不正确\n";
            }
        } else {
            echo "✗ 部分文章缺少发布时间\n";
        }
    } else {
        echo "✗ 查询到的文章数量不足\n";
    }

} catch (Exception $e) {
    echo "✗ 发布时间排序测试失败: " . $e->getMessage() . "\n";
}

echo "\n3. 测试 DTO 默认排序\n";
echo "===================\n";

try {
    // 创建 DTO 测试默认排序
    $filterDto = new NewsFilterDto();

    echo "✓ 创建 NewsFilterDto 成功\n";
    echo "  - 默认排序字段: " . ($filterDto->getSortBy() ?? 'null') . "\n";
    echo "  - 默认排序方向: " . ($filterDto->getSortDirection() ?? 'null') . "\n";

    if ($filterDto->getSortBy() === 'releaseTime' && $filterDto->getSortDirection() === 'desc') {
        echo "✓ DTO 默认排序设置正确\n";
        $tests['dto_default_sorting'] = true;
    } else {
        echo "✗ DTO 默认排序设置不正确\n";
    }

    // 使用 DTO 进行查询测试
    $articlesByDto = $newsRepository->findByFilterDto($filterDto);

    if (count($articlesByDto) >= 3) {
        echo "✓ DTO 查询成功，返回 " . count($articlesByDto) . " 篇文章\n";
    } else {
        echo "✗ DTO 查询返回文章数量不足\n";
    }

} catch (Exception $e) {
    echo "✗ DTO 排序测试失败: " . $e->getMessage() . "\n";
}

echo "\n4. 测试时间字段不为空\n";
echo "====================\n";

try {
    // 查询所有文章并检查时间字段
    $allArticles = $newsRepository->findByCriteria([], 50);

    $emptyFields = [];
    foreach ($allArticles as $article) {
        if (!$article->getCreateAt()) {
            $emptyFields[] = "文章ID {$article->getId()} 缺少创建时间";
        }
        if (!$article->getUpdateAt()) {
            $emptyFields[] = "文章ID {$article->getId()} 缺少更新时间";
        }
    }

    if (empty($emptyFields)) {
        echo "✓ 所有文章的时间字段都正确设置\n";
        $tests['time_fields_not_null'] = true;
    } else {
        echo "✗ 发现空时间字段:\n";
        foreach ($emptyFields as $error) {
            echo "  - " . $error . "\n";
        }
    }

} catch (Exception $e) {
    echo "✗ 时间字段验证失败: " . $e->getMessage() . "\n";
}

echo "\n=== 测试结果总结 ===\n";

$passedTests = array_sum($tests);
$totalTests = count($tests);

echo "通过测试: {$passedTests}/{$totalTests}\n\n";

foreach ($tests as $testName => $result) {
    $status = $result ? '✓ 通过' : '✗ 失败';
    $testNameZh = [
        'field_mapping' => '字段映射修复',
        'release_time_sorting' => '发布时间排序',
        'dto_default_sorting' => 'DTO默认排序',
        'time_fields_not_null' => '时间字段非空'
    ];
    echo "{$status} {$testNameZh[$testName]}\n";
}

echo "\n=== 清理测试数据 ===\n";

try {
    // 删除测试数据
    $testArticles = $newsRepository->findBy(['name' => ['测试新闻1 - 最早发布', '测试新闻2 - 中间发布', '测试新闻3 - 最新发布']]);
    foreach ($testArticles as $article) {
        $entityManager->remove($article);
    }

    $testCategories = $entityManager->getRepository(SysNewsArticleCategory::class)->findBy(['code' => 'test_category']);
    foreach ($testCategories as $category) {
        $entityManager->remove($category);
    }

    $entityManager->flush();
    echo "✓ 测试数据清理完成\n";

} catch (Exception $e) {
    echo "✗ 测试数据清理失败: " . $e->getMessage() . "\n";
}

echo "\n=== 验证完成 ===\n";

if ($passedTests === $totalTests) {
    echo "🎉 所有测试通过！新闻时间字段修复成功！\n";
} else {
    echo "⚠️  部分测试失败，请检查相关修复。\n";
}

$kernel->shutdown();
