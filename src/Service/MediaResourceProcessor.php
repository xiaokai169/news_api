<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Siganushka\MediaBundle\MediaManagerInterface;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class MediaResourceProcessor
{
    private LoggerInterface $logger;

    // 微信CDN域名列表
    private const WECHAT_CDN_DOMAINS = [
        'mmbiz.qpic.cn',
        'res.wx.qq.com',
        'wx.qlogo.cn',
        'mmfb.qpic.cn'
    ];

    // 支持的文件类型
    private const SUPPORTED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
        'video/mp4',
        'video/avi',
        'video/mov',
        'video/wmv'
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MediaManagerInterface $mediaManager,
        LoggerInterface $logger
    ) {
        // 使用专用的微信日志通道
        if ($logger instanceof Logger) {
            $this->logger = $logger->withName('wechat_media');
        } else {
            $this->logger = $logger;
        }
    }

    /**
     * 处理文章中的媒体资源
     *
     * @param string $content 文章内容
     * @param string|null $thumbUrl 缩略图URL
     * @return array 处理结果
     */
    public function processArticleMedia(string $content, ?string $thumbUrl = null): array
    {
        $result = [
            'content' => $content,
            'thumb_url' => $thumbUrl,
            'processed_resources' => [],
            'errors' => []
        ];

        try {
            $this->logger->info('开始处理文章媒体资源');

            // 提取内容中的所有媒体资源URL
            $contentUrls = $this->extractMediaUrls($content);
            $this->logger->info('从内容中提取到媒体资源', ['count' => count($contentUrls)]);

            // 处理缩略图
            $thumbProcessed = false;
            if ($thumbUrl && $this->isWechatResource($thumbUrl)) {
                $thumbResult = $this->processSingleResource($thumbUrl, 'thumb');
                if ($thumbResult['success']) {
                    $result['thumb_url'] = $thumbResult['new_url'];
                    $thumbProcessed = true;
                    $result['processed_resources'][] = [
                        'type' => 'thumb',
                        'original_url' => $thumbUrl,
                        'new_url' => $thumbResult['new_url']
                    ];
                } else {
                    $result['errors'][] = '缩略图处理失败: ' . $thumbResult['error'];
                }
            }

            // 处理内容中的媒体资源
            $urlMapping = [];
            foreach ($contentUrls as $url) {
                if ($this->isWechatResource($url)) {
                    $resourceResult = $this->processSingleResource($url, 'content');
                    if ($resourceResult['success']) {
                        $urlMapping[$url] = $resourceResult['new_url'];
                        $result['processed_resources'][] = [
                            'type' => 'content',
                            'original_url' => $url,
                            'new_url' => $resourceResult['new_url']
                        ];
                    } else {
                        $result['errors'][] = '媒体资源处理失败: ' . $url . ' - ' . $resourceResult['error'];
                    }
                }
            }

            // 替换内容中的URL
            if (!empty($urlMapping)) {
                $result['content'] = $this->replaceUrlsInContent($content, $urlMapping);
                $this->logger->info('完成内容URL替换', ['replaced_count' => count($urlMapping)]);
            }

            $this->logger->info('媒体资源处理完成', [
                'processed_count' => count($result['processed_resources']),
                'errors_count' => count($result['errors']),
                'thumb_processed' => $thumbProcessed
            ]);

        } catch (\Exception $e) {
            $errorMsg = '媒体资源处理异常: ' . $e->getMessage();
            $result['errors'][] = $errorMsg;
            $this->logger->error($errorMsg, ['exception' => $e]);
        }

        return $result;
    }

    /**
     * 处理单个媒体资源
     *
     * @param string $url 原始URL
     * @param string $type 资源类型 (thumb, content)
     * @return array 处理结果
     */
    private function processSingleResource(string $url, string $type): array
    {
        $result = [
            'success' => false,
            'new_url' => null,
            'error' => null
        ];

        try {
            $this->logger->debug('开始处理媒体资源', ['url' => $url, 'type' => $type]);

            // 下载资源
            $tempFile = $this->downloadResource($url);
            if (!$tempFile) {
                $result['error'] = '资源下载失败';
                return $result;
            }

            // 生成文件名
            $filename = $this->generateFilename($url, $type);

            // 使用MediaManagerInterface保存文件
            $ufile = $this->mediaManager->save('official', $tempFile);
            // $this->entityManager->persist($ufile);
            // $this->entityManager->flush();
            if (!$ufile) {
                $result['error'] = '文件保存失败';
                // 清理临时文件
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
                return $result;
            }

            // 获取新URL
            $newUrl = $ufile->getUrl();
            if (!$newUrl) {
                $result['error'] = '获取新URL失败';
                // 清理临时文件
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
                return $result;
            }

            // 清理临时文件
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            $result['success'] = true;
            $result['new_url'] = $newUrl;

            $this->logger->info('媒体资源处理成功', [
                'original_url' => $url,
                'new_url' => $newUrl,
                'type' => $type
            ]);

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            $this->logger->error('处理单个媒体资源失败', [
                'url' => $url,
                'type' => $type,
                'error' => $e->getMessage(),
                'exception' => $e
            ]);
        }

        return $result;
    }

    /**
     * 下载媒体资源到临时文件
     *
     * @param string $url 资源URL
     * @return string|null 临时文件路径
     */
    private function downloadResource(string $url): ?string
    {
        try {
            $client = HttpClient::create();

            // 设置超时时间
            $response = $client->request('GET', $url, [
                'timeout' => 30,
                'max_redirects' => 5
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('资源下载失败，状态码: ' . $response->getStatusCode(), ['url' => $url]);
                return null;
            }

            $content = $response->getContent();
            if (empty($content)) {
                $this->logger->error('下载的内容为空', ['url' => $url]);
                return null;
            }

            // 检查MIME类型
            $mimeType = $this->getMimeType($content);
            if (!$this->isSupportedMimeType($mimeType)) {
                $this->logger->error('不支持的文件类型', ['url' => $url, 'mime_type' => $mimeType]);
                return null;
            }

            // 创建临时文件
            $tempFile = tempnam(sys_get_temp_dir(), 'wechat_media_') . $this->getFileExtension($mimeType);

            if (file_put_contents($tempFile, $content) === false) {
                $this->logger->error('无法写入临时文件', ['temp_file' => $tempFile]);
                return null;
            }

            $this->logger->debug('资源下载成功', [
                'url' => $url,
                'temp_file' => $tempFile,
                'size' => strlen($content),
                'mime_type' => $mimeType
            ]);

            return $tempFile;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('资源下载网络错误: ' . $e->getMessage(), ['url' => $url]);
            return null;
        } catch (\Exception $e) {
            $this->logger->error('资源下载异常: ' . $e->getMessage(), ['url' => $url]);
            return null;
        }
    }

    /**
     * 从文章内容中提取媒体资源URL
     *
     * @param string $content 文章内容
     * @return array URL列表
     */
    private function extractMediaUrls(string $content): array
    {
        $urls = [];

        // 提取img标签中的src
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches);
        if (!empty($matches[1])) {
            $this->logger->debug('提取到src属性URL', ['urls' => $matches[1]]);
            $urls = array_merge($urls, $matches[1]);
        }

        // 提取img标签中的data-src（懒加载图片）
        preg_match_all('/<img[^>]+data-src=["\']([^"\']+)["\']/i', $content, $matches);
        if (!empty($matches[1])) {
            $this->logger->debug('提取到data-src属性URL', ['urls' => $matches[1]]);
            $urls = array_merge($urls, $matches[1]);
        }

        // 提取背景图片URL
        preg_match_all('/background-image:\s*url\(["\']?([^"\'\)]+)["\']?\)/i', $content, $matches);
        if (!empty($matches[1])) {
            $this->logger->debug('提取到背景图片URL', ['urls' => $matches[1]]);
            $urls = array_merge($urls, $matches[1]);
        }

        // 提取video标签中的src和poster
        preg_match_all('/<video[^>]+src=["\']([^"\']+)["\']/i', $content, $matches);
        if (!empty($matches[1])) {
            $this->logger->debug('提取到video src URL', ['urls' => $matches[1]]);
            $urls = array_merge($urls, $matches[1]);
        }

        preg_match_all('/<video[^>]+poster=["\']([^"\']+)["\']/i', $content, $matches);
        if (!empty($matches[1])) {
            $this->logger->debug('提取到video poster URL', ['urls' => $matches[1]]);
            $urls = array_merge($urls, $matches[1]);
        }

        // 去重并过滤
        $urls = array_filter(array_unique($urls));

        $this->logger->debug('提取到的所有媒体URL', ['urls' => $urls]);

        return $urls;
    }

    /**
     * 替换内容中的URL
     *
     * @param string $content 原始内容
     * @param array $urlMapping URL映射表
     * @return string 替换后的内容
     */
    private function replaceUrlsInContent(string $content, array $urlMapping): string
    {
        $this->logger->info('开始替换内容中的URL', ['mapping_count' => count($urlMapping)]);

        foreach ($urlMapping as $oldUrl => $newUrl) {
            // 标准化URL（移除显式443端口等）
            $normalizedOldUrl = $this->normalizeUrl($oldUrl);
            $normalizedNewUrl = $this->normalizeUrl($newUrl);

            $this->logger->debug('替换URL', [
                'old_url' => $oldUrl,
                'new_url' => $newUrl,
                'normalized_old_url' => $normalizedOldUrl,
                'normalized_new_url' => $normalizedNewUrl
            ]);

            $replacements = 0;

            // 1. 替换img标签中的原始src属性（确保只匹配独立的src属性）
            $pattern = '/(<img[^>]+)(?<!data-)src=["\']' . preg_quote($oldUrl, '/') . '["\']([^>]*>)/i';
            $content = preg_replace($pattern, '$1src="' . $newUrl . '"$2', $content, -1, $count);
            $replacements += $count;

            // 2. 将data-src属性替换为src属性（移除懒加载机制）
            $pattern = '/(<img[^>]+)data-src=["\']' . preg_quote($oldUrl, '/') . '["\']([^>]*>)/i';
            $content = preg_replace_callback($pattern, function($matches) use ($newUrl, $oldUrl) {
                $imgTag = $matches[0];

                // 检查是否已经有src属性（确保不匹配data-src）
                if (!preg_match('/(?<!data-)\bsrc\s*=\s*["\'][^"\']*["\']/', $imgTag)) {
                    // 没有src属性，将data-src转换为src
                    $imgTag = preg_replace('/data-src=["\']' . preg_quote($oldUrl, '/') . '["\']/', 'src="' . $newUrl . '"', $imgTag);
                } else {
                    // 已经有src属性，只移除data-src
                    $imgTag = preg_replace('/\s+data-src=["\']' . preg_quote($oldUrl, '/') . '["\']/', '', $imgTag);
                }

                return $imgTag;
            }, $content, -1, $count);
            $replacements += $count;

            // 4. 替换背景图片URL
            $pattern = '/background-image:\s*url\(["\']?' . preg_quote($oldUrl, '/') . '["\']?\)/i';
            $content = preg_replace($pattern, 'background-image: url("' . $newUrl . '")', $content, -1, $count);
            $replacements += $count;

            // 5. 替换background属性中的URL
            $pattern = '/background:\s*[^;]*url\(["\']?' . preg_quote($oldUrl, '/') . '["\']?\)/i';
            $content = preg_replace($pattern, 'background: url("' . $newUrl . '")', $content, -1, $count);
            $replacements += $count;

            // 6. 替换video标签中的src属性
            $pattern = '/(<video[^>]+)src=["\']' . preg_quote($oldUrl, '/') . '["\']([^>]*>)/i';
            $content = preg_replace($pattern, '$1src="' . $newUrl . '"$2', $content, -1, $count);
            $replacements += $count;

            // 7. 替换video标签中的poster属性
            $pattern = '/(<video[^>]+)poster=["\']' . preg_quote($oldUrl, '/') . '["\']([^>]*>)/i';
            $content = preg_replace($pattern, '$1poster="' . $newUrl . '"$2', $content, -1, $count);
            $replacements += $count;

            // 8. 替换source标签中的src属性
            $pattern = '/(<source[^>]+)src=["\']' . preg_quote($oldUrl, '/') . '["\']([^>]*>)/i';
            $content = preg_replace($pattern, '$1src="' . $newUrl . '"$2', $content, -1, $count);
            $replacements += $count;

            // 9. 处理标准化的URL替换（处理端口标准化后的URL）
            if ($normalizedOldUrl !== $oldUrl) {
                $pattern = '/(?<!data-)src=["\']' . preg_quote($normalizedOldUrl, '/') . '["\']/i';
                $content = preg_replace($pattern, 'src="' . $newUrl . '"', $content, -1, $count);
                $replacements += $count;

                // 将标准化的data-src替换为src
                $pattern = '/(<img[^>]+)data-src=["\']' . preg_quote($normalizedOldUrl, '/') . '["\']([^>]*>)/i';
                $content = preg_replace_callback($pattern, function($matches) use ($newUrl, $normalizedOldUrl) {
                    $imgTag = $matches[0];

                    // 检查是否已经有src属性（确保不匹配data-src）
                    if (!preg_match('/(?<!data-)\bsrc\s*=\s*["\'][^"\']*["\']/', $imgTag)) {
                        // 没有src属性，将data-src转换为src
                        $imgTag = preg_replace('/data-src=["\']' . preg_quote($normalizedOldUrl, '/') . '["\']/', 'src="' . $newUrl . '"', $imgTag);
                    } else {
                        // 已经有src属性，只移除data-src
                        $imgTag = preg_replace('/\s+data-src=["\']' . preg_quote($normalizedOldUrl, '/') . '["\']/', '', $imgTag);
                    }

                    return $imgTag;
                }, $content, -1, $count);
                $replacements += $count;
            }

            // 10. 处理URL编码的情况
            $encodedOldUrl = urlencode($oldUrl);
            if ($encodedOldUrl !== $oldUrl) {
                $pattern = '/(?<!data-)src=["\']' . preg_quote($encodedOldUrl, '/') . '["\']/i';
                $content = preg_replace($pattern, 'src="' . $newUrl . '"', $content, -1, $count);
                $replacements += $count;

                // 将URL编码的data-src替换为src
                $pattern = '/(<img[^>]+)data-src=["\']' . preg_quote($encodedOldUrl, '/') . '["\']([^>]*>)/i';
                $content = preg_replace_callback($pattern, function($matches) use ($newUrl, $encodedOldUrl) {
                    $imgTag = $matches[0];

                    // 检查是否已经有src属性（确保不匹配data-src）
                    if (!preg_match('/(?<!data-)\bsrc\s*=\s*["\'][^"\']*["\']/', $imgTag)) {
                        // 没有src属性，将data-src转换为src
                        $imgTag = preg_replace('/data-src=["\']' . preg_quote($encodedOldUrl, '/') . '["\']/', 'src="' . $newUrl . '"', $imgTag);
                    } else {
                        // 已经有src属性，只移除data-src
                        $imgTag = preg_replace('/\s+data-src=["\']' . preg_quote($encodedOldUrl, '/') . '["\']/', '', $imgTag);
                    }

                    return $imgTag;
                }, $content, -1, $count);
                $replacements += $count;
            }

            // 11. 最后的通用替换（作为兜底）
            $contentBefore = $content;
            $content = str_replace($oldUrl, $newUrl, $content);
            if ($contentBefore !== $content) {
                $replacements += substr_count($contentBefore, $oldUrl) - substr_count($content, $oldUrl);
            }

            $this->logger->debug('URL替换完成', [
                'old_url' => $oldUrl,
                'new_url' => $newUrl,
                'total_replacements' => $replacements
            ]);
        }

        // 12. 清理所有剩余的data-src属性，将它们转换为src属性（在所有URL映射处理完成后执行）
        $content = preg_replace_callback('/(<img[^>]+)data-src=["\']([^"\']*)["\']([^>]*>)/i', function($matches) use ($urlMapping) {
            $imgTag = $matches[0];
            $dataSrcValue = $matches[2];

            // 如果data-src为空，直接移除
            if (empty($dataSrcValue)) {
                return preg_replace('/\s*data-src=["\']["\']/', '', $imgTag);
            }

            // 检查是否已经有src属性（确保不匹配data-src）
            if (!preg_match('/(?<!data-)\bsrc\s*=\s*["\'][^"\']*["\']/', $imgTag)) {
                // 将data-src转换为src，如果有映射则使用映射后的URL
                $finalSrc = isset($urlMapping[$dataSrcValue]) ? $urlMapping[$dataSrcValue] : $dataSrcValue;
                $imgTag = preg_replace('/data-src=["\']' . preg_quote($dataSrcValue, '/') . '["\']/', 'src="' . $finalSrc . '"', $imgTag);
            } else {
                // 如果已经有src，则移除data-src
                $imgTag = preg_replace('/\s+data-src=["\'][^"\']*["\']/', '', $imgTag);
            }

            return $imgTag;
        }, $content);

        $this->logger->info('所有URL替换完成');
        return $content;
    }

    /**
     * 标准化URL（移除显式端口、规范化协议等）
     *
     * @param string $url 原始URL
     * @return string 标准化后的URL
     */
    private function normalizeUrl(string $url): string
    {
        $parsedUrl = parse_url($url);
        if (!$parsedUrl || !isset($parsedUrl['scheme']) || !isset($parsedUrl['host'])) {
            return $url;
        }

        $scheme = $parsedUrl['scheme'];
        $host = $parsedUrl['host'];
        $port = $parsedUrl['port'] ?? null;
        $path = $parsedUrl['path'] ?? '';
        $query = $parsedUrl['query'] ?? '';
        $fragment = $parsedUrl['fragment'] ?? '';

        // 移除显式的443端口（HTTPS默认端口）
        if ($port === 443 && $scheme === 'https') {
            $port = null;
        }

        // 移除显式的80端口（HTTP默认端口）
        if ($port === 80 && $scheme === 'http') {
            $port = null;
        }

        // 重建URL
        $normalizedUrl = $scheme . '://' . $host;

        if ($port) {
            $normalizedUrl .= ':' . $port;
        }

        $normalizedUrl .= $path;

        if ($query) {
            $normalizedUrl .= '?' . $query;
        }

        if ($fragment) {
            $normalizedUrl .= '#' . $fragment;
        }

        return $normalizedUrl;
    }

    /**
     * 检查是否为微信资源
     *
     * @param string $url URL地址
     * @return bool
     */
    private function isWechatResource(string $url): bool
    {
        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['host'])) {
            $this->logger->debug('URL解析失败，没有host字段', ['url' => $url]);
            return false;
        }

        $this->logger->debug('检查是否为微信资源', ['url' => $url, 'host' => $parsedUrl['host']]);

        foreach (self::WECHAT_CDN_DOMAINS as $domain) {
            if (strpos($parsedUrl['host'], $domain) !== false) {
                $this->logger->debug('确认为微信CDN资源', ['url' => $url, 'matched_domain' => $domain]);
                return true;
            }
        }

        // 检查是否为华为云OBS（已处理的资源）
        if (strpos($parsedUrl['host'], 'obs.myhuaweicloud.com') !== false) {
            $this->logger->debug('华为云OBS资源，无需再次处理', ['url' => $url]);
            return false;
        }

        $this->logger->debug('非微信CDN资源', ['url' => $url, 'host' => $parsedUrl['host']]);
        return false;
    }

    /**
     * 生成文件名
     *
     * @param string $url 原始URL
     * @param string $type 资源类型
     * @return string
     */
    private function generateFilename(string $url, string $type): string
    {
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '';

        // 从URL路径中提取文件名
        $filename = basename($path);
        if (empty($filename) || $filename === '/') {
            $filename = 'wechat_media';
        }

        // 添加时间戳和类型前缀
        $timestamp = time();
        $prefix = $type === 'thumb' ? 'thumb' : 'content';

        // 移除文件扩展名，稍后会根据MIME类型重新添加
        $filenameWithoutExt = preg_replace('/\.[^.]+$/', '', $filename);

        return sprintf('%s_%s_%s', $prefix, $filenameWithoutExt, $timestamp);
    }

    /**
     * 获取文件的MIME类型
     *
     * @param string $content 文件内容
     * @return string|null
     */
    private function getMimeType(string $content): ?string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $content);
        finfo_close($finfo);

        return $mimeType;
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
     * @return string
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
            'video/wmv' => '.wmv'
        ];

        return $extensions[$mimeType] ?? '.jpg';
    }

    /**
     * 批量处理媒体资源（用于异步处理）
     *
     * @param array $resources 资源列表
     * @return array 处理结果
     */
    public function batchProcessResources(array $resources): array
    {
        $results = [];

        foreach ($resources as $resource) {
            $url = $resource['url'] ?? '';
            $type = $resource['type'] ?? 'content';

            if (empty($url)) {
                continue;
            }

            $result = $this->processSingleResource($url, $type);
            $results[] = [
                'original_url' => $url,
                'type' => $type,
                'result' => $result
            ];
        }

        $this->logger->info('批量处理媒体资源完成', [
            'total_count' => count($resources),
            'processed_count' => count($results)
        ]);

        return $results;
    }
}
