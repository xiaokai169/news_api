<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Monolog\Logger;

class ResourceExtractor
{
    private LoggerInterface $logger;

    // 微信CDN域名列表
    private const WECHAT_CDN_DOMAINS = [
        'mmbiz.qpic.cn',
        'res.wx.qq.com',
        'wx.qlogo.cn',
        'mmfb.qpic.cn'
    ];

    // OBS域名模式（支持华为云OBS等对象存储服务）
    private const OBS_DOMAIN_PATTERNS = [
        'obs.cn-north-',
        'obs.cn-south-',
        'obs.cn-east-',
        'obs.cn-southwest-',
        'obs.north-',
        'obs.south-',
        'obs.east-',
        'obs.ap-',
        'obs.eu-',
        'obs.af-',
        'obs.na-',
        'obs.sa-',
        '.obs.myhuaweicloud.com',
        '.obs.cn-north-',
        '.obs.cn-south-',
        '.obs.cn-east-',
        's3.amazonaws.com',
        's3.amazonaws.com',
        's3-',
        '.s3.',
        'oss-cn-',
        '.oss-',
        'cos.ap-',
        '.cos.ap-'
    ];

    // 支持的媒体文件扩展名
    private const MEDIA_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
        'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'
    ];

    public function __construct(LoggerInterface $logger)
    {
        // 使用专用的微信日志通道
        if ($logger instanceof Logger) {
            $this->logger = $logger->withName('wechat_extractor');
        } else {
            $this->logger = $logger;
        }
    }

    /**
     * 从文章数据中提取所有媒体资源
     *
     * @param array $articleData 文章数据
     * @return array 提取结果
     */
    public function extractFromArticle(array $articleData): array
    {
        $result = [
            'content_urls' => [],
            'thumb_url' => null,
            'total_count' => 0,
            'errors' => []
        ];

        try {
            $this->logger->info('开始提取文章媒体资源', ['article_id' => $articleData['article_id'] ?? 'unknown']);

            // 提取缩略图
            if (!empty($articleData['thumb_url'])) {
                $thumbUrl = $articleData['thumb_url'];
                if ($this->isWechatResource($thumbUrl)) {
                    $result['thumb_url'] = $thumbUrl;
                    $this->logger->debug('提取到缩略图', ['thumb_url' => $thumbUrl]);
                }
            }

            // 提取内容中的媒体资源
            if (!empty($articleData['content'])) {
                $contentUrls = $this->extractFromContent($articleData['content']);
                $result['content_urls'] = $contentUrls;
                $this->logger->debug('从内容中提取到媒体资源', ['count' => count($contentUrls)]);
            }

            $result['total_count'] = count($result['content_urls']) + ($result['thumb_url'] ? 1 : 0);

            $this->logger->info('媒体资源提取完成', [
                'total_count' => $result['total_count'],
                'content_count' => count($result['content_urls']),
                'has_thumb' => !empty($result['thumb_url'])
            ]);

        } catch (\Exception $e) {
            $errorMsg = '提取媒体资源异常: ' . $e->getMessage();
            $result['errors'][] = $errorMsg;
            $this->logger->error($errorMsg, ['articleData' => $articleData, 'exception' => $e]);
        }

        return $result;
    }

    /**
     * 从HTML内容中提取媒体资源URL
     *
     * @param string $content HTML内容
     * @return array URL列表
     */
    public function extractFromContent(string $content): array
    {
        $urls = [];

        try {
            // 提取img标签中的src
            $imgUrls = $this->extractImageTags($content);
            $urls = array_merge($urls, $imgUrls);

            // 提取背景图片URL
            $bgUrls = $this->extractBackgroundImages($content);
            $urls = array_merge($urls, $bgUrls);

            // 提取视频资源
            $videoUrls = $this->extractVideoResources($content);
            $urls = array_merge($urls, $videoUrls);

            // 提取其他可能的媒体资源
            $otherUrls = $this->extractOtherMedia($content);
            $urls = array_merge($urls, $otherUrls);

            // 过滤：保留微信CDN和OBS的资源
            $filteredUrls = array_filter($urls, function($url) {
                return $this->isWechatResource($url) || $this->isObsResource($url);
            });

            // 去重
            $filteredUrls = array_values(array_unique($filteredUrls));

            $this->logger->debug('内容媒体资源提取完成', [
                'original_count' => count($urls),
                'filtered_count' => count($filteredUrls),
                'urls' => $filteredUrls
            ]);

            return $filteredUrls;

        } catch (\Exception $e) {
            $this->logger->error('从内容提取媒体资源失败', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * 提取img标签中的src属性
     *
     * @param string $content HTML内容
     * @return array URL列表
     */
    private function extractImageTags(string $content): array
    {
        $urls = [];

        // 匹配img标签的src属性
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $url) {
                // 验证URL格式
                if ($this->isValidMediaUrl($url)) {
                    $urls[] = $url;
                }
            }
        }

        // 匹配data-src属性（懒加载图片）
        preg_match_all('/<img[^>]+data-src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $url) {
                if ($this->isValidMediaUrl($url)) {
                    $urls[] = $url;
                }
            }
        }

        $this->logger->debug('提取img标签完成', ['count' => count($urls)]);

        return $urls;
    }

    /**
     * 提取CSS背景图片URL
     *
     * @param string $content HTML内容
     * @return array URL列表
     */
    private function extractBackgroundImages(string $content): array
    {
        $urls = [];

        // 匹配background-image样式
        preg_match_all('/background-image:\s*url\(["\']?([^"\'\)]+)["\']?\)/i', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $url) {
                if ($this->isValidMediaUrl($url)) {
                    $urls[] = $url;
                }
            }
        }

        // 匹配background属性
        preg_match_all('/background:\s*[^;]*url\(["\']?([^"\'\)]+)["\']?\)/i', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $url) {
                if ($this->isValidMediaUrl($url)) {
                    $urls[] = $url;
                }
            }
        }

        $this->logger->debug('提取背景图片完成', ['count' => count($urls)]);

        return $urls;
    }

    /**
     * 提取视频资源URL
     *
     * @param string $content HTML内容
     * @return array URL列表
     */
    private function extractVideoResources(string $content): array
    {
        $urls = [];

        // 匹配video标签的src属性
        preg_match_all('/<video[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $url) {
                if ($this->isValidMediaUrl($url)) {
                    $urls[] = $url;
                }
            }
        }

        // 匹配video标签的poster属性
        preg_match_all('/<video[^>]+poster=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $url) {
                if ($this->isValidMediaUrl($url)) {
                    $urls[] = $url;
                }
            }
        }

        // 匹配source标签
        preg_match_all('/<source[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $url) {
                if ($this->isValidMediaUrl($url)) {
                    $urls[] = $url;
                }
            }
        }

        $this->logger->debug('提取视频资源完成', ['count' => count($urls)]);

        return $urls;
    }

    /**
     * 提取其他可能的媒体资源
     *
     * @param string $content HTML内容
     * @return array URL列表
     */
    private function extractOtherMedia(string $content): array
    {
        $urls = [];

        // 匹配audio标签
        preg_match_all('/<audio[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $url) {
                if ($this->isValidMediaUrl($url)) {
                    $urls[] = $url;
                }
            }
        }

        // 匹配iframe标签中的视频源
        preg_match_all('/<iframe[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $url) {
                // 只处理视频平台的iframe
                if ($this->isVideoPlatformUrl($url)) {
                    $urls[] = $url;
                }
            }
        }

        $this->logger->debug('提取其他媒体资源完成', ['count' => count($urls)]);

        return $urls;
    }

    /**
     * 验证URL是否为有效的媒体资源URL
     *
     * @param string $url URL地址
     * @return bool
     */
    private function isValidMediaUrl(string $url): bool
    {
        // 检查URL格式
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // 检查是否为相对路径
        if (strpos($url, 'http') !== 0) {
            return false;
        }

        // 检查文件扩展名
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (empty($extension) || !in_array($extension, self::MEDIA_EXTENSIONS)) {
            // 如果没有扩展名或扩展名不支持，但如果是微信CDN的URL，仍然认为是有效的
            return $this->isWechatResource($url);
        }

        return true;
    }

    /**
     * 检查是否为微信资源
     *
     * @param string $url URL地址
     * @return bool
     */
    public function isWechatResource(string $url): bool
    {
        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['host'])) {
            return false;
        }

        foreach (self::WECHAT_CDN_DOMAINS as $domain) {
            if (strpos($parsedUrl['host'], $domain) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查是否为OBS（对象存储）资源
     *
     * @param string $url URL地址
     * @return bool
     */
    public function isObsResource(string $url): bool
    {
        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['host'])) {
            return false;
        }

        $host = $parsedUrl['host'];

        // 检查是否匹配OBS域名模式
        foreach (self::OBS_DOMAIN_PATTERNS as $pattern) {
            if (strpos($host, $pattern) !== false) {
                $this->logger->debug('检测到OBS资源', ['url' => $url, 'pattern' => $pattern]);
                return true;
            }
        }

        // 检查是否为标准的对象存储URL格式
        // 例如: bucket.obs.region.myhuaweicloud.com 或 bucket.s3.region.amazonaws.com
        if (preg_match('/^[a-z0-9\-]+\.((obs|s3|oss|cos)[\-\.][a-z0-9\-\.]+\.(com|cn))$/i', $host)) {
            $this->logger->debug('检测到标准对象存储资源', ['url' => $url, 'host' => $host]);
            return true;
        }

        return false;
    }

    /**
     * 检查是否为视频平台URL
     *
     * @param string $url URL地址
     * @return bool
     */
    private function isVideoPlatformUrl(string $url): bool
    {
        $videoPlatforms = [
            'v.qq.com',
            'youku.com',
            'bilibili.com',
            'youtube.com',
            'youtu.be'
        ];

        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['host'])) {
            return false;
        }

        foreach ($videoPlatforms as $platform) {
            if (strpos($parsedUrl['host'], $platform) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 批量提取多篇文章的媒体资源
     *
     * @param array $articlesData 文章数据列表
     * @return array 批量提取结果
     */
    public function batchExtractFromArticles(array $articlesData): array
    {
        $results = [];
        $totalResources = 0;

        foreach ($articlesData as $index => $articleData) {
            $extracted = $this->extractFromArticle($articleData);
            $results[$index] = $extracted;
            $totalResources += $extracted['total_count'];
        }

        $this->logger->info('批量提取媒体资源完成', [
            'articles_count' => count($articlesData),
            'total_resources' => $totalResources
        ]);

        return [
            'articles' => $results,
            'summary' => [
                'articles_count' => count($articlesData),
                'total_resources' => $totalResources,
                'articles_with_resources' => count(array_filter($results, function($r) { return $r['total_count'] > 0; }))
            ]
        ];
    }

    /**
     * 获取媒体资源的统计信息
     *
     * @param array $extractedData 提取的数据
     * @return array 统计信息
     */
    public function getResourceStats(array $extractedData): array
    {
        $stats = [
            'total_articles' => count($extractedData),
            'articles_with_content_resources' => 0,
            'articles_with_thumb_resources' => 0,
            'total_content_resources' => 0,
            'total_thumb_resources' => 0,
            'unique_domains' => []
        ];

        foreach ($extractedData as $articleData) {
            if (count($articleData['content_urls']) > 0) {
                $stats['articles_with_content_resources']++;
                $stats['total_content_resources'] += count($articleData['content_urls']);

                // 统计域名
                foreach ($articleData['content_urls'] as $url) {
                    $parsedUrl = parse_url($url);
                    $domain = $parsedUrl['host'] ?? 'unknown';
                    $stats['unique_domains'][$domain] = ($stats['unique_domains'][$domain] ?? 0) + 1;
                }
            }

            if ($articleData['thumb_url']) {
                $stats['articles_with_thumb_resources']++;
                $stats['total_thumb_resources']++;

                // 统计域名
                $parsedUrl = parse_url($articleData['thumb_url']);
                $domain = $parsedUrl['host'] ?? 'unknown';
                $stats['unique_domains'][$domain] = ($stats['unique_domains'][$domain] ?? 0) + 1;
            }
        }

        return $stats;
    }
}
