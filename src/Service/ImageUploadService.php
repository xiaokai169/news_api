<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class ImageUploadService
{
    private const UPLOAD_URL = 'https://biz.arab-bee.com/api/media';
    private const CHANNEL = 'enterprise_process_img';

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * 上传图片到指定API
     */
    public function uploadImage(string $imageUrl): ?string
    {
        try {
            $client = HttpClient::create();

            // 下载原始图片
            $response = $client->request('GET', $imageUrl);
            $imageContent = $response->getContent();

            // 获取图片MIME类型
            $mimeType = $this->getImageMimeType($imageContent);
            if (!$mimeType) {
                $this->logger->error('无法识别图片类型: ' . $imageUrl);
                return null;
            }

            // 生成临时文件名
            $tempFileName = tempnam(sys_get_temp_dir(), 'wechat_img_') . $this->getFileExtension($mimeType);
            file_put_contents($tempFileName, $imageContent);

            // 上传图片
            $uploadResponse = $client->request('POST', self::UPLOAD_URL, [
                'headers' => [
                    'Content-Type' => 'multipart/form-data',
                ],
                'body' => [
                    'channel' => self::CHANNEL,
                    'file' => fopen($tempFileName, 'r'),
                ],
            ]);

            // 清理临时文件
            unlink($tempFileName);

            if ($uploadResponse->getStatusCode() !== 200) {
                $this->logger->error('图片上传失败，状态码: ' . $uploadResponse->getStatusCode());
                return null;
            }

            $result = json_decode($uploadResponse->getContent(), true);

            if (!isset($result['data']['url']) || empty($result['data']['url'])) {
                $this->logger->error('图片上传返回格式错误: ' . $uploadResponse->getContent());
                return null;
            }

            $this->logger->info('图片上传成功: ' . $imageUrl . ' -> ' . $result['data']['url']);
            return $result['data']['url'];

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('图片上传网络错误: ' . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            $this->logger->error('图片上传失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 批量上传图片
     */
    public function uploadImages(array $imageUrls): array
    {
        $results = [];

        foreach ($imageUrls as $imageUrl) {
            $newUrl = $this->uploadImage($imageUrl);
            $results[$imageUrl] = $newUrl;
        }

        return $results;
    }

    /**
     * 从图片内容获取MIME类型
     */
    private function getImageMimeType(string $imageContent): ?string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $imageContent);
        finfo_close($finfo);

        return in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']) ? $mimeType : null;
    }

    /**
     * 根据MIME类型获取文件扩展名
     */
    private function getFileExtension(string $mimeType): string
    {
        $extensions = [
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/gif' => '.gif',
            'image/webp' => '.webp',
        ];

        return $extensions[$mimeType] ?? '.jpg';
    }

    /**
     * 替换文章内容中的图片URL
     */
    public function replaceImageUrls(string $content, array $urlMapping): string
    {
        foreach ($urlMapping as $oldUrl => $newUrl) {
            if ($newUrl) {
                // 替换各种可能的图片标签格式
                $content = str_replace($oldUrl, $newUrl, $content);

                // 处理可能被转义的URL
                $escapedUrl = str_replace('/', '\/', $oldUrl);
                $content = str_replace($escapedUrl, $newUrl, $content);
            }
        }

        return $content;
    }

    /**
     * 从文章内容中提取图片URL
     */
    public function extractImageUrls(string $content): array
    {
        $imageUrls = [];

        // 匹配常见的图片标签格式
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches);
        if (!empty($matches[1])) {
            $imageUrls = array_merge($imageUrls, $matches[1]);
        }

        // 匹配背景图片
        preg_match_all('/background-image:\s*url\(["\']?([^"\'\)]+)["\']?\)/i', $content, $matches);
        if (!empty($matches[1])) {
            $imageUrls = array_merge($imageUrls, $matches[1]);
        }

        // 去重并过滤空值
        $imageUrls = array_filter(array_unique($imageUrls));

        // 只保留微信CDN的图片（可选，根据需求调整）
        $wechatImageUrls = array_filter($imageUrls, function($url) {
            return strpos($url, 'mmbiz.qpic.cn') !== false ||
                   strpos($url, 'res.wx.qq.com') !== false;
        });

        return array_values($wechatImageUrls);
    }
}
