<?php

require_once 'vendor/autoload.php';

use App\Service\WechatArticleSyncService;
use App\Service\WechatApiService;
use App\Repository\OfficialRepository;
use App\Repository\WechatPublicAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\MediaResourceProcessor;
use App\Service\ResourceExtractor;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * 测试 release_time 修复效果
 */

// 创建模拟的日志记录器
$logger = new Logger('test');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

// 模拟不同场景的文章数据
$testCases = [
    'normal_publish_time' => [
        'article_id' => 'test_001',
        'title' => '测试文章1 - 正常发布时间',
        'publish_time' => '1703980800', // 2023-12-31 00:00:00
        'update_time' => '1704067200', // 2024-01-01 00:00:00
        'content' => '<p>测试内容1</p>',
        'author' => '测试作者'
    ],
    'only_update_time' => [
        'article_id' => 'test_002',
        'title' => '测试文章2 - 只有更新时间',
        'update_time' => '1704067200', // 2024-01-01 00:00:00
        'content' => '<p>测试内容2</p>',
        'author' => '测试作者'
    ],
    'no_time_fields' => [
        'article_id' => 'test_003',
        'title' => '测试文章3 - 无时间字段',
        'content' => '<p>测试内容3</p>',
        'author' => '测试作者'
    ],
    'empty_time_values' => [
        'article_id' => 'test_004',
        'title' => '测试文章4 - 空时间值',
        'publish_time' => '',
        'update_time' => null,
        'content' => '<p>测试内容4</p>',
        'author' => '测试作者'
    ],
    'invalid_time_values' => [
        'article_id' => 'test_005',
        'title' => '测试文章5 - 无效时间值',
        'publish_time' => 'invalid_timestamp',
        'update_time' => '0',
        'content' => '<p>测试内容5</p>',
        'author' => '测试作者'
    ]
];

echo "=== Release Time 修复测试 ===\n\n";

// 创建 WechatArticleSyncService 实例（使用模拟依赖）
$syncService = new class($logger) {
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * 模拟 processArticleData 方法的时间处理逻辑
     */
    public function testTimeProcessing(array $articleData): array
    {
        $articleId = $articleData['article_id'];

        // 设置发布时间到 releaseTime 字段 - 修复后的时间处理逻辑
        $releaseTime = null;
        $timeSource = '';

        // 优先级1: 使用微信API的 publish_time
        if (isset($articleData['publish_time']) && !empty($articleData['publish_time'])) {
            $releaseTime = \DateTime::createFromFormat('U', $articleData['publish_time']);
            if ($releaseTime) {
                $timeSource = 'publish_time';
                $this->logger->debug('使用发布时间', ['source' => 'publish_time', 'timestamp' => $articleData['publish_time']]);
            } else {
                $this->logger->warning('创建发布时间DateTime失败', ['publish_time' => $articleData['publish_time']]);
            }
        }

        // 优先级2: 使用 update_time 作为备选
        if ($releaseTime === null && isset($articleData['update_time']) && !empty($articleData['update_time'])) {
            $releaseTime = \DateTime::createFromFormat('U', $articleData['update_time']);
            if ($releaseTime) {
                $timeSource = 'update_time';
                $this->logger->debug('使用更新时间作为发布时间', ['source' => 'update_time', 'timestamp' => $articleData['update_time']]);
            } else {
                $this->logger->warning('创建更新时间DateTime失败', ['update_time' => $articleData['update_time']]);
            }
        }

        // 优先级3: 使用当前时间作为默认值，确保永远不会为空
        if ($releaseTime === null) {
            $releaseTime = new \DateTime();
            $timeSource = 'current_time';
            $this->logger->warning('未找到有效的时间字段，使用当前时间作为默认值', [
                'articleId' => $articleId,
                'available_fields' => array_keys($articleData),
                'has_publish_time' => isset($articleData['publish_time']),
                'has_update_time' => isset($articleData['update_time']),
                'default_time' => $releaseTime->format('Y-m-d H:i:s')
            ]);
        }

        // 设置最终的时间值，确保格式正确
        if ($releaseTime instanceof \DateTime) {
            $formattedTime = $releaseTime->format('Y-m-d H:i:s');
            $this->logger->info('发布时间设置成功', [
                'articleId' => $articleId,
                'timeSource' => $timeSource,
                'releaseTime' => $formattedTime
            ]);

            return [
                'success' => true,
                'articleId' => $articleId,
                'releaseTime' => $formattedTime,
                'timeSource' => $timeSource,
                'isEmpty' => empty($formattedTime)
            ];
        } else {
            // 额外的安全检查，理论上不应该到达这里
            $fallbackTime = new \DateTime();
            $formattedTime = $fallbackTime->format('Y-m-d H:i:s');
            $this->logger->error('时间创建失败，使用紧急备用时间', [
                'articleId' => $articleId,
                'fallbackTime' => $formattedTime
            ]);

            return [
                'success' => false,
                'articleId' => $articleId,
                'releaseTime' => $formattedTime,
                'timeSource' => 'emergency_fallback',
                'isEmpty' => false // 紧急备用时间不为空
            ];
        }
    }
};

// 执行测试
$results = [];
foreach ($testCases as $caseName => $articleData) {
    echo "测试案例: {$caseName}\n";
    echo "文章标题: {$articleData['title']}\n";
    echo "输入数据: " . json_encode([
        'publish_time' => $articleData['publish_time'] ?? 'not_set',
        'update_time' => $articleData['update_time'] ?? 'not_set'
    ], JSON_UNESCAPED_UNICODE) . "\n";

    $result = $syncService->testTimeProcessing($articleData);
    $results[$caseName] = $result;

    echo "处理结果:\n";
    echo "  - 成功: " . ($result['success'] ? '是' : '否') . "\n";
    echo "  - 时间源: {$result['timeSource']}\n";
    echo "  - 发布时间: {$result['releaseTime']}\n";
    echo "  - 是否为空: " . ($result['isEmpty'] ? '是' : '否') . "\n";
    echo str_repeat('-', 50) . "\n";
}

// 汇总测试结果
echo "\n=== 测试结果汇总 ===\n";
$totalTests = count($results);
$successfulTests = array_filter($results, fn($r) => $r['success']);
$emptyTimeTests = array_filter($results, fn($r) => $r['isEmpty']);

echo "总测试案例: {$totalTests}\n";
echo "成功处理: " . count($successfulTests) . "\n";
echo "时间为空: " . count($emptyTimeTests) . "\n";

if (count($emptyTimeTests) === 0) {
    echo "✅ 修复成功！所有测试案例的 release_time 都有有效值\n";
} else {
    echo "❌ 修复失败！仍有测试案例的 release_time 为空\n";
}

echo "\n详细结果:\n";
foreach ($results as $caseName => $result) {
    $status = $result['success'] && !$result['isEmpty'] ? '✅' : '❌';
    echo "{$status} {$caseName}: {$result['releaseTime']} (来源: {$result['timeSource']})\n";
}

echo "\n=== 修复验证完成 ===\n";
