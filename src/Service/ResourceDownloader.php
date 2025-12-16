<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\ChunkInterface;
use Symfony\Component\HttpClient\Response\AsyncResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class ResourceDownloader
{
    private LoggerInterface $logger;

    // 下载配置
    private const DEFAULT_TIMEOUT = 30;
    private const MAX_REDIRECTS = 5;
    private const MAX_CONCURRENT_DOWNLOADS = 10;
    private const RETRY_ATTEMPTS = 3;
    private const RETRY_DELAY = 1000; // 毫秒

    // 支持的MIME类型
    private const SUPPORTED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
        'video/mp4',
        'video/avi',
        'video/mov',
        'video/wmv',
        'video/flv',
        'video/webm'
    ];

    public function __construct(LoggerInterface $logger)
    {
        // 使用专用的微信日志通道
        if ($logger instanceof Logger) {
            $this->logger = $logger->withName('wechat_downloader');
        } else {
            $this->logger = $logger;
        }
    }

    /**
     * 并发下载多个资源
     *
     * @param array $urls URL列表
     * @param array $options 下载选项
     * @return array 下载结果
     */
    public function downloadMultiple(array $urls, array $options = []): array
    {
        $results = [];
        $startTime = microtime(true);

        try {
            $maxConcurrent = $options['max_concurrent'] ?? self::MAX_CONCURRENT_DOWNLOADS;
            $timeout = $options['timeout'] ?? self::DEFAULT_TIMEOUT;
            $retryAttempts = $options['retry_attempts'] ?? self::RETRY_ATTEMPTS;

            $this->logger->info('开始并发下载资源', [
                'total_urls' => count($urls),
                'max_concurrent' => $maxConcurrent,
                'timeout' => $timeout,
                'retry_attempts' => $retryAttempts
            ]);

            // 分批处理URL
            $batches = array_chunk($urls, $maxConcurrent);
            $allResults = [];

            foreach ($batches as $batchIndex => $batch) {
                $this->logger->debug('处理下载批次', [
                    'batch_index' => $batchIndex + 1,
                    'batch_size' => count($batch)
                ]);

                $batchResults = $this->downloadBatch($batch, $options);
                $allResults = array_merge($allResults, $batchResults);
            }

            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            $successfulDownloads = array_filter($allResults, function($result) {
                return $result['success'] === true;
            });

            $results = [
                'total' => count($urls),
                'successful' => count($successfulDownloads),
                'failed' => count($allResults) - count($successfulDownloads),
                'duration' => $duration,
                'results' => $allResults,
                'success_rate' => round((count($successfulDownloads) / count($urls)) * 100, 2)
            ];

            $this->logger->info('并发下载完成', [
                'total' => $results['total'],
                'successful' => $results['successful'],
                'failed' => $results['failed'],
                'duration' => $duration,
                'success_rate' => $results['success_rate'] . '%'
            ]);

        } catch (\Exception $e) {
            $errorMsg = '并发下载异常: ' . $e->getMessage();
            $this->logger->error($errorMsg, ['exception' => $e]);

            $results = [
                'total' => count($urls),
                'successful' => 0,
                'failed' => count($urls),
                'duration' => 0,
                'results' => [],
                'success_rate' => 0,
                'error' => $errorMsg
            ];
        }

        return $results;
    }

    /**
     * 下载单个批次
     *
     * @param array $urls URL批次
     * @param array $options 下载选项
     * @return array 批次下载结果
     */
    private function downloadBatch(array $urls, array $options): array
    {
        $client = HttpClient::create();
        $timeout = $options['timeout'] ?? self::DEFAULT_TIMEOUT;
        $retryAttempts = $options['retry_attempts'] ?? self::RETRY_ATTEMPTS;

        $responses = [];
        $results = [];

        // 创建异步请求
        foreach ($urls as $url) {
            try {
                $response = $client->request('GET', $url, [
                    'timeout' => $timeout,
                    'max_redirects' => self::MAX_REDIRECTS,
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                        'Accept' => 'image/*,video/*,*/*;q=0.8',
                        'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
                        'Accept-Encoding' => 'gzip, deflate, br',
                        'Connection' => 'keep-alive',
                        'Upgrade-Insecure-Requests' => '1'
                    ]
                ]);
                $responses[$url] = $response;
            } catch (\Exception $e) {
                $results[$url] = [
                    'url' => $url,
                    'success' => false,
                    'error' => '创建请求失败: ' . $e->getMessage(),
                    'temp_file' => null,
                    'size' => 0,
                    'mime_type' => null,
                    'duration' => 0
                ];
                $this->logger->error('创建下载请求失败', ['url' => $url, 'error' => $e->getMessage()]);
            }
        }

        // 等待所有请求完成
        foreach ($responses as $url => $response) {
            $result = $this->processDownloadResponse($url, $response, $retryAttempts);
            $results[$url] = $result;
        }

        return array_values($results);
    }

    /**
     * 处理下载响应
     *
     * @param string $url URL
     * @param AsyncResponse $response HTTP响应
     * @param int $retryAttempts 重试次数
     * @return array 处理结果
     */
    private function processDownloadResponse(string $url, AsyncResponse $response, int $retryAttempts): array
    {
        $startTime = microtime(true);
        $attempt = 0;

        while ($attempt <= $retryAttempts) {
            try {
                $this->logger->debug('处理下载响应', [
                    'url' => $url,
                    'attempt' => $attempt + 1,
                    'max_attempts' => $retryAttempts + 1
                ]);

                // 检查HTTP状态码
                $statusCode = $response->getStatusCode();
                if ($statusCode !== 200) {
                    throw new \Exception("HTTP状态码错误: {$statusCode}");
                }

                // 获取内容类型
                $contentType = $response->getHeaders()['content-type'][0] ?? '';
                $mimeType = $this->extractMimeType($contentType);

                // 检查MIME类型
                if (!$this->isSupportedMimeType($mimeType)) {
                    throw new \Exception("不支持的MIME类型: {$mimeType}");
                }

                // 获取内容
                $content = $response->getContent();
                if (empty($content)) {
                    throw new \Exception('下载内容为空');
                }

                // 创建临时文件
                $tempFile = $this->createTempFile($content, $mimeType);
                if (!$tempFile) {
                    throw new \Exception('创建临时文件失败');
                }

                $duration = round(microtime(true) - $startTime, 2);

                $this->logger->info('资源下载成功', [
                    'url' => $url,
                    'temp_file' => $tempFile,
                    'size' => strlen($content),
                    'mime_type' => $mimeType,
                    'duration' => $duration,
                    'attempt' => $attempt + 1
                ]);

                return [
                    'url' => $url,
                    'success' => true,
                    'error' => null,
                    'temp_file' => $tempFile,
                    'size' => strlen($content),
                    'mime_type' => $mimeType,
                    'duration' => $duration,
                    'attempt' => $attempt + 1
                ];

            } catch (TransportExceptionInterface | ClientExceptionInterface | ServerExceptionInterface $e) {
                $error = '网络错误: ' . $e->getMessage();
                $this->logger->warning('下载网络错误', [
                    'url' => $url,
                    'attempt' => $attempt + 1,
                    'error' => $error
                ]);
            } catch (\Exception $e) {
                $error = $e->getMessage();
                $this->logger->warning('下载处理错误', [
                    'url' => $url,
                    'attempt' => $attempt + 1,
                    'error' => $error
                ]);
            }

            $attempt++;

            // 如果还有重试机会，等待一段时间
            if ($attempt <= $retryAttempts) {
                usleep(self::RETRY_DELAY * 1000); // 转换为微秒
            }
        }

        $duration = round(microtime(true) - $startTime, 2);

        $this->logger->error('资源下载失败（重试次数用尽）', [
            'url' => $url,
            'total_attempts' => $retryAttempts + 1,
            'duration' => $duration
        ]);

        return [
            'url' => $url,
            'success' => false,
            'error' => $error ?? '下载失败',
            'temp_file' => null,
            'size' => 0,
            'mime_type' => null,
            'duration' => $duration,
            'attempt' => $attempt
        ];
    }

    /**
     * 创建临时文件
     *
     * @param string $content 文件内容
     * @param string $mimeType MIME类型
     * @return string|null 临时文件路径
     */
    private function createTempFile(string $content, string $mimeType): ?string
    {
        try {
            $extension = $this->getFileExtension($mimeType);
            $tempFile = tempnam(sys_get_temp_dir(), 'wechat_download_') . $extension;

            if (file_put_contents($tempFile, $content) === false) {
                throw new \Exception('无法写入临时文件');
            }

            // 验证文件是否创建成功
            if (!file_exists($tempFile) || filesize($tempFile) === 0) {
                throw new \Exception('临时文件创建失败或为空');
            }

            return $tempFile;

        } catch (\Exception $e) {
            $this->logger->error('创建临时文件失败', [
                'error' => $e->getMessage(),
                'mime_type' => $mimeType,
                'content_size' => strlen($content)
            ]);
            return null;
        }
    }

    /**
     * 从Content-Type头中提取MIME类型
     *
     * @param string $contentType Content-Type头
     * @return string MIME类型
     */
    private function extractMimeType(string $contentType): string
    {
        // 移除字符集信息
        if (strpos($contentType, ';') !== false) {
            $contentType = explode(';', $contentType)[0];
        }

        return strtolower(trim($contentType));
    }

    /**
     * 检查是否为支持的MIME类型
     *
     * @param string $mimeType MIME类型
     * @return bool
     */
    private function isSupportedMimeType(string $mimeType): bool
    {
        return in_array($mimeType, self::SUPPORTED_MIME_TYPES);
    }

    /**
     * 根据MIME类型获取文件扩展名
     *
     * @param string $mimeType MIME类型
     * @return string 文件扩展名
     */
    private function getFileExtension(string $mimeType): string
    {
        $extensions = [
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
            'video/webm' => '.webm'
        ];

        return $extensions[$mimeType] ?? '.bin';
    }

    /**
     * 下载单个资源
     *
     * @param string $url 资源URL
     * @param array $options 下载选项
     * @return array 下载结果
     */
    public function downloadSingle(string $url, array $options = []): array
    {
        $results = $this->downloadMultiple([$url], $options);
        return $results['results'][0] ?? [
            'url' => $url,
            'success' => false,
            'error' => '下载结果为空',
            'temp_file' => null,
            'size' => 0,
            'mime_type' => null,
            'duration' => 0
        ];
    }

    /**
     * 清理临时文件
     *
     * @param array $tempFiles 临时文件列表
     * @return array 清理结果
     */
    public function cleanupTempFiles(array $tempFiles): array
    {
        $results = [
            'total' => count($tempFiles),
            'cleaned' => 0,
            'errors' => []
        ];

        foreach ($tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
                try {
                    if (unlink($tempFile)) {
                        $results['cleaned']++;
                        $this->logger->debug('临时文件清理成功', ['temp_file' => $tempFile]);
                    } else {
                        $error = '无法删除临时文件: ' . $tempFile;
                        $results['errors'][] = $error;
                        $this->logger->error($error);
                    }
                } catch (\Exception $e) {
                    $error = '清理临时文件异常: ' . $e->getMessage();
                    $results['errors'][] = $error;
                    $this->logger->error($error, ['temp_file' => $tempFile]);
                }
            }
        }

        $this->logger->info('临时文件清理完成', [
            'total' => $results['total'],
            'cleaned' => $results['cleaned'],
            'errors' => count($results['errors'])
        ]);

        return $results;
    }

    /**
     * 获取下载统计信息
     *
     * @param array $downloadResults 下载结果
     * @return array 统计信息
     */
    public function getDownloadStats(array $downloadResults): array
    {
        $stats = [
            'total_downloads' => $downloadResults['total'] ?? 0,
            'successful_downloads' => $downloadResults['successful'] ?? 0,
            'failed_downloads' => $downloadResults['failed'] ?? 0,
            'success_rate' => $downloadResults['success_rate'] ?? 0,
            'total_duration' => $downloadResults['duration'] ?? 0,
            'average_duration' => 0,
            'total_size' => 0,
            'mime_types' => [],
            'error_types' => []
        ];

        $results = $downloadResults['results'] ?? [];
        $successfulResults = array_filter($results, function($result) {
            return $result['success'] === true;
        });

        if (!empty($successfulResults)) {
            $totalSize = 0;
            $totalDuration = 0;
            $mimeTypes = [];

            foreach ($successfulResults as $result) {
                $totalSize += $result['size'] ?? 0;
                $totalDuration += $result['duration'] ?? 0;

                $mimeType = $result['mime_type'] ?? 'unknown';
                $mimeTypes[$mimeType] = ($mimeTypes[$mimeType] ?? 0) + 1;
            }

            $stats['total_size'] = $totalSize;
            $stats['average_duration'] = round($totalDuration / count($successfulResults), 2);
            $stats['mime_types'] = $mimeTypes;
        }

        // 统计错误类型
        $failedResults = array_filter($results, function($result) {
            return $result['success'] === false;
        });

        foreach ($failedResults as $result) {
            $error = $result['error'] ?? 'unknown_error';
            $stats['error_types'][$error] = ($stats['error_types'][$error] ?? 0) + 1;
        }

        return $stats;
    }
}
