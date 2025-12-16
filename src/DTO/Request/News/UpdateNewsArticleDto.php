<?php

namespace App\DTO\Request\News;

use App\DTO\Base\AbstractRequestDto;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * 更新新闻文章请求DTO
 *
 * 用于更新新闻文章时的请求数据传输对象
 * 包含更新新闻文章的字段（大部分字段可选）
 */
#[OA\Schema(
    title: '更新新闻文章请求DTO',
    description: '更新新闻文章的请求数据结构',
    required: [] // 所有字段都是可选的
)]
class UpdateNewsArticleDto extends AbstractRequestDto
{
    /**
     * 文章名称
     */
    #[Assert\Length(max: 50, maxMessage: '文章名称不能超过50个字符')]
    #[OA\Property(
        description: '文章名称',
        example: '今日要闻更新版',
        maxLength: 50
    )]
    #[Groups(['updateNewsArticle:read', 'updateNewsArticle:write'])]
    public ?string $name = null;

    /**
     * 封面图URL
     */
    #[Assert\Url(message: '封面图必须是有效的URL')]
    #[OA\Property(
        description: '封面图URL',
        example: 'https://example.com/images/news-cover-updated.jpg'
    )]
    #[Groups(['updateNewsArticle:read', 'updateNewsArticle:write'])]
    public ?string $cover = null;

    /**
     * 文章内容
     */
    #[Assert\Length(max: 255, maxMessage: '文章内容不能超过255个字符')]
    #[OA\Property(
        description: '文章内容摘要',
        example: '这是一篇更新后的关于最新科技发展的新闻报道...',
        maxLength: 255
    )]
    #[Groups(['updateNewsArticle:read', 'updateNewsArticle:write'])]
    public ?string $content = null;

    /**
     * 文章分类
     * @var array|null
     */
    #[OA\Property(
        description: '文章分类对象',
        example: ['id' => 2, 'name' => '财经新闻']
    )]
    #[Groups(['updateNewsArticle:read', 'updateNewsArticle:write'])]
    public ?array $category = null;

    /**
     * 分类代码
     */
    #[Assert\Length(max: 50, maxMessage: '分类代码不能超过50个字符')]
    #[OA\Property(
        description: '文章分类代码',
        example: 'finance',
        maxLength: 50
    )]
    #[Groups(['updateNewsArticle:read', 'updateNewsArticle:write'])]
    public ?string $categoryCode = null;

    /**
     * 完美描述
     */
    #[Assert\Length(max: 255, maxMessage: '完美描述不能超过255个字符')]
    #[OA\Property(
        description: '完美描述',
        example: '这是一篇更新后的完美新闻文章',
        maxLength: 255
    )]
    #[Groups(['updateNewsArticle:read', 'updateNewsArticle:write'])]
    public ?string $perfect = null;

    /**
     * 文章状态
     */
    #[Assert\Choice(choices: [1, 2, 3], message: '状态值必须是1（激活）、2（非激活）或3（已删除）')]
    #[OA\Property(
        description: '文章状态：1-激活，2-非激活，3-已删除',
        example: 2,
        enum: [1, 2, 3]
    )]
    #[Groups(['updateNewsArticle:read', 'updateNewsArticle:write'])]
    public ?int $status = null;

    /**
     * 是否推荐
     */
    #[Assert\Type(type: 'bool', message: '是否推荐必须是布尔值')]
    #[OA\Property(
        description: '是否推荐',
        example: true
    )]
    #[Groups(['updateNewsArticle:read', 'updateNewsArticle:write'])]
    public ?bool $isRecommend = null;

    /**
     * 发布时间
     */
    #[Assert\DateTime(message: '发布时间格式不正确')]
    #[OA\Property(
        description: '发布时间（格式：Y-m-d H:i:s）',
        example: '2024-01-02 10:00:00',
        format: 'date-time'
    )]
    #[Groups(['updateNewsArticle:read', 'updateNewsArticle:write'])]
    public ?string $releaseTime = null;

    /**
     * 原文链接
     */
    // #[Assert\Url(message: '原文链接必须是有效的URL')] // 移除URL验证约束
    #[OA\Property(
        description: '原文链接',
        example: 'https://example.com/updated-original-article'
    )]
    #[Groups(['updateNewsArticle:read', 'updateNewsArticle:write'])]
    public ?string $originalUrl = null;

    /**
     * 商户ID
     */
    #[Assert\PositiveOrZero(message: '商户ID必须是非负整数')]
    #[OA\Property(
        description: '商户ID',
        example: 2,
        minimum: 0
    )]
    #[Groups(['updateNewsArticle:read', 'updateNewsArticle:write'])]
    public ?int $merchantId = null;

    /**
     * 用户ID
     */
    #[Assert\PositiveOrZero(message: '用户ID必须是非负整数')]
    #[OA\Property(
        description: '用户ID',
        example: 2,
        minimum: 0
    )]
    #[Groups(['updateNewsArticle:read', 'updateNewsArticle:write'])]
    public ?int $userId = null;

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
            $this->name = $data['name'] !== null ? $this->cleanString($data['name']) : null;
        }

        if (isset($data['cover'])) {
            $this->cover = $data['cover'] !== null ? $this->cleanString($data['cover']) : null;
        }

        if (isset($data['content'])) {
            $this->content = $data['content'] !== null ? $this->cleanString($data['content']) : null;
        }

        if (isset($data['category'])) {
            $this->category = $data['category'];
        }

        if (isset($data['categoryCode'])) {
            $this->categoryCode = $data['categoryCode'] !== null ? $this->cleanString($data['categoryCode']) : null;
        }

        if (isset($data['perfect'])) {
            $this->perfect = $data['perfect'] !== null ? $this->cleanString($data['perfect']) : null;
        }

        if (isset($data['status'])) {
            $this->status = $data['status'] !== null ? (int) $data['status'] : null;
        }

        if (isset($data['isRecommend'])) {
            $this->isRecommend = $data['isRecommend'] !== null ? (bool) $data['isRecommend'] : null;
        }

        if (isset($data['releaseTime'])) {
            $this->releaseTime = $data['releaseTime'] !== null ? $data['releaseTime'] : null;
        }

        if (isset($data['originalUrl'])) {
            $this->originalUrl = $data['originalUrl'] !== null ? $this->cleanString($data['originalUrl']) : null;
        }

        if (isset($data['merchantId'])) {
            $this->merchantId = $data['merchantId'] !== null ? (int) $data['merchantId'] : null;
        }

        if (isset($data['userId'])) {
            $this->userId = $data['userId'] !== null ? (int) $data['userId'] : null;
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
        return $this->releaseTime ? $this->parseDateTime($this->releaseTime) : null;
    }

    /**
     * 设置发布时间为DateTime对象
     *
     * @param \DateTimeInterface|null $dateTime
     * @return self
     */
    public function setReleaseTimeDateTime(?\DateTimeInterface $dateTime): self
    {
        $this->releaseTime = $dateTime ? $this->formatDateTime($dateTime) : null;
        return $this;
    }

    /**
     * 检查是否为定时发布
     *
     * @return bool|null
     */
    public function isScheduledPublish(): ?bool
    {
        if (empty($this->releaseTime)) {
            return null;
        }

        try {
            $releaseTime = new \DateTime($this->releaseTime);
            return $releaseTime > new \DateTime();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 获取文章状态描述
     *
     * @return string|null
     */
    public function getStatusDescription(): ?string
    {
        if ($this->status === null) {
            return null;
        }

        return match($this->status) {
            1 => '已发布',
            2 => '待发布',
            3 => '已删除',
            default => '未知状态'
        };
    }

    /**
     * 检查是否有任何字段被更新
     *
     * @return bool
     */
    public function hasUpdates(): bool
    {
        $fields = [
            'name', 'cover', 'content', 'category', 'categoryCode',
            'perfect', 'status', 'isRecommend', 'releaseTime',
            'originalUrl', 'merchantId', 'userId'
        ];

        foreach ($fields as $field) {
            if ($this->$field !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取已更新的字段列表
     *
     * @return array
     */
    public function getUpdatedFields(): array
    {
        $updatedFields = [];
        $fields = [
            'name', 'cover', 'content', 'category', 'categoryCode',
            'perfect', 'status', 'isRecommend', 'releaseTime',
            'originalUrl', 'merchantId', 'userId'
        ];

        foreach ($fields as $field) {
            if ($this->$field !== null) {
                $updatedFields[$field] = $this->$field;
            }
        }

        return $updatedFields;
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
        if ($this->releaseTime !== null) {
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
        // if ($this->originalUrl !== null && !$this->validateUrl($this->originalUrl)) {
        //     $errors['originalUrl'] = '原文链接格式不正确';
        // }

        if ($this->cover !== null && !$this->validateUrl($this->cover)) {
            $errors['cover'] = '封面图链接格式不正确';
        }

        // 检查是否有任何更新
        if (!$this->hasUpdates()) {
            $errors['noUpdates'] = '没有提供任何要更新的字段';
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
        $data = parent::toArray();

        // 只包含非null的字段
        $fields = [
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
        ];

        foreach ($fields as $key => $value) {
            if ($value !== null) {
                $data[$key] = $value;
            }
        }

        // 添加计算字段
        if ($this->status !== null) {
            $data['statusDescription'] = $this->getStatusDescription();
        }

        $scheduledPublish = $this->isScheduledPublish();
        if ($scheduledPublish !== null) {
            $data['isScheduledPublish'] = $scheduledPublish;
        }

        $data['hasUpdates'] = $this->hasUpdates();
        $data['updatedFields'] = array_keys($this->getUpdatedFields());

        return $data;
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
