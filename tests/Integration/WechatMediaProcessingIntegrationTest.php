<?php

namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use App\Service\MediaResourceProcessor;
use App\Service\ResourceExtractor;
use App\Service\ResourceDownloader;
use App\Service\WechatArticleSyncService;
use App\Media\MediaManagerInterface;
use App\Entity\Ufile;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * 微信媒体处理集成测试
 *
 * 测试完整的媒体资源处理工作流程：
 * 1. URL提取
 * 2. 并发下载
 * 3. 媒体存储
 * 4. URL替换
 * 5. 错误处理
 */
class WechatMediaProcessingIntegrationTest extends TestCase
{
    private $mediaProcessor;
    private $resourceExtractor;
    private $resourceDownloader;
    private $mediaManager;
    private $logger;
    private $entityManager;

    protected function setUp(): void
    {
        // 创建Mock对象
        $this->mediaManager = $this->createMock(MediaManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        // 创建真实的资源下载器（使用Mock HTTP客户端）
        $this->resourceDownloader = new ResourceDownloader(
            $this->mediaManager,
            $this->logger
        );

        // 创建真实的资源提取器
        $this->resourceExtractor = new ResourceExtractor();

        // 创建真实的媒体处理器
        $this->mediaProcessor = new MediaResourceProcessor(
            $this->resourceExtractor,
            $this->resourceDownloader,
            $this->mediaManager,
            $this->logger
        );
    }

    /**
     * 测试完整的媒体处理工作流程
     */
    public function testCompleteMediaProcessingWorkflow()
    {
        // 模拟包含多种媒体资源的微信文章内容
        $originalContent = '
            <article>
                <h1>测试文章标题</h1>
                <p>这是一篇包含多种媒体资源的测试文章。</p>

                <!-- 封面图片 -->
                <img src="https://mmbiz.qpic.cn/mmbiz_jpg/test_cover.jpg" alt="封面图片">

                <!-- 正文图片 -->
                <img src="https://mmbiz.qpic.cn/mmbiz_png/content_image_1.png" alt="内容图片1">
                <img data-src="https://mmbiz.qpic.cn/mmbiz_gif/content_image_2.gif" alt="内容图片2">

                <!-- 视频 -->
                <video src="https://mmfb.qpic.cn/mmfb_video.mp4" poster="https://mmbiz.qpic.cn/video_poster.jpg"></video>

                <!-- 背景图片 -->
                <div style="background-image: url(https://wx.qlogo.cn/background.jpg)"></div>

                <!-- 外部资源（不应该被处理） -->
                <img src="https://example.com/external.jpg" alt="外部图片">
            </article>
        ';

        $thumbUrl = 'https://mmbiz.qpic.cn/mmbiz_jpg/thumb_url.jpg';

        // 模拟HTTP响应
        $responses = [
            new MockResponse('cover-image-content', [
                'http_code' => 200,
                'response_headers' => ['content-type' => ['image/jpeg']]
            ]),
            new MockResponse('content-image-1', [
                'http_code' => 200,
                'response_headers' => ['content-type' => ['image/png']]
            ]),
            new MockResponse('content-image-2', [
                'http_code' => 200,
                'response_headers' => ['content-type' => ['image/gif']]
            ]),
            new MockResponse('video-content', [
                'http_code' => 200,
                'response_headers' => ['content-type' => ['video/mp4']]
            ]),
            new MockResponse('poster-content', [
                'http_code' => 200,
                'response_headers' => ['content-type' => ['image/jpeg']]
            ]),
            new MockResponse('background-content', [
                'http_code' => 200,
                'response_headers' => ['content-type' => ['image/jpeg']]
            ]),
            new MockResponse('thumb-content', [
                'http_code' => 200,
                'response_headers' => ['content-type' => ['image/jpeg']]
            ])
        ];

        $client = new MockHttpClient($responses);

        // 使用反射设置HTTP客户端
        $reflection = new \ReflectionClass($this->resourceDownloader);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->resourceDownloader, $client);

        // 模拟MediaManagerInterface的save方法
        $savedFiles = [];
        $newUrls = [
            'https://example.com/processed_cover.jpg',
            'https://example.com/processed_content_1.png',
            'https://example.com/processed_content_2.gif',
            'https://example.com/processed_video.mp4',
            'https://example.com/processed_poster.jpg',
            'https://example.com/processed_background.jpg',
            'https://example.com/processed_thumb.jpg'
        ];

        foreach ($newUrls as $index => $newUrl) {
            $ufile = $this->createMock(Ufile::class);
            $ufile->method('getUrl')->willReturn($newUrl);
            $savedFiles[] = $ufile;
        }

        $this->mediaManager
            ->expects($this->exactly(7))
            ->method('save')
            ->willReturnOnConsecutiveCalls(...$savedFiles);

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('info');

        // 执行媒体处理
        $result = $this->mediaProcessor->processArticleMedia($originalContent, $thumbUrl);

        // 验证处理结果
        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('thumb_url', $result);
        $this->assertArrayHasKey('processed_resources', $result);

        // 验证内容中的URL替换
        $processedContent = $result['content'];

        // 检查所有微信资源URL都被替换
        $this->assertStringNotContainsString('https://mmbiz.qpic.cn/mmbiz_jpg/test_cover.jpg', $processedContent);
        $this->assertStringNotContainsString('https://mmbiz.qpic.cn/mmbiz_png/content_image_1.png', $processedContent);
        $this->assertStringNotContainsString('https://mmbiz.qpic.cn/mmbiz_gif/content_image_2.gif', $processedContent);
        $this->assertStringNotContainsString('https://mmfb.qpic.cn/mmfb_video.mp4', $processedContent);
        $this->assertStringNotContainsString('https://mmbiz.qpic.cn/video_poster.jpg', $processedContent);
        $this->assertStringNotContainsString('https://wx.qlogo.cn/background.jpg', $processedContent);

        // 检查新URL存在
        foreach ($newUrls as $newUrl) {
            if ($newUrl !== 'https://example.com/processed_thumb.jpg') {
                $this->assertStringContainsString($newUrl, $processedContent);
            }
        }

        // 检查外部资源未被处理
        $this->assertStringContainsString('https://example.com/external.jpg', $processedContent);

        // 验证缩略图URL被替换
        $this->assertEquals('https://example.com/processed_thumb.jpg', $result['thumb_url']);

        // 验证处理资源记录
        $this->assertCount(6, $result['processed_resources']); // 不包括缩略图

        // 验证每个处理资源的记录
        $expectedUrls = [
            'https://mmbiz.qpic.cn/mmbiz_jpg/test_cover.jpg',
            'https://mmbiz.qpic.cn/mmbiz_png/content_image_1.png',
            'https://mmbiz.qpic.cn/mmbiz_gif/content_image_2.gif',
            'https://mmfb.qpic.cn/mmfb_video.mp4',
            'https://mmbiz.qpic.cn/video_poster.jpg',
            'https://wx.qlogo.cn/background.jpg'
        ];

        foreach ($result['processed_resources'] as $resource) {
            $this->assertArrayHasKey('original_url', $resource);
            $this->assertArrayHasKey('new_url', $resource);
            $this->assertArrayHasKey('mime_type', $resource);
            $this->assertArrayHasKey('file_size', $resource);
            $this->assertContains($resource['original_url'], $expectedUrls);
        }
    }

    /**
     * 测试批量处理多篇文章
     */
    public function testBatchProcessMultipleArticles()
    {
        $articles = [
            [
                'content' => '<img src="https://mmbiz.qpic.cn/article1_img1.jpg">',
                'thumb_url' => 'https://mmbiz.qpic.cn/article1_thumb.jpg'
            ],
            [
                'content' => '<img src="https://mmbiz.qpic.cn/article2_img1.jpg"><img src="https://mmbiz.qpic.cn/article2_img2.jpg">',
                'thumb_url' => 'https://mmbiz.qpic.cn/article2_thumb.jpg'
            ],
            [
                'content' => '<video src="https://mmfb.qpic.cn/article3_video.mp4"></video>',
                'thumb_url' => null
            ]
        ];

        // 模拟HTTP响应
        $responses = [];
        for ($i = 1; $i <= 6; $i++) {
            $responses[] = new MockResponse("content-{$i}", [
                'http_code' => 200,
                'response_headers' => ['content-type' => ['image/jpeg']]
            ]);
        }

        $client = new MockHttpClient($responses);
        $reflection = new \ReflectionClass($this->resourceDownloader);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->resourceDownloader, $client);

        // 模拟MediaManagerInterface
        $ufiles = [];
        for ($i = 1; $i <= 6; $i++) {
            $ufile = $this->createMock(Ufile::class);
            $ufile->method('getUrl')->willReturn("https://example.com/processed_{$i}.jpg");
            $ufiles[] = $ufile;
        }

        $this->mediaManager
            ->expects($this->exactly(6))
            ->method('save')
            ->willReturnOnConsecutiveCalls(...$ufiles);

        // 执行批量处理
        $results = [];
        foreach ($articles as $index => $article) {
            $result = $this->mediaProcessor->processArticleMedia(
                $article['content'],
                $article['thumb_url']
            );
            $results[] = $result;
        }

        // 验证批量处理结果
        $this->assertCount(3, $results);

        // 验证每篇文章的处理结果
        $this->assertCount(1, $results[0]['processed_resources']); // 文章1：1张图片
        $this->assertCount(2, $results[1]['processed_resources']); // 文章2：2张图片
        $this->assertCount(1, $results[2]['processed_resources']); // 文章3：1个视频

        // 验证缩略图处理
        $this->assertNotNull($results[0]['thumb_url']);
        $this->assertNotNull($results[1]['thumb_url']);
        $this->assertNull($results[2]['thumb_url']); // 原来就是null
    }

    /**
     * 测试错误处理和恢复
     */
    public function testErrorHandlingAndRecovery()
    {
        $content = '
            <img src="https://mmbiz.qpic.cn/success.jpg">
            <img src="https://mmbiz.qpic.cn/timeout.jpg">
            <img src="https://mmbiz.qpic.cn/404.jpg">
            <img src="https://mmbiz.qpic.cn/server_error.jpg">
        ';

        // 模拟不同的HTTP响应
        $responses = [
            new MockResponse('success-content', ['http_code' => 200, 'response_headers' => ['content-type' => ['image/jpeg']]]),
            new MockResponse('', ['http_code' => 408]), // 超时
            new MockResponse('', ['http_code' => 404]), // 不存在
            new MockResponse('', ['http_code' => 500])  // 服务器错误
        ];

        $client = new MockHttpClient($responses);
        $reflection = new \ReflectionClass($this->resourceDownloader);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->resourceDownloader, $client);

        // 只有成功下载的资源会被保存
        $ufile = $this->createMock(Ufile::class);
        $ufile->method('getUrl')->willReturn('https://example.com/success.jpg');

        $this->mediaManager
            ->expects($this->once())
            ->method('save')
            ->willReturn($ufile);

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('error');

        // 执行处理
        $result = $this->mediaProcessor->processArticleMedia($content, null);

        // 验证错误处理
        $this->assertNotEmpty($result['processed_resources']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('https://example.com/success.jpg', $result['content']);
        $this->assertStringContainsString('https://mmbiz.qpic.cn/timeout.jpg', $result['content']); // 失败的URL保持不变
    }

    /**
     * 测试并发下载性能
     */
    public function testConcurrentDownloadPerformance()
    {
        // 创建多个微信资源URL
        $urls = [];
        for ($i = 1; $i <= 10; $i++) {
            $urls[] = "https://mmbiz.qpic.cn/performance_test_{$i}.jpg";
        }

        // 设置HTTP客户端模拟
        $responses = [];
        for ($i = 1; $i <= 10; $i++) {
            $responses[] = new MockResponse("fake-content-{$i}", [
                'http_code' => 200,
                'response_headers' => ['content-type' => ['image/jpeg']]
            ]);
        }

        $client = new MockHttpClient($responses);
        $reflection = new \ReflectionClass($this->resourceDownloader);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->resourceDownloader, $client);

        // 模拟MediaManagerInterface
        $ufiles = [];
        for ($i = 1; $i <= 10; $i++) {
            $ufile = $this->createMock(Ufile::class);
            $ufile->method('getUrl')->willReturn("https://example.com/performance_{$i}.jpg");
            $ufiles[] = $ufile;
        }

        $this->mediaManager
            ->expects($this->exactly(10))
            ->method('save')
            ->willReturnOnConsecutiveCalls(...$ufiles);

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('info');

        // 测试并发下载
        $startTime = microtime(true);
        $downloadResult = $this->resourceDownloader->downloadMultiple($urls, [
            'max_concurrent' => 5,
            'timeout' => 30
        ]);
        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // 验证性能
        $this->assertEquals(10, $downloadResult['total']);
        $this->assertEquals(10, $downloadResult['successful']);
        $this->assertEquals(0, $downloadResult['failed']);
        $this->assertEquals(100.0, $downloadResult['success_rate']);
        $this->assertLessThan(30, $duration); // 应该在30秒内完成

        // 验证下载统计
        $stats = $this->resourceDownloader->getDownloadStats($downloadResult);
        $this->assertEquals(10, $stats['total_downloads']);
        $this->assertEquals(10, $stats['successful_downloads']);
        $this->assertEquals(0, $stats['failed_downloads']);
        $this->assertEquals(100.0, $stats['success_rate']);
    }

    /**
     * 测试URL替换的准确性
     */
    public function testUrlReplacementAccuracy()
    {
        $originalContent = '
            <p>测试内容</p>
            <img src="https://mmbiz.qpic.cn/original1.jpg" alt="原图1">
            <img data-src="https://res.wx.qq.com/original2.png" alt="原图2">
            <div style="background-image: url(https://wx.qlogo.cn/original3.gif)"></div>
            <video src="https://mmfb.qpic.cn/original4.mp4" poster="https://mmbiz.qpic.cn/poster.jpg"></video>
        ';

        // 模拟下载和保存
        $responses = [
            new MockResponse('fake-content-1', ['http_code' => 200, 'response_headers' => ['content-type' => ['image/jpeg']]]),
            new MockResponse('fake-content-2', ['http_code' => 200, 'response_headers' => ['content-type' => ['image/png']]]),
            new MockResponse('fake-content-3', ['http_code' => 200, 'response_headers' => ['content-type' => ['image/gif']]]),
            new MockResponse('fake-content-4', ['http_code' => 200, 'response_headers' => ['content-type' => ['video/mp4']]]),
            new MockResponse('fake-poster', ['http_code' => 200, 'response_headers' => ['content-type' => ['image/jpeg']]])
        ];

        $client = new MockHttpClient($responses);
        $reflection = new \ReflectionClass($this->resourceDownloader);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->resourceDownloader, $client);

        $ufiles = [];
        $newUrls = [
            'https://example.com/new1.jpg',
            'https://example.com/new2.png',
            'https://example.com/new3.gif',
            'https://example.com/new4.mp4',
            'https://example.com/new_poster.jpg'
        ];

        foreach ($newUrls as $index => $newUrl) {
            $ufile = $this->createMock(Ufile::class);
            $ufile->method('getUrl')->willReturn($newUrl);
            $ufiles[] = $ufile;
        }

        $this->mediaManager
            ->expects($this->exactly(5))
            ->method('save')
            ->willReturnOnConsecutiveCalls(...$ufiles);

        // 执行媒体处理
        $result = $this->mediaProcessor->processArticleMedia($originalContent, null);

        // 验证URL替换的准确性
        $processedContent = $result['content'];

        // 检查所有原始URL都被替换
        $this->assertStringNotContainsString('https://mmbiz.qpic.cn/original1.jpg', $processedContent);
        $this->assertStringNotContainsString('https://res.wx.qq.com/original2.png', $processedContent);
        $this->assertStringNotContainsString('https://wx.qlogo.cn/original3.gif', $processedContent);
        $this->assertStringNotContainsString('https://mmfb.qpic.cn/original4.mp4', $processedContent);
        $this->assertStringNotContainsString('https://mmbiz.qpic.cn/poster.jpg', $processedContent);

        // 检查所有新URL都存在
        $this->assertStringContainsString('https://example.com/new1.jpg', $processedContent);
        $this->assertStringContainsString('https://example.com/new2.png', $processedContent);
        $this->assertStringContainsString('https://example.com/new3.gif', $processedContent);
        $this->assertStringContainsString('https://example.com/new4.mp4', $processedContent);
        $this->assertStringContainsString('https://example.com/new_poster.jpg', $processedContent);

        // 验证处理资源记录
        $this->assertCount(5, $result['processed_resources']);

        $processedUrls = array_column($result['processed_resources'], 'new_url');
        foreach ($newUrls as $expectedUrl) {
            $this->assertContains($expectedUrl, $processedUrls);
        }
    }

    /**
     * 测试边界情况处理
     */
    public function testEdgeCaseHandling()
    {
        // 测试空内容
        $result1 = $this->mediaProcessor->processArticleMedia('', null);
        $this->assertEquals('', $result1['content']);
        $this->assertNull($result1['thumb_url']);
        $this->assertEmpty($result1['processed_resources']);

        // 测试只有非微信资源
        $contentWithExternalResources = '
            <img src="https://example.com/external1.jpg">
            <img src="https://external-site.com/external2.png">
        ';
        $result2 = $this->mediaProcessor->processArticleMedia($contentWithExternalResources, null);
        $this->assertEquals($contentWithExternalResources, $result2['content']);
        $this->assertEmpty($result2['processed_resources']);

        // 测试重复URL
        $contentWithDuplicates = '
            <img src="https://mmbiz.qpic.cn/duplicate.jpg">
            <img src="https://mmbiz.qpic.cn/duplicate.jpg">
            <img data-src="https://mmbiz.qpic.cn/duplicate.jpg">
        ';

        $responses = [new MockResponse('fake-duplicate', ['http_code' => 200, 'response_headers' => ['content-type' => ['image/jpeg']]])];
        $client = new MockHttpClient($responses);
        $reflection = new \ReflectionClass($this->resourceDownloader);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->resourceDownloader, $client);

        $ufile = $this->createMock(Ufile::class);
        $ufile->method('getUrl')->willReturn('https://example.com/duplicate.jpg');

        $this->mediaManager
            ->expects($this->once()) // 只应该被调用一次
            ->method('save')
            ->willReturn($ufile);

        $result3 = $this->mediaProcessor->processArticleMedia($contentWithDuplicates, null);

        // 验证重复URL只被处理一次
        $this->assertEquals(1, count($result3['processed_resources']));
        $processedContent = $result3['content'];

        // 计算新URL在内容中出现的次数
        $newUrlCount = substr_count($processedContent, 'https://example.com/duplicate.jpg');
        $this->assertEquals(3, $newUrlCount); // 应该替换所有3个重复的URL
    }
}
