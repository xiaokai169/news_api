<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Psr\Log\LoggerInterface;

/**
 * 图片代理控制器
 * 用于解决跨域访问图片资源的问题
 */
class ImageProxyController extends AbstractController
{
    private LoggerInterface $logger;

    // 允许的图片域名白名单
    private const ALLOWED_DOMAINS = [
        'mmbiz.qpic.cn',
        'res.wx.qq.com',
        'wx.qlogo.cn',
        'mmfb.qpic.cn',
        'obs.myhuaweicloud.com',
        'obs.cn-north-',
        'obs.cn-south-',
        'obs.cn-east-',
        'obs.cn-southwest-',
        's3.amazonaws.com',
        'oss-cn-',
        'cos.ap-'
    ];

    // 支持的图片MIME类型
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
        'image/svg+xml'
    ];

    // 最大文件大小（10MB）
    private const MAX_FILE_SIZE = 10485760;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * 代理图片请求
     *
     * @param Request $request
     * @return Response
     */
    public function proxy(Request $request): Response
    {
        try {
            $imageUrl = $request->query->get('url');

            if (empty($imageUrl)) {
                return new JsonResponse(['error' => '缺少图片URL参数'], Response::HTTP_BAD_REQUEST);
            }

            // 验证URL格式
            if (!$this->isValidUrl($imageUrl)) {
                return new JsonResponse(['error' => '无效的图片URL'], Response::HTTP_BAD_REQUEST);
            }

            // 验证域名白名单
            if (!$this->isAllowedDomain($imageUrl)) {
                $this->logger->warning('尝试访问不在白名单中的域名', ['url' => $imageUrl]);
                return new JsonResponse(['error' => '域名不在允许列表中'], Response::HTTP_FORBIDDEN);
            }

            // 创建缓存键
            $cacheKey = 'image_proxy_' . md5($imageUrl);

            // 检查缓存（这里可以集成Redis等缓存系统）
            // $cachedResponse = $this->cache->get($cacheKey);
            // if ($cachedResponse) {
            //     return new Response($cachedResponse['content'], 200, $cachedResponse['headers']);
            // }

            // 下载图片
            $imageData = $this->downloadImage($imageUrl);

            if (!$imageData) {
                return new JsonResponse(['error' => '图片下载失败'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // 验证图片内容
            $mimeType = $this->getMimeType($imageData);
            if (!$this->isAllowedMimeType($mimeType)) {
                return new JsonResponse(['error' => '不支持的图片类型'], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
            }

            // 检查文件大小
            if (strlen($imageData) > self::MAX_FILE_SIZE) {
                return new JsonResponse(['error' => '图片文件过大'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
            }

            // 设置响应头
            $headers = [
                'Content-Type' => $mimeType,
                'Content-Length' => strlen($imageData),
                'Cache-Control' => 'public, max-age=86400', // 缓存24小时
                'Expires' => gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET',
                'Access-Control-Allow-Headers' => 'Content-Type',
                'X-Image-Proxy' => 'true',
                'X-Original-URL' => $imageUrl
            ];

            // 缓存响应（可选）
            // $this->cache->set($cacheKey, [
            //     'content' => $imageData,
            //     'headers' => $headers
            // ], 86400);

            $this->logger->info('图片代理请求成功', [
                'original_url' => $imageUrl,
                'size' => strlen($imageData),
                'mime_type' => $mimeType
            ]);

            return new Response($imageData, Response::HTTP_OK, $headers);

        } catch (\Exception $e) {
            $this->logger->error('图片代理处理异常', [
                'url' => $imageUrl ?? 'unknown',
                'error' => $e->getMessage(),
                'exception' => $e
            ]);

            return new JsonResponse(['error' => '服务器内部错误'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 验证URL是否有效
     *
     * @param string $url
     * @return bool
     */
    private function isValidUrl(string $url): bool
    {
        // 检查URL格式
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // 检查协议
        $parsedUrl = parse_url($url);
        if (!in_array($parsedUrl['scheme'] ?? '', ['http', 'https'])) {
            return false;
        }

        return true;
    }

    /**
     * 验证域名是否在白名单中
     *
     * @param string $url
     * @return bool
     */
    private function isAllowedDomain(string $url): bool
    {
        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['host'])) {
            return false;
        }

        $host = $parsedUrl['host'];

        foreach (self::ALLOWED_DOMAINS as $allowedDomain) {
            if (strpos($host, $allowedDomain) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 下载图片
     *
     * @param string $url
     * @return string|null
     */
    private function downloadImage(string $url): ?string
    {
        try {
            $client = HttpClient::create([
                'timeout' => 30,
                'max_redirects' => 5,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Referer' => 'https://mp.weixin.qq.com/',
                    'Accept' => 'image/*,*/*;q=0.8'
                ]
            ]);

            $response = $client->request('GET', $url);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('图片下载失败，状态码: ' . $response->getStatusCode(), ['url' => $url]);
                return null;
            }

            $content = $response->getContent();
            if (empty($content)) {
                $this->logger->error('下载的图片内容为空', ['url' => $url]);
                return null;
            }

            return $content;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('图片下载网络错误: ' . $e->getMessage(), ['url' => $url]);
            return null;
        } catch (ClientExceptionInterface | ServerExceptionInterface | RedirectionExceptionInterface $e) {
            $this->logger->error('图片下载HTTP错误: ' . $e->getMessage(), ['url' => $url]);
            return null;
        } catch (\Exception $e) {
            $this->logger->error('图片下载异常: ' . $e->getMessage(), ['url' => $url]);
            return null;
        }
    }

    /**
     * 获取文件的MIME类型
     *
     * @param string $content
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
     * 验证MIME类型是否允许
     *
     * @param string $mimeType
     * @return bool
     */
    private function isAllowedMimeType(string $mimeType): bool
    {
        return in_array($mimeType, self::ALLOWED_MIME_TYPES);
    }

    /**
     * 获取代理统计信息
     *
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        // 这里可以集成实际的统计逻辑
        $stats = [
            'total_requests' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'errors' => 0,
            'bandwidth_used' => 0,
            'popular_domains' => []
        ];

        return new JsonResponse($stats);
    }

    /**
     * 清除缓存
     *
     * @return JsonResponse
     */
    public function clearCache(): JsonResponse
    {
        // 这里可以实现缓存清除逻辑
        return new JsonResponse(['message' => '缓存已清除']);
    }
}
