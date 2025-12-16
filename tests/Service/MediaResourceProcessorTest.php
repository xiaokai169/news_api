<?php

namespace App\Tests\Service;

use App\Service\MediaResourceProcessor;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Siganushka\MediaBundle\MediaManagerInterface;
use Siganushka\MediaBundle\File\Ufile;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class MediaResourceProcessorTest extends TestCase
{
    private $mediaManager;
    private $logger;
    private $processor;

    protected function setUp(): void
    {
        $this->mediaManager = $this->createMock(MediaManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->processor = new MediaResourceProcessor($this->mediaManager, $this->logger);
    }

    /**
     * 测试处理包含微信媒体资源的文章
     */
    public function testProcessArticleMediaWithWechatResources()
    {
        // 模拟文章内容，包含微信图片
        $content = '<p>测试文章</p><img src="https://mmbiz.qpic.cn/test1.jpg" alt="测试图片1"><img src="https://res.wx.qq.com/test2.png" alt="测试图片2">';
        $thumbUrl = 'https://wx.qlogo.cn/thumb.jpg';

        // 模拟MediaManagerInterface的返回值
        $ufile1 = $this->createMock(Ufile::class);
        $ufile1->method('getUrl')->willReturn('https://example.com/new1.jpg');

        $ufile2 = $this->createMock(Ufile::class);
        $ufile2->method('getUrl')->willReturn('https://example.com/new2.png');

        $ufileThumb = $this->createMock(Ufile::class);
        $ufileThumb->method('getUrl')->willReturn('https://example.com/new_thumb.jpg');

        // 设置MediaManagerInterface的期望
        $this->mediaManager
            ->expects($this->exactly(3))
            ->method('save')
            ->withConsecutive(
                [$this->stringContains('thumb_test'), $this->anything()],
                [$this->stringContains('content_test1'), $this->anything()],
                [$this->stringContains('content_test2'), $this->anything()]
            )
            ->willReturnOnConsecutiveCalls($ufileThumb, $ufile1, $ufile2);

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('info');

        // 执行处理
        $result = $this->processor->processArticleMedia($content, $thumbUrl);

        // 验证结果
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('thumb_url', $result);
        $this->assertArrayHasKey('processed_resources', $result);
        $this->assertArrayHasKey('errors', $result);

        // 验证URL被正确替换
        $this->assertStringContainsString('https://example.com/new1.jpg', $result['content']);
        $this->assertStringContainsString('https://example.com/new2.png', $result['content']);
        $this->assertEquals('https://example.com/new_thumb.jpg', $result['thumb_url']);

        // 验证处理资源记录
        $this->assertCount(3, $result['processed_resources']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * 测试处理不包含微信资源的文章
     */
    public function testProcessArticleMediaWithoutWechatResources()
    {
        $content = '<p>测试文章</p><img src="https://example.com/external.jpg" alt="外部图片">';
        $thumbUrl = 'https://example.com/external_thumb.jpg';

        // MediaManagerInterface不应该被调用
        $this->mediaManager->expects($this->never())->method('save');

        // 执行处理
        $result = $this->processor->processArticleMedia($content, $thumbUrl);

        // 验证结果
        $this->assertEquals($content, $result['content']);
        $this->assertEquals($thumbUrl, $result['thumb_url']);
        $this->assertEmpty($result['processed_resources']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * 测试处理空内容
     */
    public function testProcessArticleMediaWithEmptyContent()
    {
        $content = '';
        $thumbUrl = null;

        // 执行处理
        $result = $this->processor->processArticleMedia($content, $thumbUrl);

        // 验证结果
        $this->assertEquals('', $result['content']);
        $this->assertNull($result['thumb_url']);
        $this->assertEmpty($result['processed_resources']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * 测试MediaManagerInterface保存失败的情况
     */
    public function testProcessArticleMediaWithSaveFailure()
    {
        $content = '<img src="https://mmbiz.qpic.cn/test.jpg">';
        $thumbUrl = null;

        // 模拟保存失败
        $this->mediaManager
            ->expects($this->once())
            ->method('save')
            ->willReturn(null);

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('error');

        // 执行处理
        $result = $this->processor->processArticleMedia($content, $thumbUrl);

        // 验证错误处理
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('缩略图处理失败', $result['errors'][0]);
    }

    /**
     * 测试批量处理媒体资源
     */
    public function testBatchProcessResources()
    {
        $resources = [
            ['url' => 'https://mmbiz.qpic.cn/test1.jpg', 'type' => 'content'],
            ['url' => 'https://res.wx.qq.com/test2.png', 'type' => 'thumb'],
            ['url' => 'https://invalid-url.com/test.jpg', 'type' => 'content'] // 非微信URL
        ];

        $ufile1 = $this->createMock(Ufile::class);
        $ufile1->method('getUrl')->willReturn('https://example.com/new1.jpg');

        $ufile2 = $this->createMock(Ufile::class);
        $ufile2->method('getUrl')->willReturn('https://example.com/new2.png');

        // 只期望两次save调用（只有微信资源会被处理）
        $this->mediaManager
            ->expects($this->exactly(2))
            ->method('save')
            ->willReturnOnConsecutiveCalls($ufile1, $ufile2);

        // 执行批量处理
        $result = $this->processor->batchProcessResources($resources);

        // 验证结果
        $this->assertCount(3, $result);

        // 验证微信资源被处理
        $this->assertEquals('https://example.com/new1.jpg', $result[0]['result']['new_url']);
        $this->assertTrue($result[0]['result']['success']);

        $this->assertEquals('https://example.com/new2.png', $result[1]['result']['new_url']);
        $this->assertTrue($result[1]['result']['success']);

        // 验证非微信资源被跳过
        $this->assertFalse($result[2]['result']['success']);
    }

    /**
     * 测试微信资源识别
     */
    public function testWechatResourceIdentification()
    {
        $testCases = [
            'https://mmbiz.qpic.cn/test.jpg' => true,
            'https://res.wx.qq.com/test.png' => true,
            'https://wx.qlogo.cn/avatar.jpg' => true,
            'https://mmfb.qpic.cn/test.gif' => true,
            'https://example.com/test.jpg' => false,
            'http://mmbiz.qpic.cn/test.jpg' => true,
            'https://sub.mmbiz.qpic.cn/test.jpg' => true,
        ];

        foreach ($testCases as $url => $expected) {
            // 使用反射来测试私有方法
            $reflection = new \ReflectionClass($this->processor);
            $method = $reflection->getMethod('isWechatResource');
            $method->setAccessible(true);

            $result = $method->invoke($this->processor, $url);
            $this->assertEquals($expected, $result, "Failed for URL: {$url}");
        }
    }

    /**
     * 测试文件名生成
     */
    public function testFilenameGeneration()
    {
        $testCases = [
            ['url' => 'https://mmbiz.qpic.cn/test.jpg', 'type' => 'thumb', 'expected_pattern' => '/^thumb_test_\d+$/'],
            ['url' => 'https://res.wx.qq.com/image.png', 'type' => 'content', 'expected_pattern' => '/^content_image_\d+$/'],
            ['url' => 'https://wx.qlogo.cn/', 'type' => 'thumb', 'expected_pattern' => '/^thumb_wechat_media_\d+$/'],
        ];

        $reflection = new \ReflectionClass($this->processor);
        $method = $reflection->getMethod('generateFilename');
        $method->setAccessible(true);

        foreach ($testCases as $testCase) {
            $result = $method->invoke($this->processor, $testCase['url'], $testCase['type']);
            $this->assertMatchesRegularExpression($testCase['expected_pattern'], $result);
        }
    }

    /**
     * 测试URL替换功能
     */
    public function testUrlReplacement()
    {
        $originalContent = '<img src="https://mmbiz.qpic.cn/original.jpg" alt="测试">
                           <div style="background-image: url(\'https://mmbiz.qpic.cn/bg.jpg\')"></div>';

        $urlMapping = [
            'https://mmbiz.qpic.cn/original.jpg' => 'https://example.com/new_original.jpg',
            'https://mmbiz.qpic.cn/bg.jpg' => 'https://example.com/new_bg.jpg'
        ];

        $reflection = new \ReflectionClass($this->processor);
        $method = $reflection->getMethod('replaceUrlsInContent');
        $method->setAccessible(true);

        $result = $method->invoke($this->processor, $originalContent, $urlMapping);

        $this->assertStringContainsString('https://example.com/new_original.jpg', $result);
        $this->assertStringContainsString('https://example.com/new_bg.jpg', $result);
        $this->assertStringNotContainsString('https://mmbiz.qpic.cn/original.jpg', $result);
        $this->assertStringNotContainsString('https://mmbiz.qpic.cn/bg.jpg', $result);
    }

    /**
     * 测试MIME类型检查
     */
    public function testMimeTypeChecking()
    {
        $testCases = [
            'image/jpeg' => true,
            'image/png' => true,
            'image/gif' => true,
            'image/webp' => true,
            'video/mp4' => true,
            'video/avi' => true,
            'application/pdf' => false,
            'text/html' => false,
        ];

        $reflection = new \ReflectionClass($this->processor);
        $method = $reflection->getMethod('isSupportedMimeType');
        $method->setAccessible(true);

        foreach ($testCases as $mimeType => $expected) {
            $result = $method->invoke($this->processor, $mimeType);
            $this->assertEquals($expected, $result, "MIME type: {$mimeType}");
        }
    }

    /**
     * 测试异常处理
     */
    public function testExceptionHandling()
    {
        $content = '<img src="https://mmbiz.qpic.cn/test.jpg">';
        $thumbUrl = 'https://mmbiz.qpic.cn/thumb.jpg';

        // 模拟异常
        $this->mediaManager
            ->expects($this->once())
            ->method('save')
            ->willThrowException(new \Exception('保存失败'));

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('error');

        // 执行处理
        $result = $this->processor->processArticleMedia($content, $thumbUrl);

        // 验证异常被正确处理
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('媒体资源处理异常', $result['errors'][0]);
    }

    /**
     * 测试视频资源处理
     */
    public function testVideoResourceProcessing()
    {
        $content = '<video src="https://mmbiz.qpic.cn/video.mp4" poster="https://mmbiz.qpic.cn/poster.jpg"></video>';
        $thumbUrl = null;

        $ufileVideo = $this->createMock(Ufile::class);
        $ufileVideo->method('getUrl')->willReturn('https://example.com/new_video.mp4');

        $ufilePoster = $this->createMock(Ufile::class);
        $ufilePoster->method('getUrl')->willReturn('https://example.com/new_poster.jpg');

        $this->mediaManager
            ->expects($this->exactly(2))
            ->method('save')
            ->willReturnOnConsecutiveCalls($ufileVideo, $ufilePoster);

        // 执行处理
        $result = $this->processor->processArticleMedia($content, $thumbUrl);

        // 验证结果
        $this->assertStringContainsString('https://example.com/new_video.mp4', $result['content']);
        $this->assertStringContainsString('https://example.com/new_poster.jpg', $result['content']);
        $this->assertCount(2, $result['processed_resources']);
    }
}
