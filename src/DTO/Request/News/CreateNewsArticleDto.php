<?php

namespace App\DTO\Request\News;

use App\DTO\Base\AbstractRequestDto;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * 创建新闻文章请求DTO
 *
 * 用于创建新闻文章时的请求数据传输对象
 * 包含创建新闻文章所需的所有字段和验证约束
 */
#[OA\Schema(
    title: '创建新闻文章请求DTO',
    description: '创建新闻文章的请求数据结构',
    required: ['name', 'cover', 'content', 'categoryCode']
)]
class CreateNewsArticleDto extends AbstractRequestDto
{
    /**
     * 文章名称
     */
    #[Assert\NotBlank(message: '文章名称不能为空')]
    #[Assert\Length(max: 50, maxMessage: '文章名称不能超过50个字符')]
    #[OA\Property(
        description: '文章名称',
        maxLength: 50,
        example: '今日要闻'
    )]
    #[Groups(['createNewsArticle:read', 'createNewsArticle:write'])]
    public string $name = '';

    /**
     * 封面图URL
     */
    #[Assert\NotBlank(message: '封面图不能为空')]
    #[Assert\Url(message: '封面图必须是有效的URL')]
    #[OA\Property(
        description: '封面图URL',
        example: 'https://example.com/images/news-cover.jpg'
    )]
    #[Groups(['createNewsArticle:read', 'createNewsArticle:write'])]
    public string $cover = '';

    /**
     * 文章内容
     */
    #[Assert\NotBlank(message: '文章内容不能为空')]
    #[Assert\Length(max: 255, maxMessage: '文章内容不能超过255个字符')]
    #[OA\Property(
        description: '文章内容摘要',
        maxLength: 255,
        example: '这是一篇关于最新科技发展的新闻报道...'
    )]
    #[Groups(['createNewsArticle:read', 'createNewsArticle:write'])]
    public string $content = '';

    /**
     * 文章分类
     * @var array|null|int|string
     */
    #[Assert\Type(
        type: ['array', 'null', 'integer', 'string'],
        message: '分类字段必须是数组、整数、字符串或null'
    )]
    #[OA\Property(
        description: '文章分类对象（可以是数组、ID或代码）',
        example: ['id' => 1, 'name' => '科技新闻']
    )]
    #[Groups(['createNewsArticle:read', 'createNewsArticle:write'])]
    public $category = null;

    /**
     * 分类代码
     */
    #[Assert\NotBlank(message: '分类代码不能为空')]
    #[Assert\Length(max: 50, maxMessage: '分类代码不能超过50个字符')]
    #[OA\Property(
        description: '文章分类代码',
        maxLength: 50,
        example: 'tech'
    )]
    #[Groups(['createNewsArticle:read', 'createNewsArticle:write'])]
    public string $categoryCode = '';

    /**
     * 完美描述
     */
    #[Assert\Length(max: 255, maxMessage: '完美描述不能超过255个字符')]
    #[OA\Property(
        description: '完美描述',
        maxLength: 255,
        example: '这是一篇完美的新闻文章'
    )]
    #[Groups(['createNewsArticle:read', 'createNewsArticle:write'])]
    public string $perfect = '';

    /**
     * 文章状态
     */
    #[Assert\Choice(choices: [1, 2, 3], message: '状态值必须是1（激活）、2（非激活）或3（已删除）')]
    #[OA\Property(
        description: '文章状态：1-激活，2-非激活，3-已删除',
        enum: [1, 2, 3],
        example: 1
    )]
    #[Groups(['createNewsArticle:read', 'createNewsArticle:write'])]
    public int $status = 1;

    /**
     * 是否推荐
     */
    #[Assert\Type(type: 'bool', message: '是否推荐必须是布尔值')]
    #[OA\Property(
        description: '是否推荐',
        example: false
    )]
    #[Groups(['createNewsArticle:read', 'createNewsArticle:write'])]
    public bool $isRecommend = false;

    /**
     * 发布时间
     */
    #[Assert\DateTime(message: '发布时间格式不正确')]
    #[OA\Property(
        description: '发布时间（格式：Y-m-d H:i:s）',
        format: 'date-time',
        example: '2024-01-01 10:00:00'
    )]
    #[Groups(['createNewsArticle:read', 'createNewsArticle:write'])]
    public ?string $releaseTime = null;

    /**
     * 原文链接
     */
    // #[Assert\Url(message: '原文链接必须是有效的URL')] // 移除URL验证约束
    #[OA\Property(
        description: '原文链接',
        example: 'https://example.com/original-article'
    )]
    #[Groups(['createNewsArticle:read', 'createNewsArticle:write'])]
    public string $originalUrl = '';

    /**
     * 商户ID
     */
    #[Assert\PositiveOrZero(message: '商户ID必须是非负整数')]
    #[OA\Property(
        description: '商户ID',
        minimum: 0,
        example: 1
    )]
    #[Groups(['createNewsArticle:read', 'createNewsArticle:write'])]
    public int $merchantId = 0;

    /**
     * 用户ID
     */
    #[Assert\PositiveOrZero(message: '用户ID必须是非负整数')]
    #[OA\Property(
        description: '用户ID',
        minimum: 0,
        example: 1
    )]
    #[Groups(['createNewsArticle:read', 'createNewsArticle:write'])]
    public int $userId = 0;

    /**
     * 构造函数
     *
     * @param array $data 初始化数据
     */
    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    /**
     * 从数据填充DTO
     *
     * @param array $data
     * @return self
     */
    public function populateFromData(array $data): self
    {
        if (isset($data['name'])) {
            $this->name = $this->cleanString($data['name']);
        }

        if (isset($data['cover'])) {
            $this->cover = $this->cleanString($data['cover']);
        }

        if (isset($data['content'])) {
            $this->content = $this->cleanString($data['content']);
        }

        if (isset($data['category'])) {
            $this->category = $data['category'];
        }

        if (isset($data['categoryCode'])) {
            $this->categoryCode = $this->cleanString($data['categoryCode']);
        }

        if (isset($data['perfect'])) {
            $this->perfect = $this->cleanString($data['perfect']);
        }

        if (isset($data['status'])) {
            $this->status = (int) $data['status'];
        }

        if (isset($data['isRecommend'])) {
            $this->isRecommend = (bool) $data['isRecommend'];
        }

        if (isset($data['releaseTime'])) {
            $this->releaseTime = $data['releaseTime'];
        }

        if (isset($data['originalUrl'])) {
            $this->originalUrl = $this->cleanString($data['originalUrl']);
        }

        if (isset($data['merchantId'])) {
            $this->merchantId = (int) $data['merchantId'];
        }

        if (isset($data['userId'])) {
            $this->userId = (int) $data['userId'];
        }

        return $this;
    }

    /**
     * 获取格式化的发布时间
     *
     * @return \DateTimeInterface|null
     * @throws \Exception
     */
    public function getReleaseTimeDateTime(): ?\DateTimeInterface
    {
        return $this->parseDateTime($this->releaseTime);
    }

    /**
     * 设置发布时间为DateTime对象
     *
     * @param \DateTimeInterface|null $dateTime
     * @return self
     */
    public function setReleaseTimeDateTime(?\DateTimeInterface $dateTime): self
    {
        $this->releaseTime = $this->formatDateTime($dateTime);
        return $this;
    }

    /**
     * 检查是否为定时发布
     *
     * @return bool
     */
    public function isScheduledPublish(): bool
    {
        if (empty($this->releaseTime)) {
            return false;
        }

        try {
            $releaseTime = new \DateTime($this->releaseTime);
            return $releaseTime > new \DateTime();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取文章状态描述
     *
     * @return string
     */
    public function getStatusDescription(): string
    {
        return match($this->status) {
            1 => '已发布',
            2 => '待发布',
            3 => '已删除',
            default => '未知状态'
        };
    }

    /**
     * 验证业务逻辑
     *
     * @return array 验证错误数组
     */
    public function validateBusinessRules(): array
    {
        $errors = [];

        // 验证定时发布时间
        if ($this->releaseTime) {
            try {
                $releaseTime = new \DateTime($this->releaseTime);
                $now = new \DateTime();

                // 如果发布时间在过去且状态为待发布，给出警告
                if ($releaseTime < $now && $this->status === 2) {
                    $errors['releaseTime'] = '发布时间在过去，建议将状态设置为已发布';
                }

                // 如果发布时间在未来但状态为已发布，给出警告
                if ($releaseTime > $now && $this->status === 1) {
                    $errors['releaseTime'] = '发布时间在未来，建议将状态设置为待发布';
                }
            } catch (\Exception $e) {
                $errors['releaseTime'] = '发布时间格式不正确';
            }
        }

        // 验证URL格式 - 已移除originalUrl的URL验证
        // if (!empty($this->originalUrl) && !$this->validateUrl($this->originalUrl)) {
        //     $errors['originalUrl'] = '原文链接格式不正确';
        // }

        if (!empty($this->cover) && !$this->validateUrl($this->cover)) {
            $errors['cover'] = '封面图链接格式不正确';
        }

        return $errors;
    }

    /**
     * 转换为数组
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'name' => $this->name,
            'cover' => $this->cover,
            'content' => $this->content,
            'category' => $this->category,
            'categoryCode' => $this->categoryCode,
            'perfect' => $this->perfect,
            'status' => $this->status,
            'isRecommend' => $this->isRecommend,
            'releaseTime' => $this->releaseTime,
            'originalUrl' => $this->originalUrl,
            'merchantId' => $this->merchantId,
            'userId' => $this->userId,
            'statusDescription' => $this->getStatusDescription(),
            'isScheduledPublish' => $this->isScheduledPublish(),
        ]);
    }

    /**
     * 从数组创建实例
     *
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $dto = new static();
        $dto->populateFromData($data);

        // 设置父类属性
        if (isset($data['timestamp'])) {
            $dto->setTimestamp($data['timestamp']);
        }

        if (isset($data['requestId'])) {
            $dto->setRequestId($data['requestId']);
        }

        if (isset($data['clientVersion'])) {
            $dto->setClientVersion($data['clientVersion']);
        }

        if (isset($data['deviceInfo'])) {
            $dto->setDeviceInfo($data['deviceInfo']);
        }

        if (isset($data['userAgent'])) {
            $dto->setUserAgent($data['userAgent']);
        }

        if (isset($data['ipAddress'])) {
            $dto->setIpAddress($data['ipAddress']);
        }

        if (isset($data['source'])) {
            $dto->setSource($data['source']);
        }

        return $dto;
    }
}
