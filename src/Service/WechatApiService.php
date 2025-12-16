<?php

namespace App\Service;

use App\Entity\WechatPublicAccount;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class WechatApiService
{
    private const WECHAT_API_BASE = 'https://api.weixin.qq.com/cgi-bin';

    public function __construct(
        private LoggerInterface $logger
    ) {
        // 使用专用的微信日志通道
        if ($this->logger instanceof Logger) {
            $this->logger = $this->logger->withName('wechat');
        }
    }

    /**
     * 获取访问令牌
     */
    public function getAccessToken(WechatPublicAccount $account): ?string
    {
        try {
            $client = HttpClient::create();

            $response = $client->request('GET', self::WECHAT_API_BASE . '/token', [
                'query' => [
                    'grant_type' => 'client_credential',
                    'appid' => $account->getAppId(),
                    'secret' => $account->getAppSecret(),
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('获取access_token失败，状态码: ' . $response->getStatusCode());
                return null;
            }

            $result = json_decode($response->getContent(), true);

            if (isset($result['errcode']) && $result['errcode'] !== 0) {
                $appId = $account->getAppId();
                $appSecret = $account->getAppSecret();
                $this->logger->error('获取access_token返回错误: ' . $result['errmsg'] .
                    ', appid: ' . $appId .
                    ', secret: ' . substr($appSecret, 0, 8) . '***');
                return null;
            }

            if (!isset($result['access_token'])) {
                $this->logger->error('获取access_token返回格式错误: ' . $response->getContent());
                return null;
            }

            $this->logger->info('获取access_token成功');
            return $result['access_token'];

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('获取access_token网络错误: ' . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            $this->logger->error('获取access_token失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 批量获取素材列表（文章）
     */
    public function getArticleList(string $accessToken, int $offset = 0, int $count = 20): ?array
    {
        try {
            $client = HttpClient::create();

            $response = $client->request('POST', self::WECHAT_API_BASE . '/material/batchget_material', [
                'query' => [
                    'access_token' => $accessToken,
                ],
                'json' => [
                    'type' => 'news',
                    'offset' => $offset,
                    'count' => $count,
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('获取文章列表失败，状态码: ' . $response->getStatusCode());
                return null;
            }

            $result = json_decode($response->getContent(), true);

            if (isset($result['errcode']) && $result['errcode'] !== 0) {
                $this->logger->error('获取文章列表返回错误: ' . $result['errmsg']);
                return null;
            }

            if (!isset($result['item'])) {
                $this->logger->error('获取文章列表返回格式错误: ' . $response->getContent());
                return null;
            }

            $this->logger->info('获取文章列表成功，数量: ' . count($result['item']));
            return $result['item'];

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('获取文章列表网络错误: ' . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            $this->logger->error('获取文章列表失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 获取素材详情（单篇文章）
     */
    public function getArticleDetail(string $accessToken, string $mediaId): ?array
    {
        try {
            $client = HttpClient::create();

            $response = $client->request('POST', self::WECHAT_API_BASE . '/material/get_material', [
                'query' => [
                    'access_token' => $accessToken,
                ],
                'json' => [
                    'media_id' => $mediaId,
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('获取文章详情失败，状态码: ' . $response->getStatusCode());
                return null;
            }

            $result = json_decode($response->getContent(), true);

            if (isset($result['errcode']) && $result['errcode'] !== 0) {
                $this->logger->error('获取文章详情返回错误: ' . $result['errmsg']);
                return null;
            }

            $this->logger->info('获取文章详情成功: ' . $mediaId);
            return $result;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('获取文章详情网络错误: ' . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            $this->logger->error('获取文章详情失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 批量获取所有文章（分页获取）
     */
    public function getAllArticles(string $accessToken, int $batchSize = 20): array
    {
        $allArticles = [];
        $offset = 0;

        do {
            $articles = $this->getArticleList($accessToken, $offset, $batchSize);

            if ($articles === null) {
                $this->logger->error('获取文章列表失败，停止批量获取');
                break;
            }

            if (empty($articles)) {
                $this->logger->info('没有更多文章了');
                break;
            }

            $allArticles = array_merge($allArticles, $articles);
            $offset += count($articles);

            $this->logger->info('已获取文章: ' . count($allArticles) . ' 篇');

            // 防止无限循环
            if (count($articles) < $batchSize) {
                break;
            }

        } while (true);

        return $allArticles;
    }

    /**
     * 批量获取草稿箱文章列表
     */
    public function getDraftArticleList(string $accessToken, int $offset = 0, int $count = 20): ?array
    {
        try {
            $client = HttpClient::create();

            $response = $client->request('POST', self::WECHAT_API_BASE . '/draft/batchget', [
                'query' => [
                    'access_token' => $accessToken,
                ],
                'json' => [
                    'offset' => $offset,
                    'count' => $count,
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('获取草稿箱文章列表失败，状态码: ' . $response->getStatusCode());
                return null;
            }

            $result = json_decode($response->getContent(), true);

            if (isset($result['errcode']) && $result['errcode'] !== 0) {
                $this->logger->error('获取草稿箱文章列表返回错误: ' . $result['errmsg']);
                return null;
            }

            if (!isset($result['item'])) {
                $this->logger->error('获取草稿箱文章列表返回格式错误: ' . $response->getContent());
                return null;
            }

            $this->logger->info('获取草稿箱文章列表成功，数量: ' . count($result['item']));
            return $result['item'];

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('获取草稿箱文章列表网络错误: ' . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            $this->logger->error('获取草稿箱文章列表失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 批量获取所有草稿箱文章（分页获取）
     */
    public function getAllDraftArticles(string $accessToken, int $batchSize = 20): array
    {
        $allArticles = [];
        $offset = 0;

        do {
            $articles = $this->getDraftArticleList($accessToken, $offset, $batchSize);

            if ($articles === null) {
                $this->logger->error('获取草稿箱文章列表失败，停止批量获取');
                break;
            }

            if (empty($articles)) {
                $this->logger->info('没有更多草稿箱文章了');
                break;
            }

            $allArticles = array_merge($allArticles, $articles);
            $offset += count($articles);

            $this->logger->info('已获取草稿箱文章: ' . count($allArticles) . ' 篇');

            // 防止无限循环
            if (count($articles) < $batchSize) {
                break;
            }

        } while (true);

        return $allArticles;
    }

    /**
     * 获取已发布消息列表（根据微信官方文档）
     */
    public function getPublishedArticleList(string $accessToken, int $offset = 0, int $count = 20, int $noContent = 0, int $beginDate = 0, int $endDate = 0): ?array
    {
        try {
            $client = HttpClient::create();

            $requestData = [
                'offset' => $offset,
                'count' => $count,
                'no_content' => $noContent,
            ];

            // 添加日期范围参数（如果提供）
            if ($beginDate > 0) {
                $requestData['begin_date'] = $beginDate;
            }
            if ($endDate > 0) {
                $requestData['end_date'] = $endDate;
            }

            $this->logger->info('调用已发布消息API: ' . self::WECHAT_API_BASE . '/freepublish/batchget');
            $this->logger->info('请求参数: ' . json_encode($requestData));

            $response = $client->request('POST', self::WECHAT_API_BASE . '/freepublish/batchget', [
                'query' => [
                    'access_token' => $accessToken,
                ],
                'json' => $requestData,
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('获取已发布消息列表失败，状态码: ' . $response->getStatusCode());
                return null;
            }

            $responseContent = $response->getContent();
            $result = json_decode($responseContent, true);

            $this->logger->info('已发布消息API完整响应: ' . $responseContent);

            if (isset($result['errcode']) && $result['errcode'] !== 0) {
                $this->logger->error('获取已发布消息列表返回错误: ' . $result['errmsg']);
                return null;
            }

            if (!isset($result['item'])) {
                $this->logger->error('获取已发布消息列表返回格式错误: ' . $responseContent);
                return null;
            }

            $this->logger->info('获取已发布消息列表成功，数量: ' . count($result['item']));
            return $result['item'];

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('获取已发布消息列表网络错误: ' . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            $this->logger->error('获取已发布消息列表失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 批量获取所有已发布消息（分页获取）
     */
    public function getAllPublishedArticles(string $accessToken, int $batchSize = 20, int $noContent = 0, int $beginDate = 0, int $endDate = 0): array
    {
        $allArticles = [];
        $offset = 0;

        do {
            $articles = $this->getPublishedArticleList($accessToken, $offset, $batchSize, $noContent, $beginDate, $endDate);

            if ($articles === null) {
                $this->logger->error('获取已发布消息列表失败，停止批量获取');
                break;
            }

            if (empty($articles)) {
                $this->logger->info('没有更多已发布消息了');
                break;
            }

            $allArticles = array_merge($allArticles, $articles);
            $offset += count($articles);

            $this->logger->info('已获取已发布消息: ' . count($allArticles) . ' 篇');

            // 防止无限循环
            if (count($articles) < $batchSize) {
                break;
            }

        } while (true);

        return $allArticles;
    }

    /**
     * 从已发布消息项中提取文章数据
     */
    public function extractArticlesFromPublishedItem(array $item): array
    {
        $articles = [];

        if (!isset($item['content'])) {
            $this->logger->warning('已发布消息项缺少content字段', ['item' => array_keys($item)]);
            return $articles;
        }

        $content = $item['content'];

        // 支持两种数据结构：news_item (素材) 和 item (已发布消息)
        $newsItems = null;

        if (isset($content['news_item'])) {
            // 素材数据结构
            $newsItems = $content['news_item'];
            $this->logger->debug('使用news_item结构提取文章', ['count' => count($newsItems)]);
        } elseif (isset($content['item'])) {
            // 已发布消息数据结构
            $newsItems = $content['item'];
            $this->logger->debug('使用item结构提取文章', ['count' => count($newsItems)]);
        } else {
            $this->logger->warning('content字段中未找到news_item或item', ['content_keys' => array_keys($content)]);
            return $articles;
        }

        $mediaId = $item['media_id'] ?? '';
        $articleId = $item['article_id'] ?? $mediaId; // 优先使用article_id，否则使用media_id
        $publishTime = $item['publish_time'] ?? $item['update_time'] ?? time();

        foreach ($newsItems as $newsItem) {
            // 为每篇文章生成唯一的article_id
            $uniqueArticleId = $articleId . '_' . ($newsItem['title'] ?? md5(serialize($newsItem)));

            $articles[] = [
                'article_id' => $uniqueArticleId,
                'media_id' => $mediaId,
                'publish_time' => $publishTime,
                'update_time' => $item['update_time'] ?? null,
                'title' => $newsItem['title'] ?? '',
                'author' => $newsItem['author'] ?? '',
                'digest' => $newsItem['digest'] ?? '',
                'content' => $newsItem['content'] ?? '',
                'content_source_url' => $newsItem['content_source_url'] ?? '',
                'thumb_media_id' => $newsItem['thumb_media_id'] ?? '',
                'show_cover_pic' => $newsItem['show_cover_pic'] ?? 0,
                'need_open_comment' => $newsItem['need_open_comment'] ?? 0,
                'url' => $newsItem['url'] ?? '',
                'thumb_url' => $newsItem['thumb_url'] ?? '',
            ];
        }

        $this->logger->debug('成功提取文章数据', ['extracted_count' => count($articles)]);
        return $articles;
    }

    /**
     * 批量提取所有已发布消息数据
     */
    public function extractAllPublishedArticles(array $items): array
    {
        $allArticles = [];

        foreach ($items as $item) {
            $articles = $this->extractArticlesFromPublishedItem($item);
            $allArticles = array_merge($allArticles, $articles);
        }

        return $allArticles;
    }

    /**
     * 从文章素材项中提取文章数据
     */
    public function extractArticlesFromItem(array $item): array
    {
        $articles = [];

        if (!isset($item['content']['news_item'])) {
            return $articles;
        }

        $mediaId = $item['media_id'] ?? '';
        $updateTime = $item['update_time'] ?? time();

        foreach ($item['content']['news_item'] as $newsItem) {
            $articles[] = [
                'media_id' => $mediaId,
                'update_time' => $updateTime,
                'title' => $newsItem['title'] ?? '',
                'author' => $newsItem['author'] ?? '',
                'digest' => $newsItem['digest'] ?? '',
                'content' => $newsItem['content'] ?? '',
                'content_source_url' => $newsItem['content_source_url'] ?? '',
                'thumb_media_id' => $newsItem['thumb_media_id'] ?? '',
                'show_cover_pic' => $newsItem['show_cover_pic'] ?? 0,
                'url' => $newsItem['url'] ?? '',
                'thumb_url' => $newsItem['thumb_url'] ?? '',
            ];
        }

        return $articles;
    }

    /**
     * 批量提取所有文章数据
     */
    public function extractAllArticles(array $items): array
    {
        $allArticles = [];

        foreach ($items as $item) {
            $articles = $this->extractArticlesFromItem($item);
            $allArticles = array_merge($allArticles, $articles);
        }

        return $allArticles;
    }
}
