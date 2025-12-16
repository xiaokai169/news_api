<?php

namespace App\Tests\Integration;

use App\Service\WechatArticleSyncService;
use App\Service\WechatApiService;
use App\Service\MediaResourceProcessor;
use App\Service\ResourceExtractor;
use App\Repository\OfficialRepository;
use App\Repository\WechatPublicAccountRepository;
use App\Entity\Official;
use App\Entity\WechatPublicAccount;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class WechatArticleSyncServiceTest extends TestCase
{
    private $wechatApiService;
    private $officialRepository;
    private $wechatPublicAccountRepository;
    private $entityManager;
    private $mediaResourceProcessor;
    private $resourceExtractor;
    private $logger;
    private $syncService;

    protected function setUp(): void
    {
        $this->wechatApiService = $this->createMock(WechatApiService::class);
        $this->officialRepository = $this->createMock(OfficialRepository::class);
        $this->wechatPublicAccountRepository = $this->createMock(WechatPublicAccountRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->mediaResourceProcessor = $this->createMock(MediaResourceProcessor::class);
        $this->resourceExtractor = $this->createMock(ResourceExtractor::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->syncService = new WechatArticleSyncService(
            $this->wechatApiService,
            $this->officialRepository,
            $this->wechatPublicAccountRepository,
            $this->entityManager,
            $this->mediaResourceProcessor,
            $this->resourceExtractor,
            $this->logger
        );
    }

    /**
     * 测试同步已发布文章成功
     */
    public function testSyncPublishedArticlesSuccess()
    {
        $publicAccountId = 'test_account_123';

        // 模拟公众号
        $publicAccount = $this->createMock(WechatPublicAccount::class);
        $this->wechatPublicAccountRepository
            ->expects($this->once())
            ->method('find')
            ->with($publicAccountId)
            ->willReturn($publicAccount);

        // 模拟访问令牌
        $this->wechatApiService
            ->expects($this->once())
            ->method('getAccessToken')
            ->with($publicAccount)
            ->willReturn('test_access_token');

        // 模拟已发布文章数据
        $publishedItems = [
            [
                'content' => [
                    'news_item' => [
                        [
                            'article_id' => 'article_1',
                            'title' => '测试文章1',
                            'content' => '<p>测试内容1</p><img src="https://mmbiz.qpic.cn/img1.jpg">',
                            'thumb_url' => 'https://wx.qlogo.cn/thumb1.jpg',
                            'author' => '测试作者',
                            'digest' => '测试摘要',
                            'url' => 'https://mp.weixin.qq.com/s/article1',
                            'thumb_media_id' => 'thumb_media_1',
                            'show_cover_pic' => 1,
                            'need_open_comment' => 0,
                            'update_time' => time()
                        ],
                        [
                            'article_id' => 'article_2',
                            'title' => '测试文章2',
                            'content' => '<p>测试内容2</p><img src="https://res.wx.qq.com/img2.png">',
                            'thumb_url' => 'https://mmbiz.qpic.cn/thumb2.jpg',
                            'author' => '测试作者2',
                            'digest' => '测试摘要2',
                            'url' => 'https://mp.weixin.qq.com/s/article2',
                            'thumb_media_id' => 'thumb_media_2',
                            'show_cover_pic' => 0,
                            'need_open_comment' => 1,
                            'update_time' => time()
                        ]
                    ]
                ]
            ]
        ];

        $this->wechatApiService
            ->expects($this->once())
            ->method('getAllPublishedArticles')
            ->willReturn($publishedItems);

        // 模拟文章数据提取
        $expectedArticlesData = [
            [
                'article_id' => 'article_1',
                'title' => '测试文章1',
                'content' => '<p>测试内容1</p><img src="https://mmbiz.qpic.cn/img1.jpg">',
                'thumb_url' => 'https://wx.qlogo.cn/thumb1.jpg',
                'author' => '测试作者',
                'digest' => '测试摘要',
                'url' => 'https://mp.weixin.qq.com/s/article1',
                'thumb_media_id' => 'thumb_media_1',
                'show_cover_pic' => 1,
                'need_open_comment' => 0,
                'update_time' => time()
            ],
            [
                'article_id' => 'article_2',
                'title' => '测试文章2',
                'content' => '<p>测试内容2</p><img src="https://res.wx.qq.com/img2.png">',
                'thumb_url' => 'https://mmbiz.qpic.cn/thumb2.jpg',
                'author' => '测试作者2',
                'digest' => '测试摘要2',
                'url' => 'https://mp.weixin.qq.com/s/article2',
                'thumb_media_id' => 'thumb_media_2',
                'show_cover_pic' => 0,
                'need_open_comment' => 1,
                'update_time' => time()
            ]
        ];

        $this->wechatApiService
            ->expects($this->once())
            ->method('extractAllPublishedArticles')
            ->with($publishedItems)
            ->willReturn($expectedArticlesData);

        // 模拟现有文章检查
        $this->officialRepository
            ->expects($this->exactly(2))
            ->method('findByArticleId')
            ->withConsecutive(['article_1'], ['article_2'])
            ->willReturnOnConsecutiveCalls(null, null);

        // 模拟媒体资源处理
        $this->mediaResourceProcessor
            ->expects($this->exactly(2))
            ->method('processArticleMedia')
            ->willReturnOnConsecutiveCalls(
                [
                    'content' => '<p>测试内容1</p><img src="https://example.com/new_img1.jpg">',
                    'thumb_url' => 'https://example.com/new_thumb1.jpg',
                    'processed_resources' => [
                        ['type' => 'content', 'original_url' => 'https://mmbiz.qpic.cn/img1.jpg', 'new_url' => 'https://example.com/new_img1.jpg'],
                        ['type' => 'thumb', 'original_url' => 'https://wx.qlogo.cn/thumb1.jpg', 'new_url' => 'https://example.com/new_thumb1.jpg']
                    ],
                    'errors' => []
                ],
                [
                    'content' => '<p>测试内容2</p><img src="https://example.com/new_img2.png">',
                    'thumb_url' => 'https://example.com/new_thumb2.jpg',
                    'processed_resources' => [
                        ['type' => 'content', 'original_url' => 'https://res.wx.qq.com/img2.png', 'new_url' => 'https://example.com/new_img2.png'],
                        ['type' => 'thumb', 'original_url' => 'https://mmbiz.qpic.cn/thumb2.jpg', 'new_url' => 'https://example.com/new_thumb2.jpg']
                    ],
                    'errors' => []
                ]
            );

        // 模拟数据库保存
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist');
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush');

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('info');

        // 执行同步
        $result = $this->syncService->syncPublishedArticles($publicAccountId);

        // 验证结果
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['total_processed']);
        $this->assertEquals(2, $result['new_articles']);
        $this->assertEquals(0, $result['updated_articles']);
        $this->assertEmpty($result['errors']);
        $this->assertStringContainsString('同步完成，新增 2 篇', $result['message']);
    }

    /**
     * 测试同步已存在文章的更新
     */
    public function testSyncExistingArticlesUpdate()
    {
        $publicAccountId = 'test_account_123';

        // 模拟公众号
        $publicAccount = $this->createMock(WechatPublicAccount::class);
        $this->wechatPublicAccountRepository
            ->expects($this->once())
            ->method('find')
            ->with($publicAccountId)
            ->willReturn($publicAccount);

        // 模拟访问令牌
        $this->wechatApiService
            ->expects($this->once())
            ->method('getAccessToken')
            ->willReturn('test_access_token');

        // 模拟已发布文章数据
        $publishedItems = [
            [
                'content' => [
                    'news_item' => [
                        [
                            'article_id' => 'existing_article',
                            'title' => '更新后的标题',
                            'content' => '<p>更新后的内容</p>',
                            'thumb_url' => 'https://wx.qlogo.cn/new_thumb.jpg',
                            'update_time' => time()
                        ]
                    ]
                ]
            ]
        ];

        $this->wechatApiService
            ->expects($this->once())
            ->method('getAllPublishedArticles')
            ->willReturn($publishedItems);

        $expectedArticlesData = [
            [
                'article_id' => 'existing_article',
                'title' => '更新后的标题',
                'content' => '<p>更新后的内容</p>',
                'thumb_url' => 'https://wx.qlogo.cn/new_thumb.jpg',
                'update_time' => time()
            ]
        ];

        $this->wechatApiService
            ->expects($this->once())
            ->method('extractAllPublishedArticles')
            ->willReturn($expectedArticlesData);

        // 模拟现有文章
        $existingArticle = $this->createMock(Official::class);
        $existingArticle->method('getId')->willReturn(1);
        $existingArticle->method('getArticleId')->willReturn('existing_article');

        $this->officialRepository
            ->expects($this->once())
            ->method('findByArticleId')
            ->with('existing_article')
            ->willReturn($existingArticle);

        // 模拟媒体资源处理
        $this->mediaResourceProcessor
            ->expects($this->once())
            ->method('processArticleMedia')
            ->willReturn([
                'content' => '<p>更新后的内容</p>',
                'thumb_url' => 'https://example.com/processed_thumb.jpg',
                'processed_resources' => [],
                'errors' => []
            ]);

        // 模拟数据库保存
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($existingArticle);
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('info');

        // 执行同步
        $result = $this->syncService->syncPublishedArticles($publicAccountId);

        // 验证结果
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['total_processed']);
        $this->assertEquals(0, $result['new_articles']);
        $this->assertEquals(1, $result['updated_articles']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * 测试公众号不存在的情况
     */
    public function testSyncWithNonexistentAccount()
    {
        $publicAccountId = 'nonexistent_account';

        // 模拟公众号不存在
        $this->wechatPublicAccountRepository
            ->expects($this->once())
            ->method('find')
            ->with($publicAccountId)
            ->willReturn(null);

        // 执行同步
        $result = $this->syncService->syncPublishedArticles($publicAccountId);

        // 验证结果
        $this->assertFalse($result['success']);
        $this->assertEquals('公众号不存在', $result['message']);
        $this->assertContains('公众号不存在: ' . $publicAccountId, $result['errors']);
    }

    /**
     * 测试获取访问令牌失败
     */
    public function testSyncWithAccessTokenFailure()
    {
        $publicAccountId = 'test_account_123';

        // 模拟公众号
        $publicAccount = $this->createMock(WechatPublicAccount::class);
        $this->wechatPublicAccountRepository
            ->expects($this->once())
            ->method('find')
            ->with($publicAccountId)
            ->willReturn($publicAccount);

        // 模拟访问令牌获取失败
        $this->wechatApiService
            ->expects($this->once())
            ->method('getAccessToken')
            ->willReturn(null);

        // 执行同步
        $result = $this->syncService->syncPublishedArticles($publicAccountId);

        // 验证结果
        $this->assertFalse($result['success']);
        $this->assertEquals('获取访问令牌失败', $result['message']);
        $this->assertContains('获取微信访问令牌失败', $result['errors']);
    }

    /**
     * 测试没有文章的情况
     */
    public function testSyncWithNoArticles()
    {
        $publicAccountId = 'test_account_123';

        // 模拟公众号
        $publicAccount = $this->createMock(WechatPublicAccount::class);
        $this->wechatPublicAccountRepository
            ->expects($this->once())
            ->method('find')
            ->with($publicAccountId)
            ->willReturn($publicAccount);

        // 模拟访问令牌
        $this->wechatApiService
            ->expects($this->once())
            ->method('getAccessToken')
            ->willReturn('test_access_token');

        // 模拟没有文章
        $this->wechatApiService
            ->expects($this->once())
            ->method('getAllPublishedArticles')
            ->willReturn([]);

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('info');

        // 执行同步
        $result = $this->syncService->syncPublishedArticles($publicAccountId);

        // 验证结果
        $this->assertTrue($result['success']);
        $this->assertEquals('没有找到已发布文章', $result['message']);
        $this->assertEquals(0, $result['total_processed']);
        $this->assertEquals(0, $result['new_articles']);
        $this->assertEquals(0, $result['updated_articles']);
    }

    /**
     * 测试媒体资源处理失败的情况
     */
    public function testSyncWithMediaProcessingFailure()
    {
        $publicAccountId = 'test_account_123';

        // 模拟公众号
        $publicAccount = $this->createMock(WechatPublicAccount::class);
        $this->wechatPublicAccountRepository
            ->expects($this->once())
            ->method('find')
            ->with($publicAccountId)
            ->willReturn($publicAccount);

        // 模拟访问令牌
        $this->wechatApiService
            ->expects($this->once())
            ->method('getAccessToken')
            ->willReturn('test_access_token');

        // 模拟已发布文章数据
        $publishedItems = [
            [
                'content' => [
                    'news_item' => [
                        [
                            'article_id' => 'article_with_media',
                            'title' => '包含媒体的文章',
                            'content' => '<p>内容</p><img src="https://mmbiz.qpic.cn/img.jpg">',
                            'thumb_url' => 'https://wx.qlogo.cn/thumb.jpg',
                            'update_time' => time()
                        ]
                    ]
                ]
            ]
        ];

        $this->wechatApiService
            ->expects($this->once())
            ->method('getAllPublishedArticles')
            ->willReturn($publishedItems);

        $expectedArticlesData = [
            [
                'article_id' => 'article_with_media',
                'title' => '包含媒体的文章',
                'content' => '<p>内容</p><img src="https://mmbiz.qpic.cn/img.jpg">',
                'thumb_url' => 'https://wx.qlogo.cn/thumb.jpg',
                'update_time' => time()
            ]
        ];

        $this->wechatApiService
            ->expects($this->once())
            ->method('extractAllPublishedArticles')
            ->willReturn($expectedArticlesData);

        // 模拟现有文章检查
        $this->officialRepository
            ->expects($this->once())
            ->method('findByArticleId')
            ->with('article_with_media')
            ->willReturn(null);

        // 模拟媒体资源处理失败
        $this->mediaResourceProcessor
            ->expects($this->once())
            ->method('processArticleMedia')
            ->willReturn([
                'content' => '<p>内容</p><img src="https://mmbiz.qpic.cn/img.jpg">',
                'thumb_url' => 'https://wx.qlogo.cn/thumb.jpg',
                'processed_resources' => [],
                'errors' => ['下载失败: 网络超时']
            ]);

        // 模拟数据库保存
        $this->entityManager
            ->expects($this->once())
            ->method('persist');
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('warning');
        $this->logger->expects($this->atLeastOnce())->method('info');

        // 执行同步
        $result = $this->syncService->syncPublishedArticles($publicAccountId);

        // 验证结果 - 同步应该成功，但有媒体处理错误
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['total_processed']);
        $this->assertEquals(1, $result['new_articles']);
        $this->assertEquals(0, $result['updated_articles']);
        // 注意：媒体处理错误不会导致整个同步失败
    }

    /**
     * 测试禁用媒体处理的情况
     */
    public function testSyncWithMediaProcessingDisabled()
    {
        $publicAccountId = 'test_account_123';

        // 模拟公众号
        $publicAccount = $this->createMock(WechatPublicAccount::class);
        $this->wechatPublicAccountRepository
            ->expects($this->once())
            ->method('find')
            ->with($publicAccountId)
            ->willReturn($publicAccount);

        // 模拟访问令牌
        $this->wechatApiService
            ->expects($this->once())
            ->method('getAccessToken')
            ->willReturn('test_access_token');

        // 模拟已发布文章数据
        $publishedItems = [
            [
                'content' => [
                    'news_item' => [
                        [
                            'article_id' => 'article_no_media',
                            'title' => '不处理媒体的文章',
                            'content' => '<p>内容</p><img src="https://mmbiz.qpic.cn/img.jpg">',
                            'thumb_url' => 'https://wx.qlogo.cn/thumb.jpg',
                            'update_time' => time()
                        ]
                    ]
                ]
            ]
        ];

        $this->wechatApiService
            ->expects($this->once())
            ->method('getAllPublishedArticles')
            ->willReturn($publishedItems);

        $expectedArticlesData = [
            [
                'article_id' => 'article_no_media',
                'title' => '不处理媒体的文章',
                'content' => '<p>内容</p><img src="https://mmbiz.qpic.cn/img.jpg">',
                'thumb_url' => 'https://wx.qlogo.cn/thumb.jpg',
                'update_time' => time()
            ]
        ];

        $this->wechatApiService
            ->expects($this->once())
            ->method('extractAllPublishedArticles')
            ->willReturn($expectedArticlesData);

        // 模拟现有文章检查
        $this->officialRepository
            ->expects($this->once())
            ->method('findByArticleId')
            ->with('article_no_media')
            ->willReturn(null);

        // 媒体处理器不应该被调用
        $this->mediaResourceProcessor
            ->expects($this->never())
            ->method('processArticleMedia');

        // 模拟数据库保存
        $this->entityManager
            ->expects($this->once())
            ->method('persist');
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('info');

        // 执行同步（禁用媒体处理）
        $options = ['process_media' => false];
        $result = $this->syncService->syncPublishedArticles($publicAccountId, $options);

        // 验证结果
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['total_processed']);
        $this->assertEquals(1, $result['new_articles']);
        $this->assertEquals(0, $result['updated_articles']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * 测试数据库保存失败的情况
     */
    public function testSyncWithDatabaseSaveFailure()
    {
        $publicAccountId = 'test_account_123';

        // 模拟公众号
        $publicAccount = $this->createMock(WechatPublicAccount::class);
        $this->wechatPublicAccountRepository
            ->expects($this->once())
            ->method('find')
            ->with($publicAccountId)
            ->willReturn($publicAccount);

        // 模拟访问令牌
        $this->wechatApiService
            ->expects($this->once())
            ->method('getAccessToken')
            ->willReturn('test_access_token');

        // 模拟已发布文章数据
        $publishedItems = [
            [
                'content' => [
                    'news_item' => [
                        [
                            'article_id' => 'article_save_fail',
                            'title' => '保存失败的文章',
                            'content' => '<p>内容</p>',
                            'update_time' => time()
                        ]
                    ]
                ]
            ]
        ];

        $this->wechatApiService
            ->expects($this->once())
            ->method('getAllPublishedArticles')
            ->willReturn($publishedItems);

        $expectedArticlesData = [
            [
                'article_id' => 'article_save_fail',
                'title' => '保存失败的文章',
                'content' => '<p>内容</p>',
                'update_time' => time()
            ]
        ];

        $this->wechatApiService
            ->expects($this->once())
            ->method('extractAllPublishedArticles')
            ->willReturn($expectedArticlesData);

        // 模拟现有文章检查
        $this->officialRepository
            ->expects($this->once())
            ->method('findByArticleId')
            ->with('article_save_fail')
            ->willReturn(null);

        // 模拟媒体资源处理
        $this->mediaResourceProcessor
            ->expects($this->once())
            ->method('processArticleMedia')
            ->willReturn([
                'content' => '<p>内容</p>',
                'thumb_url' => null,
                'processed_resources' => [],
                'errors' => []
            ]);

        // 模拟数据库保存失败
        $this->entityManager
            ->expects($this->once())
            ->method('persist');
        $this->entityManager
            ->expects($this->once())
            ->method('flush')
            ->willThrowException(new \Exception('数据库连接失败'));

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('error');
        $this->logger->expects($this->atLeastOnce())->method('info');

        // 执行同步
        $result = $this->syncService->syncPublishedArticles($publicAccountId);

        // 验证结果
        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['errors']);
        $this->assertContains('保存文章失败', $result['errors'][0]);
    }

    /**
     * 测试获取同步状态
     */
    public function testGetSyncStatus()
    {
        $accountId = 'test_account_123';

        // 模拟公众号
        $publicAccount = $this->createMock(WechatPublicAccount::class);
        $publicAccount->method('getName')->willReturn('测试公众号');

        $this->wechatPublicAccountRepository
            ->expects($this->once())
            ->method('find')
            ->with($accountId)
            ->willReturn($publicAccount);

        // 模拟统计数据
        $this->officialRepository
            ->expects($this->once())
            ->method('countByAccountId')
            ->with($accountId)
            ->willReturn(10);

        $this->officialRepository
            ->expects($this->once())
            ->method('countActiveByAccountId')
            ->with($accountId)
            ->willReturn(8);

        $lastSyncTime = new \DateTime('2023-12-01 10:00:00');
        $this->officialRepository
            ->expects($this->once())
            ->method('getLastSyncTime')
            ->with($accountId)
            ->willReturn($lastSyncTime);

        // 模拟最近文章
        $recentArticle = $this->createMock(Official::class);
        $recentArticle->method('getId')->willReturn(1);
        $recentArticle->method('getArticleId')->willReturn('recent_article');
        $recentArticle->method('getTitle')->willReturn('最近的文章');
        $recentArticle->method('getUpdatedAt')->willReturn(new \DateTime('2023-12-01 09:00:00'));
        $recentArticle->method('getStatus')->willReturn('active');

        $this->officialRepository
            ->expects($this->once())
            ->method('findRecentByAccountId')
            ->with($accountId, 5)
            ->willReturn([$recentArticle]);

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('info');

        // 执行获取状态
        $status = $this->syncService->getSyncStatus($accountId);

        // 验证结果
        $this->assertEquals($accountId, $status['accountId']);
        $this->assertEquals('测试公众号', $status['accountName']);
        $this->assertTrue($status['exists']);
        $this->assertEquals('2023-12-01 10:00:00', $status['lastSyncTime']);
        $this->assertEquals(10, $status['statistics']['totalArticles']);
        $this->assertEquals(8, $status['statistics']['activeArticles']);
        $this->assertEquals(2, $status['statistics']['inactiveArticles']);
        $this->assertCount(1, $status['recentArticles']);
        $this->assertEquals('needs_sync', $status['syncStatus']); // 假设距离现在超过7天
    }

    /**
     * 测试获取不存在账号的同步状态
     */
    public function testGetSyncStatusForNonexistentAccount()
    {
        $accountId = 'nonexistent_account';

        // 模拟公众号不存在
        $this->wechatPublicAccountRepository
            ->expects($this->once())
            ->method('find')
            ->with($accountId)
            ->willReturn(null);

        // 执行获取状态
        $status = $this->syncService->getSyncStatus($accountId);

        // 验证结果
        $this->assertFalse($status['exists']);
        $this->assertArrayHasKey('error', $status);
        $this->assertStringContainsString('公众号不存在', $status['error']);
    }

    /**
     * 测试syncArticles包装器方法
     */
    public function testSyncArticlesWrapper()
    {
        $accountId = 'test_account_123';

        // 模拟syncPublishedArticles的返回值
        $expectedResult = [
            'success' => true,
            'message' => '同步完成',
            'total_processed' => 5,
            'new_articles' => 3,
            'updated_articles' => 2,
            'errors' => []
        ];

        // 创建部分模拟，只模拟syncPublishedArticles方法
        $partialMock = $this->createPartialMock(WechatArticleSyncService::class, ['syncPublishedArticles']);
        $partialMock->method('syncPublishedArticles')
            ->with($accountId, ['force_sync' => true, 'bypass_lock' => false])
            ->willReturn($expectedResult);

        // 执行包装器方法
        $result = $partialMock->syncArticles($accountId, true, false);

        // 验证结果格式转换
        $this->assertTrue($result['success']);
        $this->assertEquals('同步完成', $result['message']);
        $this->assertEquals(5, $result['stats']['total']);
        $this->assertEquals(3, $result['stats']['created']);
        $this->assertEquals(2, $result['stats']['updated']);
        $this->assertEquals(0, $result['stats']['skipped']);
        $this->assertEquals(0, $result['stats']['failed']);
        $this->assertEmpty($result['errors']);
    }
}
