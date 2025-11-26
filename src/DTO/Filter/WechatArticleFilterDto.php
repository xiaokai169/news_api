<?php

namespace App\DTO\Filter;

use App\DTO\Base\AbstractFilterDto;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

/**
 * 微信文章过滤器DTO
 */
#[OA\Schema(
    schema: 'WechatArticleFilterDto',
    title: '微信文章过滤器',
    description: '用于查询微信文章的过滤条件传输对象'
)]
class WechatArticleFilterDto extends AbstractFilterDto
{
    /**
     * 公众号ID
     */
    #[Assert\Type(type: 'string', message: '公众号ID必须是字符串')]
    #[Assert\Length(max: 100, maxMessage: '公众号ID不能超过100个字符')]
    #[OA\Property(
        description: '微信公众号ID',
        example: 'wx1234567890abcdef',
        maxLength: 100
    )]
    protected ?string $publicAccountId = null;

    /**
     * 文章标题
     */
    #[Assert\Type(type: 'string', message: '文章标题必须是字符串')]
    #[Assert\Length(max: 255, maxMessage: '文章标题不能超过255个字符')]
    #[OA\Property(
        description: '文章标题（支持模糊搜索）',
        example: '科技新闻',
        maxLength: 255
    )]
    protected ?string $title = null;

    /**
     * 文章作者
     */
    #[Assert\Type(type: 'string', message: '文章作者必须是字符串')]
    #[Assert\Length(max: 100, maxMessage: '文章作者不能超过100个字符')]
    #[OA\Property(
        description: '文章作者',
        example: '张三',
        maxLength: 100
    )]
    protected ?string $author = null;

    /**
     * 文章分类
     */
    #[Assert\Type(type: 'string', message: '文章分类必须是字符串')]
    #[Assert\Length(max: 50, maxMessage: '文章分类不能超过50个字符')]
    #[OA\Property(
        description: '文章分类',
        example: '科技',
        maxLength: 50
    )]
    protected ?string $category = null;

    /**
     * 发布时间范围开始
     */
    #[Assert\Type(type: 'string', message: '发布时间开始必须是字符串')]
    #[Assert\DateTime(message: '发布时间开始格式不正确')]
    #[OA\Property(
        description: '发布时间范围开始',
        example: '2024-01-01 00:00:00',
        format: 'date-time'
    )]
    protected ?string $publishTimeFrom = null;

    /**
     * 发布时间范围结束
     */
    #[Assert\Type(type: 'string', message: '发布时间结束必须是字符串')]
    #[Assert\DateTime(message: '发布时间结束格式不正确')]
    #[OA\Property(
        description: '发布时间范围结束',
        example: '2024-12-31 23:59:59',
        format: 'date-time'
    )]
    protected ?string $publishTimeTo = null;

    /**
     * 文章状态
     */
    #[Assert\Type(type: 'array', message: '文章状态必须是数组')]
    #[Assert\All([
        new Assert\Type(type: 'string', message: '状态值必须是字符串'),
        new Assert\Choice(choices: ['draft', 'published', 'archived'], message: '状态值必须是draft、published或archived')
    ])]
    #[OA\Property(
        description: '文章状态列表',
        type: 'array',
        items: new OA\Items(type: 'string', enum: ['draft', 'published', 'archived'])
    )]
    protected array $status = [];

    /**
     * 文章来源
     */
    #[Assert\Type(type: 'string', message: '文章来源必须是字符串')]
    #[Assert\Length(max: 100, maxMessage: '文章来源不能超过100个字符')]
    #[OA\Property(
        description: '文章来源',
        example: '微信公众号',
        maxLength: 100
    )]
    protected ?string $source = null;

    /**
     * 文章标签
     */
    #[Assert\Type(type: 'array', message: '文章标签必须是数组')]
    #[Assert\All([
        new Assert\Type(type: 'string', message: '标签必须是字符串'),
        new Assert\Length(max: 50, maxMessage: '单个标签不能超过50个字符')
    ])]
    #[OA\Property(
        description: '文章标签列表（匹配任一标签）',
        type: 'array',
        items: new OA\Items(type: 'string', example: '技术')
    )]
    protected array $tags = [];

    /**
     * 最小阅读量
     */
    #[Assert\Type(type: 'integer', message: '最小阅读量必须是整数')]
    #[Assert\PositiveOrZero(message: '最小阅读量不能为负数')]
    #[OA\Property(
        description: '最小阅读量',
        example: 100,
        minimum: 0
    )]
    protected ?int $minReadCount = null;

    /**
     * 最大阅读量
     */
    #[Assert\Type(type: 'integer', message: '最大阅读量必须是整数')]
    #[Assert\PositiveOrZero(message: '最大阅读量不能为负数')]
    #[OA\Property(
        description: '最大阅读量',
        example: 10000,
        minimum: 0
    )]
    protected ?int $maxReadCount = null;

    /**
     * 最小点赞数
     */
    #[Assert\Type(type: 'integer', message: '最小点赞数必须是整数')]
    #[Assert\PositiveOrZero(message: '最小点赞数不能为负数')]
    #[OA\Property(
        description: '最小点赞数',
        example: 10,
        minimum: 0
    )]
    protected ?int $minLikeCount = null;

    /**
     * 最大点赞数
     */
    #[Assert\Type(type: 'integer', message: '最大点赞数必须是整数')]
    #[Assert\PositiveOrZero(message: '最大点赞数不能为负数')]
    #[OA\Property(
        description: '最大点赞数',
        example: 1000,
        minimum: 0
    )]
    protected ?int $maxLikeCount = null;

    /**
     * 是否有封面图
     */
    #[Assert\Type(type: 'bool', message: '是否有封面图必须是布尔值')]
    #[OA\Property(
        description: '是否只查询有封面图的文章',
        example: true
    )]
    protected ?bool $hasCover = null;

    /**
     * 是否有原文链接
     */
    #[Assert\Type(type: 'bool', message: '是否有原文链接必须是布尔值')]
    #[OA\Property(
        description: '是否只查询有原文链接的文章',
        example: false
    )]
    protected ?bool $hasOriginalUrl = null;

    /**
     * 内容长度范围
     */
    #[Assert\Type(type: 'array', message: '内容长度范围必须是数组')]
    #[Assert\Count(min: 0, max: 2, minMessage: '内容长度范围最多包含2个值', maxMessage: '内容长度范围最多包含2个值')]
    #[OA\Property(
        description: '内容长度范围 [最小值, 最大值]',
        type: 'array',
        items: new OA\Items(type: 'integer'),
        minItems: 0,
        maxItems: 2
    )]
    protected array $contentLengthRange = [];

    /**
     * 排除的公众号ID列表
     */
    #[Assert\Type(type: 'array', message: '排除的公众号ID列表必须是数组')]
    #[Assert\All([
        new Assert\Type(type: 'string', message: '公众号ID必须是字符串'),
        new Assert\Length(max: 100, maxMessage: '公众号ID不能超过100个字符')
    ])]
    #[OA\Property(
        description: '排除的公众号ID列表',
        type: 'array',
        items: new OA\Items(type: 'string')
    )]
    protected array $excludePublicAccountIds = [];

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
     * @return string|null
     */
    public function getPublicAccountId(): ?string
    {
        return $this->publicAccountId;
    }

    /**
     * 设置公众号ID
     *
     * @param string|null $publicAccountId
     * @return self
     */
    public function setPublicAccountId(?string $publicAccountId): self
    {
        $this->publicAccountId = $this->cleanString($publicAccountId);
        return $this;
    }

    /**
     * 获取文章标题
     *
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * 设置文章标题
     *
     * @param string|null $title
     * @return self
     */
    public function setTitle(?string $title): self
    {
        $this->title = $this->cleanString($title);
        return $this;
    }

    /**
     * 获取文章作者
     *
     * @return string|null
     */
    public function getAuthor(): ?string
    {
        return $this->author;
    }

    /**
     * 设置文章作者
     *
     * @param string|null $author
     * @return self
     */
    public function setAuthor(?string $author): self
    {
        $this->author = $this->cleanString($author);
        return $this;
    }

    /**
     * 获取文章分类
     *
     * @return string|null
     */
    public function getCategory(): ?string
    {
        return $this->category;
    }

    /**
     * 设置文章分类
     *
     * @param string|null $category
     * @return self
     */
    public function setCategory(?string $category): self
    {
        $this->category = $this->cleanString($category);
        return $this;
    }

    /**
     * 获取发布时间开始
     *
     * @return string|null
     */
    public function getPublishTimeFrom(): ?string
    {
        return $this->publishTimeFrom;
    }

    /**
     * 设置发布时间开始
     *
     * @param string|null $publishTimeFrom
     * @return self
     */
    public function setPublishTimeFrom(?string $publishTimeFrom): self
    {
        $this->publishTimeFrom = $this->cleanString($publishTimeFrom);
        return $this;
    }

    /**
     * 获取发布时间结束
     *
     * @return string|null
     */
    public function getPublishTimeTo(): ?string
    {
        return $this->publishTimeTo;
    }

    /**
     * 设置发布时间结束
     *
     * @param string|null $publishTimeTo
     * @return self
     */
    public function setPublishTimeTo(?string $publishTimeTo): self
    {
        $this->publishTimeTo = $this->cleanString($publishTimeTo);
        return $this;
    }

    /**
     * 获取文章状态
     *
     * @return array
     */
    public function getStatus(): array
    {
        return $this->status;
    }

    /**
     * 设置文章状态
     *
     * @param array $status
     * @return self
     */
    public function setStatus(array $status): self
    {
        $this->status = array_filter($status, function($value) {
            return is_string($value) && in_array($value, ['draft', 'published', 'archived']);
        });
        return $this;
    }

    /**
     * 获取文章来源
     *
     * @return string|null
     */
    public function getSource(): ?string
    {
        return $this->source;
    }

    /**
     * 设置文章来源
     *
     * @param string|null $source
     * @return self
     */
    public function setSource(?string $source): self
    {
        $this->source = $this->cleanString($source);
        return $this;
    }

    /**
     * 获取文章标签
     *
     * @return array
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * 设置文章标签
     *
     * @param array $tags
     * @return self
     */
    public function setTags(array $tags): self
    {
        $this->tags = array_filter($tags, 'is_string');
        return $this;
    }

    /**
     * 获取最小阅读量
     *
     * @return int|null
     */
    public function getMinReadCount(): ?int
    {
        return $this->minReadCount;
    }

    /**
     * 设置最小阅读量
     *
     * @param int|null $minReadCount
     * @return self
     */
    public function setMinReadCount(?int $minReadCount): self
    {
        $this->minReadCount = $minReadCount !== null ? max(0, $minReadCount) : null;
        return $this;
    }

    /**
     * 获取最大阅读量
     *
     * @return int|null
     */
    public function getMaxReadCount(): ?int
    {
        return $this->maxReadCount;
    }

    /**
     * 设置最大阅读量
     *
     * @param int|null $maxReadCount
     * @return self
     */
    public function setMaxReadCount(?int $maxReadCount): self
    {
        $this->maxReadCount = $maxReadCount !== null ? max(0, $maxReadCount) : null;
        return $this;
    }

    /**
     * 获取最小点赞数
     *
     * @return int|null
     */
    public function getMinLikeCount(): ?int
    {
        return $this->minLikeCount;
    }

    /**
     * 设置最小点赞数
     *
     * @param int|null $minLikeCount
     * @return self
     */
    public function setMinLikeCount(?int $minLikeCount): self
    {
        $this->minLikeCount = $minLikeCount !== null ? max(0, $minLikeCount) : null;
        return $this;
    }

    /**
     * 获取最大点赞数
     *
     * @return int|null
     */
    public function getMaxLikeCount(): ?int
    {
        return $this->maxLikeCount;
    }

    /**
     * 设置最大点赞数
     *
     * @param int|null $maxLikeCount
     * @return self
     */
    public function setMaxLikeCount(?int $maxLikeCount): self
    {
        $this->maxLikeCount = $maxLikeCount !== null ? max(0, $maxLikeCount) : null;
        return $this;
    }

    /**
     * 是否有封面图
     *
     * @return bool|null
     */
    public function getHasCover(): ?bool
    {
        return $this->hasCover;
    }

    /**
     * 设置是否有封面图
     *
     * @param bool|null $hasCover
     * @return self
     */
    public function setHasCover(?bool $hasCover): self
    {
        $this->hasCover = $hasCover;
        return $this;
    }

    /**
     * 是否有原文链接
     *
     * @return bool|null
     */
    public function getHasOriginalUrl(): ?bool
    {
        return $this->hasOriginalUrl;
    }

    /**
     * 设置是否有原文链接
     *
     * @param bool|null $hasOriginalUrl
     * @return self
     */
    public function setHasOriginalUrl(?bool $hasOriginalUrl): self
    {
        $this->hasOriginalUrl = $hasOriginalUrl;
        return $this;
    }

    /**
     * 获取内容长度范围
     *
     * @return array
     */
    public function getContentLengthRange(): array
    {
        return $this->contentLengthRange;
    }

    /**
     * 设置内容长度范围
     *
     * @param array $contentLengthRange
     * @return self
     */
    public function setContentLengthRange(array $contentLengthRange): self
    {
        if (count($contentLengthRange) === 2 &&
            is_int($contentLengthRange[0]) &&
            is_int($contentLengthRange[1]) &&
            $contentLengthRange[0] >= 0 &&
            $contentLengthRange[1] >= $contentLengthRange[0]) {
            $this->contentLengthRange = $contentLengthRange;
        }
        return $this;
    }

    /**
     * 获取排除的公众号ID列表
     *
     * @return array
     */
    public function getExcludePublicAccountIds(): array
    {
        return $this->excludePublicAccountIds;
    }

    /**
     * 设置排除的公众号ID列表
     *
     * @param array $excludePublicAccountIds
     * @return self
     */
    public function setExcludePublicAccountIds(array $excludePublicAccountIds): self
    {
        $this->excludePublicAccountIds = array_filter($excludePublicAccountIds, 'is_string');
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
            $this->setPublicAccountId($data['publicAccountId']);
        }

        if (isset($data['title'])) {
            $this->setTitle($data['title']);
        }

        if (isset($data['author'])) {
            $this->setAuthor($data['author']);
        }

        if (isset($data['category'])) {
            $this->setCategory($data['category']);
        }

        if (isset($data['publishTimeFrom'])) {
            $this->setPublishTimeFrom($data['publishTimeFrom']);
        }

        if (isset($data['publishTimeTo'])) {
            $this->setPublishTimeTo($data['publishTimeTo']);
        }

        if (isset($data['status']) && is_array($data['status'])) {
            $this->setStatus($data['status']);
        }

        if (isset($data['source'])) {
            $this->setSource($data['source']);
        }

        if (isset($data['tags']) && is_array($data['tags'])) {
            $this->setTags($data['tags']);
        }

        if (isset($data['minReadCount'])) {
            $this->setMinReadCount($data['minReadCount']);
        }

        if (isset($data['maxReadCount'])) {
            $this->setMaxReadCount($data['maxReadCount']);
        }

        if (isset($data['minLikeCount'])) {
            $this->setMinLikeCount($data['minLikeCount']);
        }

        if (isset($data['maxLikeCount'])) {
            $this->setMaxLikeCount($data['maxLikeCount']);
        }

        if (isset($data['hasCover'])) {
            $this->setHasCover($data['hasCover']);
        }

        if (isset($data['hasOriginalUrl'])) {
            $this->setHasOriginalUrl($data['hasOriginalUrl']);
        }

        if (isset($data['contentLengthRange']) && is_array($data['contentLengthRange'])) {
            $this->setContentLengthRange($data['contentLengthRange']);
        }

        if (isset($data['excludePublicAccountIds']) && is_array($data['excludePublicAccountIds'])) {
            $this->setExcludePublicAccountIds($data['excludePublicAccountIds']);
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
        return array_merge(parent::toArray(), [
            'publicAccountId' => $this->publicAccountId,
            'title' => $this->title,
            'author' => $this->author,
            'category' => $this->category,
            'publishTimeFrom' => $this->publishTimeFrom,
            'publishTimeTo' => $this->publishTimeTo,
            'status' => $this->status,
            'source' => $this->source,
            'tags' => $this->tags,
            'minReadCount' => $this->minReadCount,
            'maxReadCount' => $this->maxReadCount,
            'minLikeCount' => $this->minLikeCount,
            'maxLikeCount' => $this->maxLikeCount,
            'hasCover' => $this->hasCover,
            'hasOriginalUrl' => $this->hasOriginalUrl,
            'contentLengthRange' => $this->contentLengthRange,
            'excludePublicAccountIds' => $this->excludePublicAccountIds,
        ]);
    }

    /**
     * 获取过滤条件
     *
     * @return array
     */
    public function getFilterCriteria(): array
    {
        $criteria = [];

        // 基础过滤条件
        if ($this->publicAccountId) {
            $criteria['publicAccountId'] = $this->publicAccountId;
        }

        if ($this->title) {
            $criteria['title'] = $this->title;
        }

        if ($this->author) {
            $criteria['author'] = $this->author;
        }

        if ($this->category) {
            $criteria['category'] = $this->category;
        }

        // 时间范围过滤
        if ($this->publishTimeFrom) {
            $criteria['publishTimeFrom'] = $this->publishTimeFrom;
        }

        if ($this->publishTimeTo) {
            $criteria['publishTimeTo'] = $this->publishTimeTo;
        }

        // 状态过滤
        if (!empty($this->status)) {
            $criteria['status'] = $this->status;
        }

        if ($this->source) {
            $criteria['source'] = $this->source;
        }

        // 标签过滤
        if (!empty($this->tags)) {
            $criteria['tags'] = $this->tags;
        }

        // 数值范围过滤
        if ($this->minReadCount !== null) {
            $criteria['readCount']['min'] = $this->minReadCount;
        }

        if ($this->maxReadCount !== null) {
            $criteria['readCount']['max'] = $this->maxReadCount;
        }

        if ($this->minLikeCount !== null) {
            $criteria['likeCount']['min'] = $this->minLikeCount;
        }

        if ($this->maxLikeCount !== null) {
            $criteria['likeCount']['max'] = $this->maxLikeCount;
        }

        // 布尔过滤
        if ($this->hasCover !== null) {
            $criteria['hasCover'] = $this->hasCover;
        }

        if ($this->hasOriginalUrl !== null) {
            $criteria['hasOriginalUrl'] = $this->hasOriginalUrl;
        }

        // 内容长度范围
        if (!empty($this->contentLengthRange)) {
            $criteria['contentLength'] = [
                'min' => $this->contentLengthRange[0],
                'max' => $this->contentLengthRange[1]
            ];
        }

        // 排除条件
        if (!empty($this->excludePublicAccountIds)) {
            $criteria['excludePublicAccountIds'] = $this->excludePublicAccountIds;
        }

        return $criteria;
    }

    /**
     * 验证过滤条件
     *
     * @return array 验证错误数组
     */
    public function validateFilterData(): array
    {
        $errors = [];

        // 验证时间范围
        if ($this->publishTimeFrom && $this->publishTimeTo) {
            $startTime = strtotime($this->publishTimeFrom);
            $endTime = strtotime($this->publishTimeTo);
            if ($startTime && $endTime && $startTime > $endTime) {
                $errors['publishTimeRange'] = '发布时间开始不能大于结束时间';
            }
        }

        // 验证阅读量范围
        if ($this->minReadCount !== null && $this->maxReadCount !== null &&
            $this->minReadCount > $this->maxReadCount) {
            $errors['readCountRange'] = '最小阅读量不能大于最大阅读量';
        }

        // 验证点赞数范围
        if ($this->minLikeCount !== null && $this->maxLikeCount !== null &&
            $this->minLikeCount > $this->maxLikeCount) {
            $errors['likeCountRange'] = '最小点赞数不能大于最大点赞数';
        }

        // 验证内容长度范围
        if (!empty($this->contentLengthRange)) {
            if (count($this->contentLengthRange) !== 2) {
                $errors['contentLengthRange'] = '内容长度范围必须包含最小值和最大值';
            } elseif ($this->contentLengthRange[0] < 0 || $this->contentLengthRange[1] < 0) {
                $errors['contentLengthRange'] = '内容长度不能为负数';
            } elseif ($this->contentLengthRange[0] > $this->contentLengthRange[1]) {
                $errors['contentLengthRange'] = '最小内容长度不能大于最大内容长度';
            }
        }

        return array_merge($errors, $this->validateDateRanges());
    }

    /**
     * 检查是否有有效的过滤条件
     *
     * @return bool
     */
    public function hasValidFilterConditions(): bool
    {
        return $this->publicAccountId !== null ||
               $this->title !== null ||
               $this->author !== null ||
               $this->category !== null ||
               $this->publishTimeFrom !== null ||
               $this->publishTimeTo !== null ||
               !empty($this->status) ||
               $this->source !== null ||
               !empty($this->tags) ||
               $this->minReadCount !== null ||
               $this->maxReadCount !== null ||
               $this->minLikeCount !== null ||
               $this->maxLikeCount !== null ||
               $this->hasCover !== null ||
               $this->hasOriginalUrl !== null ||
               !empty($this->contentLengthRange) ||
               !empty($this->excludePublicAccountIds);
    }

    /**
     * 获取过滤摘要信息
     *
     * @return array
     */
    public function getFilterSummary(): array
    {
        return array_merge(parent::getFilterSummary(), [
            'wechatFilters' => [
                'publicAccountId' => $this->publicAccountId,
                'title' => $this->title,
                'author' => $this->author,
                'category' => $this->category,
                'status' => $this->status,
                'source' => $this->source,
                'tags' => $this->tags,
                'hasCover' => $this->hasCover,
                'hasOriginalUrl' => $this->hasOriginalUrl,
            ],
            'numericRanges' => [
                'readCount' => ['min' => $this->minReadCount, 'max' => $this->maxReadCount],
                'likeCount' => ['min' => $this->minLikeCount, 'max' => $this->maxLikeCount],
                'contentLength' => $this->contentLengthRange,
            ],
            'timeRanges' => [
                'publishTime' => ['from' => $this->publishTimeFrom, 'to' => $this->publishTimeTo],
            ],
            'exclusions' => [
                'excludePublicAccountIds' => $this->excludePublicAccountIds,
            ],
            'hasValidConditions' => $this->hasValidFilterConditions(),
        ]);
    }

}
