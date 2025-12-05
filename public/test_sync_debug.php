<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\WechatArticleSyncService;
use App\Repository\WechatPublicAccountRepository;

/**
 * 调试微信同步功能
 */

echo "=== 微信同步调试测试 ===<br>\n";

// 获取EntityManager
$kernel = new \App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

$entityManager = $container->get(EntityManagerInterface::class);
$syncService = $container->get(WechatArticleSyncService::class);
$accountRepository = $container->get(WechatPublicAccountRepository::class);

try {
    // 查找第一个活跃的公众号账户
    $accounts = $accountRepository->findBy(['isActive' => 1]);

    if (empty($accounts)) {
        echo "❌ 没有找到活跃的公众号账户<br>\n";
        exit;
    }

    $account = $accounts[0];
    echo "✅ 找到公众号账户: " . $account->getName() . " (ID: " . $account->getId() . ")<br>\n";
    echo "📱 AppID: " . $account->getAppId() . "<br>\n";
    echo "🔑 AppSecret: " . substr($account->getAppSecret(), 0, 8) . "***<br><br>\n";

    // 执行同步（强制同步）
    echo "🚀 开始强制同步文章...<br>\n";
    $result = $syncService->syncArticles($account->getId(), true, true); // bypassLock = true

    echo "<h3>同步结果:</h3>\n";
    echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>\n";

    if ($result['success']) {
        echo "<h3>✅ 同步成功!</h3>\n";
        echo "📊 统计信息:<br>\n";
        echo "- 总计: " . $result['stats']['total'] . " 篇<br>\n";
        echo "- 新增: " . $result['stats']['created'] . " 篇<br>\n";
        echo "- 更新: " . $result['stats']['updated'] . " 篇<br>\n";
        echo "- 跳过: " . $result['stats']['skipped'] . " 篇<br>\n";
        echo "- 失败: " . $result['stats']['failed'] . " 篇<br>\n";
    } else {
        echo "<h3>❌ 同步失败!</h3>\n";
        echo "错误信息: " . $result['message'] . "<br>\n";
    }

    // 检查数据库中的文章数量
    echo "<h3>📋 数据库检查:</h3>\n";
    $officialRepo = $entityManager->getRepository(\App\Entity\Official::class);
    $totalArticles = $officialRepo->count([]);
    echo "Official表中总文章数: " . $totalArticles . "<br>\n";

    // 显示最近添加的几篇文章
    $recentArticles = $officialRepo->findBy([], ['createAt' => 'DESC'], 5);
    echo "<h4>最近5篇文章:</h4>\n";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>ID</th><th>标题</th><th>创建时间</th><th>文章ID</th></tr>\n";
    foreach ($recentArticles as $article) {
        echo "<tr>";
        echo "<td>" . $article->getId() . "</td>";
        echo "<td>" . htmlspecialchars($article->getTitle()) . "</td>";
        echo "<td>" . $article->getCreateAt()->format('Y-m-d H:i:s') . "</td>";
        echo "<td>" . ($article->getArticleId() ?? 'N/A') . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";

} catch (Exception $e) {
    echo "❌ 测试过程中发生异常: " . $e->getMessage() . "<br>\n";
    echo "堆栈跟踪: <pre>" . $e->getTraceAsString() . "</pre>\n";
}

echo "<br>=== 测试完成 ===<br>\n";
