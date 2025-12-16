<?php

namespace App\Tests\Service;

use App\Service\ResourceExtractor;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ResourceExtractorTest extends TestCase
{
    private $logger;
    private $extractor;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->extractor = new ResourceExtractor($this->logger);
    }

    /**
     * 测试从文章数据中提取媒体资源
     */
    public function testExtractFromArticle()
    {
        $articleData = [
            'article_id' => 'test_123',
            'title' => '测试文章',
            'content' => '<p>文章内容</p><img src="https://mmbiz.qpic.cn/test1.jpg" alt="图片1">',
            'thumb_url' => 'https://wx.qlogo.cn/thumb.jpg'
        ];

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('info');

        // 执行提取
        $result = $this->extractor->extractFromArticle($articleData);

        // 验证结果结构
        $this->assertArrayHasKey('content_urls', $result);
        $this->assertArrayHasKey('thumb_url', $result);
        $this->assertArrayHasKey('total_count', $result);
        $this->assertArrayHasKey('errors', $result);

        // 验证提取结果
        $this->assertCount(1, $result['content_urls']);
        $this->assertEquals('https://mmbiz.qpic.cn/test1.jpg', $result['content_urls'][0]);
        $this->assertEquals('https://wx.qlogo.cn/thumb.jpg', $result['thumb_url']);
        $this->assertEquals(2, $result['total_count']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * 测试从内容中提取各种类型的媒体资源
     */
    public function testExtractFromContent()
    {
        $content = '
            <p>测试内容</p>
            <img src="https://mmbiz.qpic.cn/image1.jpg" alt="图片1">
            <img data-src="https://mmbiz.qpic.cn/image2.png" alt="图片2">
            <div style="background-image: url(https://res.wx.qq.com/bg.jpg)"></div>
            <video src="https://mmbiz.qpic.cn/video.mp4" poster="https://wx.qlogo.cn/poster.jpg"></video>
            <source src="https://mmfb.qpic.cn/source.webm" type="video/webm">
            <audio src="https://mmbiz.qpic.cn/audio.mp3"></audio>
            <iframe src="https://v.qq.com/video.html"></iframe>
        ';

        // 执行提取
        $urls = $this->extractor->extractFromContent($content);

        // 验证提取的URL
        $expectedUrls = [
            'https://mmbiz.qpic.cn/image1.jpg',
            'https://mmbiz.qpic.cn/image2.png',
            'https://res.wx.qq.com/bg.jpg',
            'https://mmbiz.qpic.cn/video.mp4',
            'https://wx.qlogo.cn/poster.jpg',
            'https://mmfb.qpic.cn/source.webm',
            'https://mmbiz.qpic.cn/audio.mp3',
            'https://v.qq.com/video.html'
        ];

        $this->assertCount(count($expectedUrls), $urls);

        foreach ($expectedUrls as $expectedUrl) {
            $this->assertContains($expectedUrl, $urls);
        }
    }

    /**
     * 测试只提取微信CDN资源
     */
    public function testExtractOnlyWechatResources()
    {
        $content = '
            <img src="https://mmbiz.qpic.cn/wechat.jpg" alt="微信图片">
            <img src="https://example.com/external.jpg" alt="外部图片">
            <img src="https://res.wx.qq.com/wechat2.png" alt="微信图片2">
            <video src="https://external.com/video.mp4"></video>
        ';

        $urls = $this->extractor->extractFromContent($content);

        // 应该只提取微信CDN的资源
        $this->assertCount(2, $urls);
        $this->assertContains('https://mmbiz.qpic.cn/wechat.jpg', $urls);
        $this->assertContains('https://res.wx.qq.com/wechat2.png', $urls);
        $this->assertNotContains('https://example.com/external.jpg', $urls);
        $this->assertNotContains('https://external.com/video.mp4', $urls);
    }

    /**
     * 测试图片标签提取
     */
    public function testExtractImageTags()
    {
        $content = '
            <img src="https://mmbiz.qpic.cn/image1.jpg" alt="图片1" class="img-class">
            <img data-src="https://res.wx.qq.com/image2.png" alt="图片2">
            <img src="https://example.com/external.jpg" alt="外部图片">
            <img src="invalid-url" alt="无效URL">
        ';

        $reflection = new \ReflectionClass($this->extractor);
        $method = $reflection->getMethod('extractImageTags');
        $method->setAccessible(true);

        $urls = $method->invoke($this->extractor, $content);

        // 应该提取有效的URL（包括data-src）
        $this->assertCount(3, $urls);
        $this->assertContains('https://mmbiz.qpic.cn/image1.jpg', $urls);
        $this->assertContains('https://res.wx.qq.com/image2.png', $urls);
        $this->assertContains('https://example.com/external.jpg', $urls);
        $this->assertNotContains('invalid-url', $urls);
    }

    /**
     * 测试背景图片提取
     */
    public function testExtractBackgroundImages()
    {
        $content = '
            <div style="background-image: url(https://mmbiz.qpic.cn/bg1.jpg)"></div>
            <div style="background: url(https://res.wx.qq.com/bg2.png) no-repeat center"></div>
            <div style="background-image: url(\'https://wx.qlogo.cn/bg3.gif\')"></div>
            <div style="background-image: url(https://example.com/external.jpg)"></div>
        ';

        $reflection = new \ReflectionClass($this->extractor);
        $method = $reflection->getMethod('extractBackgroundImages');
        $method->setAccessible(true);

        $urls = $method->invoke($this->extractor, $content);

        $this->assertCount(4, $urls);
        $this->assertContains('https://mmbiz.qpic.cn/bg1.jpg', $urls);
        $this->assertContains('https://res.wx.qq.com/bg2.png', $urls);
        $this->assertContains('https://wx.qlogo.cn/bg3.gif', $urls);
        $this->assertContains('https://example.com/external.jpg', $urls);
    }

    /**
     * 测试视频资源提取
     */
    public function testExtractVideoResources()
    {
        $content = '
            <video src="https://mmbiz.qpic.cn/video1.mp4" controls></video>
            <video poster="https://res.wx.qq.com/poster1.jpg">
                <source src="https://wx.qlogo.cn/source1.webm" type="video/webm">
                <source src="https://mmfb.qpic.cn/source2.mp4" type="video/mp4">
            </video>
            <video src="https://external.com/video.mp4"></video>
        ';

        $reflection = new \ReflectionClass($this->extractor);
        $method = $reflection->getMethod('extractVideoResources');
        $method->setAccessible(true);

        $urls = $method->invoke($this->extractor, $content);

        $this->assertCount(5, $urls);
        $this->assertContains('https://mmbiz.qpic.cn/video1.mp4', $urls);
        $this->assertContains('https://res.wx.qq.com/poster1.jpg', $urls);
        $this->assertContains('https://wx.qlogo.cn/source1.webm', $urls);
        $this->assertContains('https://mmfb.qpic.cn/source2.mp4', $urls);
        $this->assertContains('https://external.com/video.mp4', $urls);
    }

    /**
     * 测试其他媒体资源提取
     */
    public function testExtractOtherMedia()
    {
        $content = '
            <audio src="https://mmbiz.qpic.cn/audio1.mp3"></audio>
            <iframe src="https://v.qq.com/video.html"></iframe>
            <iframe src="https://youku.com/video.html"></iframe>
            <iframe src="https://example.com/external.html"></iframe>
            <audio src="https://external.com/audio.mp3"></audio>
        ';

        $reflection = new \ReflectionClass($this->extractor);
        $method = $reflection->getMethod('extractOtherMedia');
        $method->setAccessible(true);

        $urls = $method->invoke($this->extractor, $content);

        // 应该只提取音频和视频平台的iframe
        $this->assertCount(3, $urls);
        $this->assertContains('https://mmbiz.qpic.cn/audio1.mp3', $urls);
        $this->assertContains('https://v.qq.com/video.html', $urls);
        $this->assertContains('https://youku.com/video.html', $urls);
        $this->assertNotContains('https://example.com/external.html', $urls);
        $this->assertNotContains('https://external.com/audio.mp3', $urls);
    }

    /**
     * 测试微信资源识别
     */
    public function testIsWechatResource()
    {
        $testCases = [
            'https://mmbiz.qpic.cn/test.jpg' => true,
            'https://res.wx.qq.com/test.png' => true,
            'https://wx.qlogo.cn/test.jpg' => true,
            'https://mmfb.qpic.cn/test.gif' => true,
            'https://example.com/test.jpg' => false,
            'https://sub.mmbiz.qpic.cn/test.jpg' => true,
            'https://fake-mmbiz.qpic.cn/test.jpg' => false,
            'invalid-url' => false,
        ];

        foreach ($testCases as $url => $expected) {
            $result = $this->extractor->isWechatResource($url);
            $this->assertEquals($expected, $result, "Failed for URL: {$url}");
        }
    }

    /**
     * 测试URL验证
     */
    public function testIsValidMediaUrl()
    {
        $reflection = new \ReflectionClass($this->extractor);
        $method = $reflection->getMethod('isValidMediaUrl');
        $method->setAccessible(true);

        $testCases = [
            'https://mmbiz.qpic.cn/test.jpg' => true,
            'http://res.wx.qq.com/test.png' => true,
            'https://example.com/test.mp4' => true,
            'ftp://example.com/test.jpg' => false,
            'relative/path/test.jpg' => false,
            'invalid-url' => false,
            '' => false,
            'https://example.com/file' => true, // 没有扩展名但可能是微信资源
        ];

        foreach ($testCases as $url => $expected) {
            $result = $method->invoke($this->extractor, $url);
            $this->assertEquals($expected, $result, "Failed for URL: {$url}");
        }
    }

    /**
     * 测试视频平台URL识别
     */
    public function testIsVideoPlatformUrl()
    {
        $reflection = new \ReflectionClass($this->extractor);
        $method = $reflection->getMethod('isVideoPlatformUrl');
        $method->setAccessible(true);

        $testCases = [
            'https://v.qq.com/video.html' => true,
            'https://youku.com/video.html' => true,
            'https://bilibili.com/video.html' => true,
            'https://youtube.com/watch?v=test' => true,
            'https://youtu.be/test' => true,
            'https://example.com/video.html' => false,
            'https://v.example.com/video.html' => false,
        ];

        foreach ($testCases as $url => $expected) {
            $result = $method->invoke($this->extractor, $url);
            $this->assertEquals($expected, $result, "Failed for URL: {$url}");
        }
    }

    /**
     * 测试批量提取多篇文章
     */
    public function testBatchExtractFromArticles()
    {
        $articlesData = [
            [
                'article_id' => 'article_1',
                'content' => '<img src="https://mmbiz.qpic.cn/img1.jpg">',
                'thumb_url' => 'https://wx.qlogo.cn/thumb1.jpg'
            ],
            [
                'article_id' => 'article_2',
                'content' => '<img src="https://res.wx.qq.com/img2.png">',
                'thumb_url' => null
            ],
            [
                'article_id' => 'article_3',
                'content' => '<p>没有媒体资源</p>',
                'thumb_url' => null
            ]
        ];

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('info');

        $result = $this->extractor->batchExtractFromArticles($articlesData);

        // 验证结果结构
        $this->assertArrayHasKey('articles', $result);
        $this->assertArrayHasKey('summary', $result);

        // 验证文章提取结果
        $this->assertCount(3, $result['articles']);

        // 第一篇文章：1个内容图片 + 1个缩略图 = 2个资源
        $this->assertEquals(2, $result['articles'][0]['total_count']);
        $this->assertCount(1, $result['articles'][0]['content_urls']);
        $this->assertEquals('https://wx.qlogo.cn/thumb1.jpg', $result['articles'][0]['thumb_url']);

        // 第二篇文章：1个内容图片，无缩略图 = 1个资源
        $this->assertEquals(1, $result['articles'][1]['total_count']);
        $this->assertCount(1, $result['articles'][1]['content_urls']);
        $this->assertNull($result['articles'][1]['thumb_url']);

        // 第三篇文章：无媒体资源 = 0个资源
        $this->assertEquals(0, $result['articles'][2]['total_count']);
        $this->assertEmpty($result['articles'][2]['content_urls']);
        $this->assertNull($result['articles'][2]['thumb_url']);

        // 验证统计信息
        $summary = $result['summary'];
        $this->assertEquals(3, $summary['articles_count']);
        $this->assertEquals(3, $summary['total_resources']);
        $this->assertEquals(2, $summary['articles_with_resources']);
    }

    /**
     * 测试获取资源统计信息
     */
    public function testGetResourceStats()
    {
        $extractedData = [
            [
                'content_urls' => [
                    'https://mmbiz.qpic.cn/img1.jpg',
                    'https://res.wx.qq.com/img2.png'
                ],
                'thumb_url' => 'https://wx.qlogo.cn/thumb1.jpg'
            ],
            [
                'content_urls' => [
                    'https://mmbiz.qpic.cn/img3.gif'
                ],
                'thumb_url' => null
            ],
            [
                'content_urls' => [],
                'thumb_url' => null
            ]
        ];

        $stats = $this->extractor->getResourceStats($extractedData);

        // 验证统计信息
        $this->assertEquals(3, $stats['total_articles']);
        $this->assertEquals(2, $stats['articles_with_content_resources']);
        $this->assertEquals(1, $stats['articles_with_thumb_resources']);
        $this->assertEquals(3, $stats['total_content_resources']);
        $this->assertEquals(1, $stats['total_thumb_resources']);

        // 验证域名统计
        $expectedDomains = [
            'mmbiz.qpic.cn' => 2,
            'res.wx.qq.com' => 1,
            'wx.qlogo.cn' => 1
        ];

        foreach ($expectedDomains as $domain => $count) {
            $this->assertEquals($count, $stats['unique_domains'][$domain]);
        }
    }

    /**
     * 测试异常处理
     */
    public function testExceptionHandling()
    {
        $articleData = [
            'article_id' => 'test_123',
            'content' => '<img src="https://mmbiz.qpic.cn/test.jpg">',
            'thumb_url' => 'https://wx.qlogo.cn/thumb.jpg'
        ];

        // 模拟日志异常
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->willThrowException(new \Exception('日志异常'));

        // 执行提取，应该不会抛出异常
        $result = $this->extractor->extractFromArticle($articleData);

        // 验证结果仍然有效
        $this->assertArrayHasKey('content_urls', $result);
        $this->assertArrayHasKey('thumb_url', $result);
        $this->assertArrayHasKey('total_count', $result);
        $this->assertArrayHasKey('errors', $result);
    }

    /**
     * 测试空内容处理
     */
    public function testEmptyContent()
    {
        $articleData = [
            'article_id' => 'test_123',
            'content' => '',
            'thumb_url' => ''
        ];

        $result = $this->extractor->extractFromArticle($articleData);

        $this->assertEmpty($result['content_urls']);
        $this->assertNull($result['thumb_url']);
        $this->assertEquals(0, $result['total_count']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * 测试重复URL去重
     */
    public function testUrlDeduplication()
    {
        $content = '
            <img src="https://mmbiz.qpic.cn/duplicate.jpg" alt="图片1">
            <img src="https://mmbiz.qpic.cn/duplicate.jpg" alt="图片2">
            <img data-src="https://mmbiz.qpic.cn/duplicate.jpg" alt="图片3">
            <div style="background-image: url(https://mmbiz.qpic.cn/duplicate.jpg)"></div>
        ';

        $urls = $this->extractor->extractFromContent($content);

        // 应该去重，只保留一个URL
        $this->assertCount(1, $urls);
        $this->assertEquals('https://mmbiz.qpic.cn/duplicate.jpg', $urls[0]);
    }
}
