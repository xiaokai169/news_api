<?php

namespace App\DTO\Filter;

use App\DTO\Base\AbstractFilterDto;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * 文章阅读统计过滤器DTO
 *
 * 用于文章阅读统计查询过滤条件的数据传输对象
 * 包含阅读统计查询过滤的各种字段和验证约束
 */
#[OA\Schema(
    schema: 'ArticleReadFilterDto',
    title: '文章阅读统计过滤器DTO',
    description: '文章阅读统计查询过滤条件的数据结构',
    required: []
)]
class ArticleReadFilterDto extends AbstractFilterDto
{
    /**
     * 文章ID过滤
     */
    #[Assert\Type(type: 'integer', message: '文章ID必须是整数')]
    #[Assert\Positive(message: '文章ID必须是正整数')]
    #[OA\Property(
        description: '文章ID过滤',
        example: 1,
        minimum: 1
    )]
    #[Groups(['articleReadFilter:read', 'articleReadFilter:write'])]
    public ?int $articleId = null;

    /**
     * 用户ID过滤
     */
    #[Assert\Type(type: 'integer', message: '用户ID必须是整数')]
    #[Assert\PositiveOrZero(message: '用户ID必须是非负整数')]
    #[OA\Property(
        description: '用户ID过滤，0表示匿名用户',
        example: 1,
        minimum: 0
    )]
    #[Groups(['articleReadFilter:read', 'articleReadFilter:write'])]
    public ?int $userId = null;

    /**
     * 设备类型过滤
     */
    #[Assert\Choice(choices: ['desktop', 'mobile', 'tablet', 'unknown'], message: '设备类型必须是desktop、mobile、tablet或unknown')]
    #[OA\Property(
        description: '设备类型过滤',
        example: 'mobile',
        enum: ['desktop', 'mobile', 'tablet', 'unknown']
    )]
    #[Groups(['articleReadFilter:read', 'articleReadFilter:write'])]
    public ?string $deviceType = null;

    /**
     * 是否完成阅读过滤
     */
    #[Assert\Type(type: 'bool', message: '是否完成阅读必须是布尔值')]
    #[OA\Property(
        description: '是否完成阅读过滤',
        example: true
    )]
    #[Groups(['articleReadFilter:read', 'articleReadFilter:write'])]
    public ?bool $isCompleted = null;

    /**
     * 最小阅读时长过滤（秒）
     */
    #[Assert\Type(type: 'integer', message: '最小阅读时长必须是整数')]
    #[Assert\PositiveOrZero(message: '最小阅读时长必须是非负整数')]
    #[OA\Property(
        description: '最小阅读时长过滤（秒）',
        example: 30,
        minimum: 0
    )]
    #[Groups(['articleReadFilter:read', 'articleReadFilter:write'])]
    public ?int $minDurationSeconds = null;

    /**
     * 最大阅读时长过滤（秒）
     */
    #[Assert\Type(type: 'integer', message: '最大阅读时长必须是整数')]
    #[Assert\Positive(message: '最大阅读时长必须是正整数')]
    #[OA\Property(
        description: '最大阅读时长过滤（秒）',
        example: 3600,
        minimum: 1
    )]
    #[Groups(['articleReadFilter:read', 'articleReadFilter:write'])]
    public ?int $maxDurationSeconds = null;

    /**
     * 阅读时间范围开始
     */
    #[Assert\Type(type: 'string', message: '阅读时间开始必须是字符串')]
    #[Assert\DateTime(message: '阅读时间开始格式不正确')]
    #[OA\Property(
        description: '阅读时间范围开始（格式：Y-m-d H:i:s）',
        example: '2024-01-01 00:00:00',
        format: 'date-time'
    )]
    #[Groups(['articleReadFilter:read', 'articleReadFilter:write'])]
    public ?string $readTimeFrom = null;

    /**
     * 阅读时间范围结束
     */
    #[Assert\Type(type: 'string', message: '阅读时间结束必须是字符串')]
    #[Assert\DateTime(message: '阅读时间结束格式不正确')]
    #[OA\Property(
        description: '阅读时间范围结束（格式：Y-m-d H:i:s）',
        example: '2024-12-31 23:59:59',
        format: 'date-time'
    )]
    #[Groups(['articleReadFilter:read', 'articleReadFilter:write'])]
    public ?string $readTimeTo = null;

    /**
     * 统计类型
     */
    #[Assert\Choice(choices: ['daily', 'weekly', 'monthly', 'overall'], message: '统计类型必须是daily、weekly、monthly或overall')]
    #[OA\Property(
        description: '统计类型：daily-按天，weekly-按周，monthly-按月，overall-总体',
        example: 'daily',
        enum: ['daily', 'weekly', 'monthly', 'overall']
    )]
    #[Groups(['articleReadFilter:read', 'articleReadFilter:write'])]
    public string $statType = 'daily';

    /**
     * 是否包含匿名用户
     */
    #[Assert\Type(type: 'bool', message: '包含匿名用户必须是布尔值')]
    #[OA\Property(
        description: '是否包含匿名用户的阅读记录',
        example: true
    )]
    #[Groups(['articleReadFilter:read', 'articleReadFilter:write'])]
    public bool $includeAnonymous = true;

    /**
     * 是否包含注册用户
     */
    #[Assert\Type(type: 'bool', message: '包含注册用户必须是布尔值')]
    #[OA\Property(
        description: '是否包含注册用户的阅读记录',
        example: true
    )]
    #[Groups(['articleReadFilter:read', 'articleReadFilter:write'])]
    public bool $includeRegistered = true;

    /**
     * 是否推荐文章过滤
     */
    #[Assert\Type(type: 'bool', message: '是否推荐必须是布尔值')]
    #[OA\Property(
        description: '是否只查询推荐文章',
        example: false
    )]
    #[Groups(['articleReadFilter:read', 'articleReadFilter:write'])]
    public ?bool $isRecommend = null;

    /**
     * 排序字段
     */
    #[Assert\Choice(choices: ['readTime', 'durationSeconds', 'totalReads', 'uniqueUsers', 'completionRate'], message: '排序字段无效')]
    #[OA\Property(
        description: '排序字段',
        example: 'readTime',
        enum: ['readTime', 'durationSeconds', 'totalReads', 'uniqueUsers', 'completionRate']
    )]
    #[Groups(['articleReadFilter:read', 'articleReadFilter:write'])]
    public ?string $sortBy = 'readTime';

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
     */
    public function populateFromData(array $data): self
    {
        // 设置父类属性
        parent::populateFromData($data);

        if (isset($data['articleId'])) {
            $this->articleId = $data['articleId'] !== null ? (int) $data['articleId'] : null;
        }

        if (isset($data['userId'])) {
            $this->userId = $data['userId'] !== null ? (int) $data['userId'] : null;
        }

        if (isset($data['deviceType'])) {
            $this->deviceType = $data['deviceType'] !== null ? $this->cleanString($data['deviceType']) : null;
        }

        if (isset($data['isCompleted'])) {
            $this->isCompleted = $data['isCompleted'] !== null ? (bool) $data['isCompleted'] : null;
        }

        if (isset($data['minDurationSeconds'])) {
            $this->minDurationSeconds = $data['minDurationSeconds'] !== null ? (int) $data['minDurationSeconds'] : null;
        }

        if (isset($data['maxDurationSeconds'])) {
            $this->maxDurationSeconds = $data['maxDurationSeconds'] !== null ? (int) $data['maxDurationSeconds'] : null;
        }

        if (isset($data['readTimeFrom'])) {
            $this->readTimeFrom = $data['readTimeFrom'] !== null ? $data['readTimeFrom'] : null;
        }

        if (isset($data['readTimeTo'])) {
            $this->readTimeTo = $data['readTimeTo'] !== null ? $data['readTimeTo'] : null;
        }

        if (isset($data['statType'])) {
            $this->statType = $this->cleanString($data['statType']);
        }

        if (isset($data['includeAnonymous'])) {
            $this->includeAnonymous = (bool) $data['includeAnonymous'];
        }

        if (isset($data['includeRegistered'])) {
            $this->includeRegistered = (bool) $data['includeRegistered'];
        }

        if (isset($data['isRecommend'])) {
            $this->isRecommend = $data['isRecommend'] !== null ? (bool) $data['isRecommend'] : null;
        }

        if (isset($data['sortBy'])) {
            $this->sortBy = $this->cleanString($data['sortBy']);
        }

        return $this;
    }

    /**
     * 获取阅读时间开始DateTime对象
     */
    public function getReadTimeFromDateTime(): ?\DateTimeInterface
    {
        return $this->readTimeFrom ? $this->parseDateTime($this->readTimeFrom) : null;
    }

    /**
     * 获取阅读时间结束DateTime对象
     */
    public function getReadTimeToDateTime(): ?\DateTimeInterface
    {
        return $this->readTimeTo ? $this->parseDateTime($this->readTimeTo) : null;
    }

    /**
     * 获取统计类型描述
     */
    public function getStatTypeDescription(): string
    {
        return match($this->statType) {
            'daily' => '按天统计',
            'weekly' => '按周统计',
            'monthly' => '按月统计',
            'overall' => '总体统计',
            default => '未知统计类型'
        };
    }

    /**
     * 检查是否有阅读特定的过滤条件
     */
    public function hasReadSpecificFilters(): bool
    {
        return $this->articleId !== null ||
               $this->userId !== null ||
               $this->deviceType !== null ||
               $this->isCompleted !== null ||
               $this->minDurationSeconds !== null ||
               $this->maxDurationSeconds !== null ||
               $this->readTimeFrom !== null ||
               $this->readTimeTo !== null ||
               $this->isRecommend !== null;
    }

    /**
     * 检查是否有时间相关的过滤条件
     */
    public function hasTimeFilters(): bool
    {
        return parent::hasDateRangeConditions() ||
               $this->readTimeFrom !== null ||
               $this->readTimeTo !== null;
    }

    /**
     * 验证过滤条件
     */
    public function validateFilters(): array
    {
        $errors = parent::validateDateRanges();

        // 验证阅读时长范围
        if ($this->minDurationSeconds !== null && $this->maxDurationSeconds !== null) {
            if ($this->minDurationSeconds >= $this->maxDurationSeconds) {
                $errors['durationRange'] = '最小阅读时长必须小于最大阅读时长';
            }
        }

        // 验证阅读时间范围
        if ($this->readTimeFrom && $this->readTimeTo) {
            if (strtotime($this->readTimeFrom) > strtotime($this->readTimeTo)) {
                $errors['readTimeRange'] = '阅读时间开始不能大于阅读时间结束';
            }
        }

        // 验证用户类型过滤
        if (!$this->includeAnonymous && !$this->includeRegistered) {
            $errors['userType'] = '至少需要包含匿名用户或注册用户中的一种';
        }

        return $errors;
    }

    /**
     * 获取过滤条件摘要
     */
    public function getFilterSummary(): array
    {
        return array_merge(parent::getFilterSummary(), [
            'articleId' => $this->articleId,
            'userId' => $this->userId,
            'deviceType' => $this->deviceType,
            'isCompleted' => $this->isCompleted,
            'minDurationSeconds' => $this->minDurationSeconds,
            'maxDurationSeconds' => $this->maxDurationSeconds,
            'readTimeFrom' => $this->readTimeFrom,
            'readTimeTo' => $this->readTimeTo,
            'statType' => $this->statType,
            'statTypeDescription' => $this->getStatTypeDescription(),
            'includeAnonymous' => $this->includeAnonymous,
            'includeRegistered' => $this->includeRegistered,
            'isRecommend' => $this->isRecommend,
            'sortBy' => $this->sortBy,
            'hasReadSpecificFilters' => $this->hasReadSpecificFilters(),
            'hasTimeFilters' => $this->hasTimeFilters(),
        ]);
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'articleId' => $this->articleId,
            'userId' => $this->userId,
            'deviceType' => $this->deviceType,
            'isCompleted' => $this->isCompleted,
            'minDurationSeconds' => $this->minDurationSeconds,
            'maxDurationSeconds' => $this->maxDurationSeconds,
            'readTimeFrom' => $this->readTimeFrom,
            'readTimeTo' => $this->readTimeTo,
            'statType' => $this->statType,
            'statTypeDescription' => $this->getStatTypeDescription(),
            'includeAnonymous' => $this->includeAnonymous,
            'includeRegistered' => $this->includeRegistered,
            'isRecommend' => $this->isRecommend,
            'sortBy' => $this->sortBy,
            'hasReadSpecificFilters' => $this->hasReadSpecificFilters(),
            'hasTimeFilters' => $this->hasTimeFilters(),
            'filterCriteria' => $this->getFilterCriteria(),
        ]);
    }

    /**
     * 从数组创建实例
     */
    public static function fromArray(array $data): static
    {
        $dto = new static();
        $dto->populateFromData($data);

        // 设置父类属性 - 支持新旧参数名
        if (isset($data['page'])) {
            $dto->setPage($data['page']);
        } elseif (isset($data['current'])) {
            $dto->setPage($data['current']);
        }

        if (isset($data['size'])) {
            $dto->setSize($data['size']);
        } elseif (isset($data['pageSize'])) {
            $dto->setSize($data['pageSize']);
        } elseif (isset($data['limit'])) {
            $dto->setSize($data['limit']);
        }

        if (isset($data['sortBy'])) {
            $dto->setSortBy($data['sortBy']);
        }

        if (isset($data['sortDirection'])) {
            $dto->setSortDirection($data['sortDirection']);
        }

        if (isset($data['keyword'])) {
            $dto->setKeyword($data['keyword']);
        }

        if (isset($data['dateFrom'])) {
            $dto->setDateFrom($data['dateFrom']);
        }

        if (isset($data['dateTo'])) {
            $dto->setDateTo($data['dateTo']);
        }

        return $dto;
    }
}
