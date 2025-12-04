<?php

namespace App\DTO\Request\Wechat;

use App\DTO\Base\AbstractRequestDto;
use App\DTO\Request\Wechat\ArticleDataDto;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

/**
 * 同步微信文章请求DTO
 */
#[OA\Schema(
    schema: 'SyncArticlesDto',
    title: '同步微信文章请求',
    description: '用于同步微信文章的请求数据传输对象'
)]
class SyncArticlesDto extends AbstractRequestDto
{
    /**
     * 公众号ID
     */
    #[Assert\NotBlank(message: '公众号ID不能为空')]
    #[Assert\Type(type: 'string', message: '公众号ID必须是字符串')]
    #[Assert\Length(max: 100, maxMessage: '公众号ID不能超过100个字符')]
    #[OA\Property(
        description: '微信公众号ID',
        example: 'wx1234567890abcdef',
        maxLength: 100
    )]
    protected string $accountId = '';

    /**
     * 文章列表
     */
    #[Assert\NotBlank(message: '文章列表不能为空')]
    #[Assert\Type(type: 'array', message: '文章列表必须是数组')]
    #[Assert\Count(min: 1, minMessage: '至少需要一篇文章')]
    #[Assert\All([
        new Assert\Type(type: 'array', message: '文章数据必须是数组')
    ])]
    #[OA\Property(
        property: 'articles',
        description: '需要同步的文章列表',
        type: 'array',
        items: new OA\Items(
            properties: [
                'title' => new OA\Property(
                    property: 'title',
                    description: '文章标题',
                    type: 'string',
                    maxLength: 255,
                    example: '科技新闻：最新技术突破'
                ),
                'content' => new OA\Property(
                    property: 'content',
                    description: '文章内容，支持HTML格式',
                    type: 'string',
                    example: '<p>这是一篇关于最新技术突破的文章...</p>'
                ),
                'coverUrl' => new OA\Property(
                    property: 'coverUrl',
                    description: '文章封面图片URL',
                    type: 'string',
                    format: 'uri',
                    example: 'https://example.com/image.jpg'
                ),
                'summary' => new OA\Property(
                    property: 'summary',
                    description: '文章摘要或描述',
                    type: 'string',
                    maxLength: 500,
                    example: '本文介绍了最新的技术突破和发展趋势...'
                ),
                'originalUrl' => new OA\Property(
                    property: 'originalUrl',
                    description: '文章原文链接',
                    type: 'string',
                    format: 'uri',
                    example: 'https://mp.weixin.qq.com/s/xxxxx'
                ),
                'author' => new OA\Property(
                    property: 'author',
                    description: '文章作者',
                    type: 'string',
                    maxLength: 100,
                    example: '张三'
                ),
                'publishTime' => new OA\Property(
                    property: 'publishTime',
                    description: '文章发布时间',
                    type: 'string',
                    format: 'date-time',
                    example: '2024-01-15 10:30:00'
                ),
                'category' => new OA\Property(
                    property: 'category',
                    description: '文章分类',
                    type: 'string',
                    maxLength: 50,
                    example: '科技'
                ),
                'tags' => new OA\Property(
                    property: 'tags',
                    description: '文章标签列表',
                    type: 'array',
                    items: new OA\Items(type: 'string', example: '技术')
                ),
                'status' => new OA\Property(
                    property: 'status',
                    description: '文章状态',
                    type: 'string',
                    enum: ['draft', 'published', 'archived'],
                    example: 'published'
                ),
                'source' => new OA\Property(
                    property: 'source',
                    description: '文章来源',
                    type: 'string',
                    maxLength: 100,
                    example: '微信公众号'
                ),
                'readCount' => new OA\Property(
                    property: 'readCount',
                    description: '文章阅读量',
                    type: 'integer',
                    minimum: 0,
                    example: 1000
                ),
                'likeCount' => new OA\Property(
                    property: 'likeCount',
                    description: '文章点赞数',
                    type: 'integer',
                    minimum: 0,
                    example: 50
                )
            ],
            type: 'object'
        ),
        minItems: 1
    )]
    protected array $articles = [];

    /**
     * 同步类型
     */
    #[Assert\Choice(choices: ['full', 'incremental', 'manual'], message: '同步类型必须是full、incremental或manual')]
    #[OA\Property(
        description: '同步类型：full-全量同步，incremental-增量同步，manual-手动同步',
        enum: ['full', 'incremental', 'manual'],
        example: 'incremental'
    )]
    protected string $syncType = 'incremental';

    /**
     * 是否强制同步
     */
    #[Assert\Type(type: 'bool', message: '强制同步必须是布尔值')]
    #[OA\Property(
        description: '是否强制同步（覆盖现有数据）',
        example: false
    )]
    protected bool $forceSync = false;

    /**
     * 同步开始时间
     */
    #[Assert\DateTime(message: '同步开始时间格式不正确')]
    #[OA\Property(
        description: '同步开始时间（用于增量同步）',
        example: '2024-01-01 00:00:00',
        format: 'date-time'
    )]
    protected ?string $syncStartTime = null;

    /**
     * 同步结束时间
     */
    #[Assert\DateTime(message: '同步结束时间格式不正确')]
    #[OA\Property(
        description: '同步结束时间（用于增量同步）',
        example: '2024-01-31 23:59:59',
        format: 'date-time'
    )]
    protected ?string $syncEndTime = null;

    /**
     * 是否自动发布
     */
    #[Assert\Type(type: 'bool', message: '自动发布必须是布尔值')]
    #[OA\Property(
        description: '同步后是否自动发布文章',
        example: true
    )]
    protected bool $autoPublish = true;

    /**
     * 默认分类ID
     */
    #[Assert\Type(type: 'integer', message: '默认分类ID必须是整数')]
    #[Assert\Positive(message: '默认分类ID必须大于0')]
    #[OA\Property(
        description: '默认文章分类ID',
        example: 1,
        minimum: 1
    )]
    protected ?int $defaultCategoryId = null;

    /**
     * 构造函数
     *
     * @param array $data 初始化数据
     */
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        if (!empty($data)) {
            $this->populateFromData($data);
        }
    }

    /**
     * 获取公众号ID
     *
     * @return string
     */
    public function getAccountId(): string
    {
        return $this->accountId;
    }

    /**
     * 设置公众号ID
     *
     * @param string $publicAccountId
     * @return self
     */
    public function setAccountId(string $accountId): self
    {
        $this->accountId = $this->cleanString($accountId);
        return $this;
    }

    /**
     * 获取文章列表
     *
     * @return array
     */
    public function getArticles(): array
    {
        return $this->articles;
    }

    /**
     * 设置文章列表
     *
     * @param array $articles
     * @return self
     */
    public function setArticles(array $articles): self
    {
        $this->articles = [];
        foreach ($articles as $articleData) {
            if (is_array($articleData)) {
                $this->articles[] = new ArticleDataDto($articleData);
            }
        }
        return $this;
    }

    /**
     * 添加文章
     *
     * @param array|ArticleDataDto $article
     * @return self
     */
    public function addArticle($article): self
    {
        if ($article instanceof ArticleDataDto) {
            $this->articles[] = $article;
        } elseif (is_array($article)) {
            $this->articles[] = new ArticleDataDto($article);
        }
        return $this;
    }

    /**
     * 获取同步类型
     *
     * @return string
     */
    public function getSyncType(): string
    {
        return $this->syncType;
    }

    /**
     * 设置同步类型
     *
     * @param string $syncType
     * @return self
     */
    public function setSyncType(string $syncType): self
    {
        $this->syncType = in_array($syncType, ['full', 'incremental', 'manual']) ? $syncType : 'incremental';
        return $this;
    }

    /**
     * 是否强制同步
     *
     * @return bool
     */
    public function isForceSync(): bool
    {
        return $this->forceSync;
    }

    /**
     * 设置强制同步
     *
     * @param bool $forceSync
     * @return self
     */
    public function setForceSync(bool $forceSync): self
    {
        $this->forceSync = $forceSync;
        return $this;
    }

    /**
     * 获取同步开始时间
     *
     * @return string|null
     */
    public function getSyncStartTime(): ?string
    {
        return $this->syncStartTime;
    }

    /**
     * 设置同步开始时间
     *
     * @param string|null $syncStartTime
     * @return self
     */
    public function setSyncStartTime(?string $syncStartTime): self
    {
        $this->syncStartTime = $this->cleanString($syncStartTime);
        return $this;
    }

    /**
     * 获取同步结束时间
     *
     * @return string|null
     */
    public function getSyncEndTime(): ?string
    {
        return $this->syncEndTime;
    }

    /**
     * 设置同步结束时间
     *
     * @param string|null $syncEndTime
     * @return self
     */
    public function setSyncEndTime(?string $syncEndTime): self
    {
        $this->syncEndTime = $this->cleanString($syncEndTime);
        return $this;
    }

    /**
     * 是否自动发布
     *
     * @return bool
     */
    public function isAutoPublish(): bool
    {
        return $this->autoPublish;
    }

    /**
     * 设置自动发布
     *
     * @param bool $autoPublish
     * @return self
     */
    public function setAutoPublish(bool $autoPublish): self
    {
        $this->autoPublish = $autoPublish;
        return $this;
    }

    /**
     * 获取默认分类ID
     *
     * @return int|null
     */
    public function getDefaultCategoryId(): ?int
    {
        return $this->defaultCategoryId;
    }

    /**
     * 设置默认分类ID
     *
     * @param int|null $defaultCategoryId
     * @return self
     */
    public function setDefaultCategoryId(?int $defaultCategoryId): self
    {
        $this->defaultCategoryId = $defaultCategoryId;
        return $this;
    }

    /**
     * 从数据数组填充属性
     *
     * @param array $data
     * @return self
     */
    public function populateFromData(array $data): self
    {
        if (isset($data['publicAccountId'])) {
            $this->setAccountId($data['publicAccountId']);
        }
        if (isset($data['accountId'])) {
            $this->setAccountId($data['accountId']);
        }

        if (isset($data['articles']) && is_array($data['articles'])) {
            $this->setArticles($data['articles']);
        }

        if (isset($data['syncType'])) {
            $this->setSyncType($data['syncType']);
        }

        if (isset($data['forceSync'])) {
            $this->setForceSync((bool)$data['forceSync']);
        }

        if (isset($data['syncStartTime'])) {
            $this->setSyncStartTime($data['syncStartTime']);
        }

        if (isset($data['syncEndTime'])) {
            $this->setSyncEndTime($data['syncEndTime']);
        }

        if (isset($data['autoPublish'])) {
            $this->setAutoPublish((bool)$data['autoPublish']);
        }

        if (isset($data['defaultCategoryId'])) {
            $this->setDefaultCategoryId($data['defaultCategoryId']);
        }

        return $this;
    }

    /**
     * 转换为数组
     *
     * @return array
     */
    public function toArray(): array
    {
        $articlesArray = [];
        foreach ($this->articles as $article) {
            $articlesArray[] = $article->toArray();
        }

        return array_merge(parent::toArray(), [
            'publicAccountId' => $this->accountId,
            'articles' => $articlesArray,
            'syncType' => $this->syncType,
            'forceSync' => $this->forceSync,
            'syncStartTime' => $this->syncStartTime,
            'syncEndTime' => $this->syncEndTime,
            'autoPublish' => $this->autoPublish,
            'defaultCategoryId' => $this->defaultCategoryId,
        ]);
    }

    /**
     * 验证同步数据
     *
     * @return array 验证错误数组
     */
    public function validateSyncData(): array
    {
        $errors = [];

        // 验证公众号ID
        if (empty($this->accountId)) {
            $errors['publicAccountId'] = '公众号ID不能为空';
        } elseif (strlen($this->accountId) > 100) {
            $errors['publicAccountId'] = '公众号ID不能超过100个字符';
        }

        // 验证文章列表
        if (empty($this->articles)) {
            $errors['articles'] = '文章列表不能为空';
        } else {
            foreach ($this->articles as $index => $article) {
                if ($article instanceof ArticleDataDto) {
                    $articleErrors = $article->validateArticleData();
                    if (!empty($articleErrors)) {
                        $errors['articles'][$index] = $articleErrors;
                    }
                }
            }
        }

        // 验证时间范围
        if ($this->syncStartTime && $this->syncEndTime) {
            $startTime = strtotime($this->syncStartTime);
            $endTime = strtotime($this->syncEndTime);
            if ($startTime && $endTime && $startTime > $endTime) {
                $errors['timeRange'] = '同步开始时间不能大于结束时间';
            }
        }

        return array_merge($errors, $this->validateRequest());
    }

    /**
     * 检查是否有有效的同步数据
     *
     * @return bool
     */
    public function hasValidSyncData(): bool
    {
        if (empty($this->accountId) || empty($this->articles)) {
            return false;
        }

        foreach ($this->articles as $article) {
            if ($article instanceof ArticleDataDto && $article->hasValidArticleData()) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取有效文章数量
     *
     * @return int
     */
    public function getValidArticleCount(): int
    {
        $count = 0;
        foreach ($this->articles as $article) {
            if ($article instanceof ArticleDataDto && $article->hasValidArticleData()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * 获取同步摘要信息
     *
     * @return array
     */
    public function getSyncSummary(): array
    {
        return [
            'publicAccountId' => $this->accountId,
            'syncType' => $this->syncType,
            'forceSync' => $this->forceSync,
            'autoPublish' => $this->autoPublish,
            'totalArticles' => count($this->articles),
            'validArticles' => $this->getValidArticleCount(),
            'hasValidData' => $this->hasValidSyncData(),
            'timeRange' => [
                'start' => $this->syncStartTime,
                'end' => $this->syncEndTime,
            ],
            'defaultCategoryId' => $this->defaultCategoryId,
            'requestSummary' => $this->getRequestSummary(),
        ];
    }
}
