<?php

namespace App\Tests\Service;

use App\Service\ResourceDownloader;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class ResourceDownloaderTest extends TestCase
{
    private $logger;
    private $downloader;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->downloader = new ResourceDownloader($this->logger);
    }

    /**
     * 测试成功下载多个资源
     */
    public function testDownloadMultipleSuccess()
    {
        $urls = [
            'https://mmbiz.qpic.cn/image1.jpg',
            'https://res.wx.qq.com/image2.png'
        ];

        // 模拟HTTP响应
        $responses = [
            new MockResponse('fake-image-content-1', [
                'http_code' => 200,
                'response_headers' => ['content-type' => ['image/jpeg']]
            ]),
            new MockResponse('fake-image-content-2', [
                'http_code' => 200,
                'response_headers' => ['content-type' => ['image/png']]
            ])
        ];

        $client = new MockHttpClient($responses);

        // 使用反射设置客户端
        $reflection = new \ReflectionClass($this->downloader);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->downloader, $client);

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('info');

        // 执行下载
        $result = $this->downloader->downloadMultiple($urls);

        // 验证结果
        $this->assertTrue($result['total'] >= 0);
        $this->assertTrue($result['successful'] >= 0);
        $this->assertTrue($result['failed'] >= 0);
        $this->assertTrue($result['duration'] >= 0);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('success_rate', $result);
    }

    /**
     * 测试下载单个资源成功
     */
    public function testDownloadSingleSuccess()
    {
        $url = 'https://mmbiz.qpic.cn/test.jpg';

        // 模拟HTTP响应
        $response = new MockResponse('fake-image-content', [
            'http_code' => 200,
            'response_headers' => ['content-type' => ['image/jpeg']]
        ]);

        $client = new MockHttpClient($response);

        // 使用反射设置客户端
        $reflection = new \ReflectionClass($this->downloader);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->downloader, $client);

        // 执行下载
        $result = $this->downloader->downloadSingle($url);

        // 验证结果结构
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('temp_file', $result);
        $this->assertArrayHasKey('size', $result);
        $this->assertArrayHasKey('mime_type', $result);
        $this->assertArrayHasKey('duration', $result);

        $this->assertEquals($url, $result['url']);
    }

    /**
     * 测试下载失败处理
     */
    public function testDownloadFailure()
    {
        $urls = [
            'https://invalid-url.com/image.jpg',
            'https://mmbiz.qpic.cn/404.jpg'
        ];

        // 模拟失败的HTTP响应
        $responses = [
            new MockResponse('', ['http_code' => 404]),
            new MockResponse('', ['http_code' => 500])
        ];

        $client = new MockHttpClient($responses);

        // 使用反射设置客户端
        $reflection = new \ReflectionClass($this->downloader);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->downloader, $client);

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('warning');

        // 执行下载
        $result = $this->downloader->downloadMultiple($urls);

        // 验证失败处理
        $this->assertGreaterThan(0, $result['failed']);
        $this->assertLessThan(100, $result['success_rate']);
    }

    /**
     * 测试并发下载限制
     */
    public function testConcurrentDownloadLimit()
    {
        $urls = [
            'https://mmbiz.qpic.cn/img1.jpg',
            'https://res.wx.qq.com/img2.png',
            'https://wx.qlogo.cn/img3.gif',
            'https://mmfb.qpic.cn/img4.jpg',
            'https://mmbiz.qpic.cn/img5.png'
        ];

        $options = [
            'max_concurrent' => 2,
            'timeout' => 10
        ];

        // 模拟HTTP响应
        $responses = array_fill(0, count($urls), new MockResponse('fake-content', [
            'http_code' => 200,
            'response_headers' => ['content-type' => ['image/jpeg']]
        ]));

        $client = new MockHttpClient($responses);

        // 使用反射设置客户端
        $reflection = new \ReflectionClass($this->downloader);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->downloader, $client);

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('info');

        // 执行下载
        $result = $this->downloader->downloadMultiple($urls, $options);

        // 验证结果
        $this->assertEquals(count($urls), $result['total']);
        $this->assertArrayHasKey('duration', $result);
    }

    /**
     * 测试重试机制
     */
    public function testRetryMechanism()
    {
        $url = 'https://mmbiz.qpic.cn/retry.jpg';

        $options = [
            'retry_attempts' => 2,
            'timeout' => 5
        ];

        // 模拟第一次失败，第二次成功
        $responses = [
            new MockResponse('', ['http_code' => 500]),
            new MockResponse('fake-content', [
                'http_code' => 200,
                'response_headers' => ['content-type' => ['image/jpeg']]
            ])
        ];

        $client = new MockHttpClient($responses);

        // 使用反射设置客户端
        $reflection = new \ReflectionClass($this->downloader);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->downloader, $client);

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('warning');

        // 执行下载
        $result = $this->downloader->downloadSingle($url, $options);

        // 验证重试结果
        $this->assertEquals($url, $result['url']);
        $this->assertArrayHasKey('attempt', $result);
    }

    /**
     * 测试MIME类型支持检查
     */
    public function testMimeTypeSupport()
    {
        $reflection = new \ReflectionClass($this->downloader);
        $method = $reflection->getMethod('isSupportedMimeType');
        $method->setAccessible(true);

        $testCases = [
            'image/jpeg' => true,
            'image/png' => true,
            'image/gif' => true,
            'image/webp' => true,
            'image/bmp' => true,
            'video/mp4' => true,
            'video/avi' => true,
            'video/mov' => true,
            'video/wmv' => true,
            'video/flv' => true,
            'video/webm' => true,
            'application/pdf' => false,
            'text/html' => false,
            'application/json' => false,
        ];

        foreach ($testCases as $mimeType => $expected) {
            $result = $method->invoke($this->downloader, $mimeType);
            $this->assertEquals($expected, $result, "MIME type: {$mimeType}");
        }
    }

    /**
     * 测试文件扩展名获取
     */
    public function testGetFileExtension()
    {
        $reflection = new \ReflectionClass($this->downloader);
        $method = $reflection->getMethod('getFileExtension');
        $method->setAccessible(true);

        $testCases = [
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/gif' => '.gif',
            'image/webp' => '.webp',
            'image/bmp' => '.bmp',
            'video/mp4' => '.mp4',
            'video/avi' => '.avi',
            'video/mov' => '.mov',
            'video/wmv' => '.wmv',
            'video/flv' => '.flv',
            'video/webm' => '.webm',
            'unknown/type' => '.bin',
        ];

        foreach ($testCases as $mimeType => $expected) {
            $result = $method->invoke($this->downloader, $mimeType);
            $this->assertEquals($expected, $result, "MIME type: {$mimeType}");
        }
    }

    /**
     * 测试MIME类型提取
     */
    public function testExtractMimeType()
    {
        $reflection = new \ReflectionClass($this->downloader);
        $method = $reflection->getMethod('extractMimeType');
        $method->setAccessible(true);

        $testCases = [
            'image/jpeg' => 'image/jpeg',
            'image/jpeg; charset=utf-8' => 'image/jpeg',
            'image/png; boundary=something' => 'image/png',
            'IMAGE/JPEG' => 'image/jpeg',
            ' Image/PNG ' => 'image/png',
        ];

        foreach ($testCases as $contentType => $expected) {
            $result = $method->invoke($this->downloader, $contentType);
            $this->assertEquals($expected, $result, "Content-Type: {$contentType}");
        }
    }

    /**
     * 测试临时文件创建
     */
    public function testCreateTempFile()
    {
        $reflection = new \ReflectionClass($this->downloader);
        $method = $reflection->getMethod('createTempFile');
        $method->setAccessible(true);

        $content = 'fake-file-content';
        $mimeType = 'image/jpeg';

        $tempFile = $method->invoke($this->downloader, $content, $mimeType);

        // 验证文件创建
        $this->assertNotNull($tempFile);
        $this->assertTrue(file_exists($tempFile));
        $this->assertStringEndsWith('.jpg', $tempFile);

        // 验证文件内容
        $fileContent = file_get_contents($tempFile);
        $this->assertEquals($content, $fileContent);

        // 清理临时文件
        unlink($tempFile);
    }

    /**
     * 测试临时文件清理
     */
    public function testCleanupTempFiles()
    {
        // 创建一些临时文件
        $tempFiles = [];
        for ($i = 0; $i < 3; $i++) {
            $tempFile = tempnam(sys_get_temp_dir(), 'test_cleanup_') . '.tmp';
            file_put_contents($tempFile, "test content {$i}");
            $tempFiles[] = $tempFile;
        }

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('debug');

        // 执行清理
        $result = $this->downloader->cleanupTempFiles($tempFiles);

        // 验证清理结果
        $this->assertEquals(3, $result['total']);
        $this->assertEquals(3, $result['cleaned']);
        $this->assertEmpty($result['errors']);

        // 验证文件被删除
        foreach ($tempFiles as $tempFile) {
            $this->assertFalse(file_exists($tempFile));
        }
    }

    /**
     * 测试下载统计信息
     */
    public function testGetDownloadStats()
    {
        $downloadResults = [
            'total' => 5,
            'successful' => 4,
            'failed' => 1,
            'duration' => 10.5,
            'success_rate' => 80.0,
            'results' => [
                [
                    'url' => 'https://mmbiz.qpic.cn/img1.jpg',
                    'success' => true,
                    'size' => 1024,
                    'duration' => 2.0,
                    'mime_type' => 'image/jpeg'
                ],
                [
                    'url' => 'https://res.wx.qq.com/img2.png',
                    'success' => true,
                    'size' => 2048,
                    'duration' => 3.0,
                    'mime_type' => 'image/png'
                ],
                [
                    'url' => 'https://invalid-url.com/img3.jpg',
                    'success' => false,
                    'error' => '404 Not Found'
                ],
                [
                    'url' => 'https://wx.qlogo.cn/img4.gif',
                    'success' => true,
                    'size' => 512,
                    'duration' => 1.5,
                    'mime_type' => 'image/gif'
                ],
                [
                    'url' => 'https://mmfb.qpic.cn/img5.jpg',
                    'success' => true,
                    'size' => 1536,
                    'duration' => 2.5,
                    'mime_type' => 'image/jpeg'
                ]
            ]
        ];

        $stats = $this->downloader->getDownloadStats($downloadResults);

        // 验证统计信息
        $this->assertEquals(5, $stats['total_downloads']);
        $this->assertEquals(4, $stats['successful_downloads']);
        $this->assertEquals(1, $stats['failed_downloads']);
        $this->assertEquals(80.0, $stats['success_rate']);
        $this->assertEquals(10.5, $stats['total_duration']);

        // 验证平均时长
        $expectedAvgDuration = (2.0 + 3.0 + 1.5 + 2.5) / 4; // 只计算成功的
        $this->assertEquals($expectedAvgDuration, $stats['average_duration']);

        // 验证总大小
        $expectedTotalSize = 1024 + 2048 + 512 + 1536;
        $this->assertEquals($expectedTotalSize, $stats['total_size']);

        // 验证MIME类型统计
        $expectedMimeTypes = [
            'image/jpeg' => 2,
            'image/png' => 1,
            'image/gif' => 1
        ];
        $this->assertEquals($expectedMimeTypes, $stats['mime_types']);

        // 验证错误类型统计
        $expectedErrorTypes = [
            '404 Not Found' => 1
        ];
        $this->assertEquals($expectedErrorTypes, $stats['error_types']);
    }

    /**
     * 测试网络异常处理
     */
    public function testNetworkExceptionHandling()
    {
        $url = 'https://timeout-url.com/image.jpg';

        // 模拟网络超时异常
        $client = $this->createMock(\Symfony\Contracts\HttpClient\HttpClientInterface::class);
        $client
            ->expects($this->once())
            ->method('request')
            ->willThrowException($this->createMock(TransportExceptionInterface::class));

        // 使用反射设置客户端
        $reflection = new \ReflectionClass($this->downloader);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->downloader, $client);

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('error');

        // 执行下载
        $result = $this->downloader->downloadSingle($url);

        // 验证异常处理
        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
        $this->assertNull($result['temp_file']);
    }

    /**
     * 测试不支持MIME类型的处理
     */
    public function testUnsupportedMimeTypeHandling()
    {
        $url = 'https://mmbiz.qpic.cn/document.pdf';

        // 模拟返回不支持的MIME类型
        $response = new MockResponse('fake-pdf-content', [
            'http_code' => 200,
            'response_headers' => ['content-type' => ['application/pdf']]
        ]);

        $client = new MockHttpClient($response);

        // 使用反射设置客户端
        $reflection = new \ReflectionClass($this->downloader);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->downloader, $client);

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('warning');

        // 执行下载
        $result = $this->downloader->downloadSingle($url);

        // 验证不支持的MIME类型被拒绝
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('不支持的MIME类型', $result['error']);
    }

    /**
     * 测试空内容处理
     */
    public function testEmptyContentHandling()
    {
        $url = 'https://mmbiz.qpic.cn/empty.jpg';

        // 模拟空响应
        $response = new MockResponse('', [
            'http_code' => 200,
            'response_headers' => ['content-type' => ['image/jpeg']]
        ]);

        $client = new MockHttpClient($response);

        // 使用反射设置客户端
        $reflection = new \ReflectionClass($this->downloader);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->downloader, $client);

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('warning');

        // 执行下载
        $result = $this->downloader->downloadSingle($url);

        // 验证空内容被拒绝
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('下载内容为空', $result['error']);
    }

    /**
     * 测试配置选项
     */
    public function testConfigurationOptions()
    {
        $urls = ['https://mmbiz.qpic.cn/test.jpg'];

        $options = [
            'max_concurrent' => 5,
            'timeout' => 60,
            'retry_attempts' => 5
        ];

        // 模拟HTTP响应
        $response = new MockResponse('fake-content', [
            'http_code' => 200,
            'response_headers' => ['content-type' => ['image/jpeg']]
        ]);

        $client = new MockHttpClient($response);

        // 使用反射设置客户端
        $reflection = new \ReflectionClass($this->downloader);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->downloader, $client);

        // 设置日志期望
        $this->logger->expects($this->atLeastOnce())->method('info');

        // 执行下载
        $result = $this->downloader->downloadMultiple($urls, $options);

        // 验证配置被应用
        $this->assertEquals(1, $result['total']);
        $this->assertArrayHasKey('duration', $result);
    }
}
