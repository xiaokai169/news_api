<?php

namespace App\DTO\Filter;

use App\DTO\Base\AbstractFilterDto;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * 新闻文章过滤器DTO
 *
 * 用于新闻文章查询过滤条件的数据传输对象
 * 包含新闻查询过滤的各种字段和验证约束
 */
#[OA\Schema(
    schema: 'NewsFilterDto',
    title: '新闻文章过滤器DTO',
    description: '新闻文章查询过滤条件的数据结构',
    required: []
)]
class NewsFilterDto extends AbstractFilterDto
{
    /**
     * 商户ID过滤
     */
    #[Assert\Type(type: 'integer', message: '商户ID必须是整数')]
    #[Assert\PositiveOrZero(message: '商户ID必须是非负整数')]
    #[OA\Property(
        description: '商户ID过滤',
        example: 1,
        minimum: 0
    )]
    #[Groups(['newsFilter:read', 'newsFilter:write'])]
    public ?int $merchantId = null;

    /**
     * 用户ID过滤
     */
    #[Assert\Type(type: 'integer', message: '用户ID必须是整数')]
    #[Assert\PositiveOrZero(message: '用户ID必须是非负整数')]
    #[OA\Property(
        description: '用户ID过滤',
        example: 1,
        minimum: 0
    )]
    #[Groups(['newsFilter:read', 'newsFilter:write'])]
    public ?int $userId = null;

    /**
     * 状态过滤（单个状态）
     */
    #[Assert\Choice(choices: [1, 2, 3], message: '状态值必须是1（激活）、2（非激活）或3（已删除）')]
    #[OA\Property(
        description: '状态过滤：1-激活，2-非激活，3-已删除',
        example: 1,
        enum: [1, 2, 3]
    )]
    #[Groups(['newsFilter:read', 'newsFilter:write'])]
    public ?int $newsStatus = null;

    /**
     * 是否推荐过滤
     */
    #[Assert\Type(type: 'bool', message: '是否推荐必须是布尔值')]
    #[OA\Property(
        description: '是否推荐过滤',
        example: true
    )]
    #[Groups(['newsFilter:read', 'newsFilter:write'])]
    public ?bool $isRecommend = null;

    /**
     * 分类代码过滤
     */
    #[Assert\Type(type: 'string', message: '分类代码必须是字符串')]
    #[Assert\Length(max: 50, maxMessage: '分类代码不能超过50个字符')]
    #[OA\Property(
        description: '分类代码过滤',
        example: 'tech',
        maxLength: 50
    )]
    #[Groups(['newsFilter:read', 'newsFilter:write'])]
    public ?string $categoryCode = null;

    /**
     * 文章名称模糊搜索
     */
    #[Assert\Type(type: 'string', message: '文章名称必须是字符串')]
    #[Assert\Length(max: 10, maxMessage: '文章名称搜索不能超过10个字符')]
    #[OA\Property(
        description: '文章名称模糊搜索',
        example: '科技',
        maxLength: 10
    )]
    #[Groups(['newsFilter:read', 'newsFilter:write'])]
    public ?string $name = null;

    /**
     * 用户名过滤
     */
    #[Assert\Type(type: 'string', message: '用户名必须是字符串')]
    #[Assert\Length(max: 100, maxMessage: '用户名不能超过100个字符')]
    #[OA\Property(
        description: '用户名过滤',
        example: 'admin',
        maxLength: 100
    )]
    #[Groups(['newsFilter:read', 'newsFilter:write'])]
    public ?string $userName = null;

    /**
     * 发布状态过滤（已发布、待发布等）
     */
    #[Assert\Type(type: 'string', message: '发布状态必须是字符串')]
    #[Assert\Choice(choices: ['published', 'scheduled', 'draft'], message: '发布状态必须是published、scheduled或draft')]
    #[OA\Property(
        description: '发布状态过滤：published-已发布，scheduled-定时发布，draft-草稿',
        example: 'published',
        enum: ['published', 'scheduled', 'draft']
    )]
    #[Groups(['newsFilter:read', 'newsFilter:write'])]
    public ?string $publishStatus = null;

    /**
     * 是否包含用户信息
     */
    #[Assert\Type(type: 'bool', message: '包含用户信息必须是布尔值')]
    #[OA\Property(
        description: '是否在结果中包含用户信息',
        example: true
    )]
    #[Groups(['newsFilter:read', 'newsFilter:write'])]
    public bool $includeUser = false;

    /**
     * 是否包含分类信息
     */
    #[Assert\Type(type: 'bool', message: '包含分类信息必须是布尔值')]
    #[OA\Property(
        description: '是否在结果中包含分类信息',
        example: true
    )]
    #[Groups(['newsFilter:read', 'newsFilter:write'])]
    public bool $includeCategory = false;

    /**
     * 发布时间范围开始
     */
    #[Assert\Type(type: 'string', message: '发布时间开始必须是字符串')]
    #[Assert\DateTime(message: '发布时间开始格式不正确')]
    #[OA\Property(
        description: '发布时间范围开始（格式：Y-m-d H:i:s）',
        example: '2024-01-01 00:00:00',
        format: 'date-time'
    )]
    #[Groups(['newsFilter:read', 'newsFilter:write'])]
    public ?string $releaseTimeFrom = null;

    /**
     * 发布时间范围结束
     */
    #[Assert\Type(type: 'string', message: '发布时间结束必须是字符串')]
    #[Assert\DateTime(message: '发布时间结束格式不正确')]
    #[OA\Property(
        description: '发布时间范围结束（格式：Y-m-d H:i:s）',
        example: '2024-12-31 23:59:59',
        format: 'date-time'
    )]
    #[Groups(['newsFilter:read', 'newsFilter:write'])]
    public ?string $releaseTimeTo = null;

    /**
     * 标签过滤
     */
    #[Assert\Type(type: 'array', message: '标签过滤必须是数组')]
    #[Assert\All([
        new Assert\Type(type: 'string', message: '标签必须是字符串'),
        new Assert\Length(max: 50, maxMessage: '标签不能超过50个字符')
    ])]
    #[OA\Property(
        description: '标签过滤列表',
        example: ['科技', '新闻', '热点']
    )]
    #[Groups(['newsFilter:read', 'newsFilter:write'])]
    public array $tags = [];

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
        // 设置父类属性
        parent::populateFromData($data);

        if (isset($data['merchantId'])) {
            $this->merchantId = $data['merchantId'] !== null ? (int) $data['merchantId'] : null;
        }

        if (isset($data['userId'])) {
            $this->userId = $data['userId'] !== null ? (int) $data['userId'] : null;
        }

        if (isset($data['status'])) {
            // 🔧 修复：空字符串应该被视为null，而不是转换为0
            if ($data['status'] !== null && $data['status'] !== '') {
                $this->newsStatus = (int) $data['status'];
            } else {
                $this->newsStatus = null;
            }
        }

        if (isset($data['isRecommend'])) {
            // 🔧 修复：空字符串应该被视为null，而不是转换为false
            if ($data['isRecommend'] !== null && $data['isRecommend'] !== '') {
                $this->isRecommend = (bool) $data['isRecommend'];
            } else {
                $this->isRecommend = null;
            }
        }

        if (isset($data['categoryCode'])) {
            // 🔧 修复：空字符串应该被视为null
            if ($data['categoryCode'] !== null && $data['categoryCode'] !== '') {
                $this->categoryCode = $this->cleanString($data['categoryCode']);
            } else {
                $this->categoryCode = null;
            }
        }

        if (isset($data['name'])) {
            // 🔧 修复：空字符串应该被视为null，避免无意义的LIKE查询
            if ($data['name'] !== null && $data['name'] !== '') {
                $this->name = $this->cleanString($data['name']);
            } else {
                $this->name = null;
            }
        }

        if (isset($data['userName'])) {
            $this->userName = $data['userName'] !== null ? $this->cleanString($data['userName']) : null;
        }

        if (isset($data['publishStatus'])) {
            $this->publishStatus = $data['publishStatus'] !== null ? $this->cleanString($data['publishStatus']) : null;
        }

        if (isset($data['includeUser'])) {
            $this->includeUser = (bool) $data['includeUser'];
        }

        if (isset($data['includeCategory'])) {
            $this->includeCategory = (bool) $data['includeCategory'];
        }

        if (isset($data['releaseTimeFrom'])) {
            $this->releaseTimeFrom = $data['releaseTimeFrom'] !== null ? $data['releaseTimeFrom'] : null;
        }

        if (isset($data['releaseTimeTo'])) {
            $this->releaseTimeTo = $data['releaseTimeTo'] !== null ? $data['releaseTimeTo'] : null;
        }

        if (isset($data['tags'])) {
            $this->tags = is_array($data['tags']) ? array_map('strval', $data['tags']) : [];
        }

        return $this;
    }

    /**
     * 获取发布时间开始DateTime对象
     *
     * @return \DateTimeInterface|null
     * @throws \Exception
     */
    public function getReleaseTimeFromDateTime(): ?\DateTimeInterface
    {
        return $this->releaseTimeFrom ? $this->parseDateTime($this->releaseTimeFrom) : null;
    }

    /**
     * 获取发布时间结束DateTime对象
     *
     * @return \DateTimeInterface|null
     * @throws \Exception
     */
    public function getReleaseTimeToDateTime(): ?\DateTimeInterface
    {
        return $this->releaseTimeTo ? $this->parseDateTime($this->releaseTimeTo) : null;
    }

    /**
     * 获取状态描述
     *
     * @return string|null
     */
    public function getStatusDescription(): ?string
    {
        if ($this->status === null) {
            return null;
        }

        return match($this->newsStatus) {
            1 => '已发布',
            2 => '待发布',
            3 => '已删除',
            default => '未知状态'
        };
    }

    /**
     * 获取发布状态描述
     *
     * @return string|null
     */
    public function getPublishStatusDescription(): ?string
    {
        if ($this->publishStatus === null) {
            return null;
        }

        return match($this->publishStatus) {
            'published' => '已发布',
            'scheduled' => '定时发布',
            'draft' => '草稿',
            default => '未知状态'
        };
    }

    /**
     * 检查是否有新闻特定的过滤条件
     *
     * @return bool
     */
    public function hasNewsSpecificFilters(): bool
    {
        return $this->merchantId !== null ||
               $this->userId !== null ||
               $this->newsStatus !== null ||
               $this->isRecommend !== null ||
               $this->categoryCode !== null ||
               $this->name !== null ||
               $this->userName !== null ||
               $this->publishStatus !== null ||
               $this->releaseTimeFrom !== null ||
               $this->releaseTimeTo !== null ||
               !empty($this->tags);
    }

    /**
     * 检查是否有时间相关的过滤条件
     *
     * @return bool
     */
    public function hasTimeFilters(): bool
    {
        return parent::hasDateRangeConditions() ||
               $this->releaseTimeFrom !== null ||
               $this->releaseTimeTo !== null;
    }

    /**
     * 获取过滤条件摘要
     *
     * @return array
     */
    public function getFilterSummary(): array
    {
        return array_merge(parent::getFilterSummary(), [
            'merchantId' => $this->merchantId,
            'userId' => $this->userId,
            'status' => $this->newsStatus,
            'statusDescription' => $this->getStatusDescription(),
            'isRecommend' => $this->isRecommend,
            'categoryCode' => $this->categoryCode,
            'name' => $this->name,
            'userName' => $this->userName,
            'publishStatus' => $this->publishStatus,
            'publishStatusDescription' => $this->getPublishStatusDescription(),
            'includeUser' => $this->includeUser,
            'includeCategory' => $this->includeCategory,
            'releaseTimeFrom' => $this->releaseTimeFrom,
            'releaseTimeTo' => $this->releaseTimeTo,
            'tags' => $this->tags,
            'hasNewsSpecificFilters' => $this->hasNewsSpecificFilters(),
            'hasTimeFilters' => $this->hasTimeFilters(),
        ]);
    }

    /**
     * 获取查询过滤条件（用于数据库查询）
     *
     * @return array
     */
    public function getFilterCriteria(): array
    {
        $criteria = [];


        // 基础过滤条件
        if ($this->merchantId !== null) {
            $criteria['merchantId'] = $this->merchantId;
        }

        if ($this->userId !== null) {
            $criteria['userId'] = $this->userId;
        }

        // 🔧 修复：优先使用子类的newsStatus，避免与父类status冲突
        if ($this->newsStatus !== null) {
            $criteria['status'] = $this->newsStatus;
        }

        if ($this->isRecommend !== null) {
            $criteria['isRecommend'] = $this->isRecommend;
        }

        if ($this->categoryCode !== null) {
            $criteria['categoryCode'] = $this->categoryCode;
        }

        // 模糊搜索条件
        if ($this->name !== null) {
            $criteria['name'] = ['like' => '%' . $this->name . '%'];
        }

        if ($this->userName !== null) {
            $criteria['userName'] = ['like' => '%' . $this->userName . '%'];
        }

        // 发布状态转换
        if ($this->publishStatus !== null) {
            switch ($this->publishStatus) {
                case 'published':
                    $criteria['status'] = 1;
                    break;
                case 'scheduled':
                    $criteria['status'] = 2;
                    break;
                case 'draft':
                    $criteria['status'] = 0; // 假设0表示草稿
                    break;
            }
        }

        // 时间范围条件
        if ($this->releaseTimeFrom !== null) {
            $criteria['releaseTime'] = $criteria['releaseTime'] ?? [];
            $criteria['releaseTime']['from'] = $this->releaseTimeFrom;
        }

        if ($this->releaseTimeTo !== null) {
            $criteria['releaseTime'] = $criteria['releaseTime'] ?? [];
            $criteria['releaseTime']['to'] = $this->releaseTimeTo;
        }

        // 标签过滤
        if (!empty($this->tags)) {
            $criteria['tags'] = $this->tags;
        }

        // 🔧 修复：获取父类条件但排除status字段，避免冲突
        $parentCriteria = parent::getFilterCriteria();

        // 移除父类的status条件，使用子类的newsStatus
        unset($parentCriteria['status']);

        $finalCriteria = array_merge($criteria, $parentCriteria);

        return $finalCriteria;
    }

    /**
     * 验证过滤条件
     *
     * @return array 验证错误数组
     */
    public function validateFilters(): array
    {
        $errors = parent::validateDateRanges();

        // 验证发布时间范围
        if ($this->releaseTimeFrom && $this->releaseTimeTo) {
            if (strtotime($this->releaseTimeFrom) > strtotime($this->releaseTimeTo)) {
                $errors['releaseTimeRange'] = '发布时间开始不能大于发布时间结束';
            }
        }

        // 验证状态和发布状态的一致性
        if ($this->newsStatus !== null && $this->publishStatus !== null) {
            $statusMapping = [
                'published' => 1,
                'scheduled' => 2,
                'draft' => 0
            ];

            if (isset($statusMapping[$this->publishStatus]) &&
                $this->newsStatus !== $statusMapping[$this->publishStatus]) {
                $errors['statusConflict'] = '状态和发布状态设置冲突';
            }
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
            'merchantId' => $this->merchantId,
            'userId' => $this->userId,
            'status' => $this->newsStatus,
            'statusDescription' => $this->getStatusDescription(),
            'isRecommend' => $this->isRecommend,
            'categoryCode' => $this->categoryCode,
            'name' => $this->name,
            'userName' => $this->userName,
            'publishStatus' => $this->publishStatus,
            'publishStatusDescription' => $this->getPublishStatusDescription(),
            'includeUser' => $this->includeUser,
            'includeCategory' => $this->includeCategory,
            'releaseTimeFrom' => $this->releaseTimeFrom,
            'releaseTimeTo' => $this->releaseTimeTo,
            'tags' => $this->tags,
            'hasNewsSpecificFilters' => $this->hasNewsSpecificFilters(),
            'hasTimeFilters' => $this->hasTimeFilters(),
            'filterCriteria' => $this->getFilterCriteria(),
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
        if (isset($data['page'])) {
            $dto->setPage($data['page']);
        }

        if (isset($data['limit'])) {
            $dto->setLimit($data['limit']);
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

        if (isset($data['searchFields'])) {
            $dto->setSearchFields($data['searchFields']);
        }

        if (isset($data['status']) && is_array($data['status'])) {
            $dto->setStatus($data['status']);
        }

        if (isset($data['dateFrom'])) {
            $dto->setDateFrom($data['dateFrom']);
        }

        if (isset($data['dateTo'])) {
            $dto->setDateTo($data['dateTo']);
        }

        if (isset($data['createdAtFrom'])) {
            $dto->setCreatedAtFrom($data['createdAtFrom']);
        }

        if (isset($data['createdAtTo'])) {
            $dto->setCreatedAtTo($data['createdAtTo']);
        }

        if (isset($data['updatedAtFrom'])) {
            $dto->setUpdatedAtFrom($data['updatedAtFrom']);
        }

        if (isset($data['updatedAtTo'])) {
            $dto->setUpdatedAtTo($data['updatedAtTo']);
        }

        if (isset($data['ids'])) {
            $dto->setIds($data['ids']);
        }

        if (isset($data['excludeIds'])) {
            $dto->setExcludeIds($data['excludeIds']);
        }

        if (isset($data['includeDeleted'])) {
            $dto->setIncludeDeleted($data['includeDeleted']);
        }

        if (isset($data['countOnly'])) {
            $dto->setCountOnly($data['countOnly']);
        }

        if (isset($data['customFilters'])) {
            $dto->setCustomFilters($data['customFilters']);
        }

        return $dto;
    }

    /**
     * 构建 Doctrine Criteria 对象
     * 使用 Doctrine Criteria API 来构建查询条件
     */
    public function buildCriteria(): Criteria
    {
        $criteria = Criteria::create();

        // 基础条件
        if ($this->merchantId !== null) {
            $criteria->andWhere(Criteria::expr()->eq('merchantId', $this->merchantId));
        }

        if ($this->userId !== null) {
            $criteria->andWhere(Criteria::expr()->eq('userId', $this->userId));
        }

        if ($this->newsStatus !== null) {
            $criteria->andWhere(Criteria::expr()->eq('status', $this->newsStatus));
        }

        if ($this->isRecommend !== null) {
            $criteria->andWhere(Criteria::expr()->eq('isRecommend', $this->isRecommend));
        }

        // 模糊搜索
        if ($this->name !== null) {
            $criteria->andWhere(Criteria::expr()->contains('name', $this->name));
        }

        if ($this->userName !== null) {
            // 注意：Criteria API 对关联查询支持有限，这里使用 QueryBuilder 更合适
            $criteria->andWhere(Criteria::expr()->contains('userName', $this->userName));
        }

        // 排序
        if ($this->sortBy) {
            $order = $this->sortDirection === 'desc' ? Criteria::DESC : Criteria::ASC;
            $criteria->orderBy([$this->sortBy => $order]);
        }

        // 分页
        if ($this->limit !== null) {
            $criteria->setMaxResults($this->limit);
            $criteria->setFirstResult(($this->page - 1) * $this->limit);
        }

        return $criteria;
    }

    /**
     * 构建 QueryBuilder 条件
     * 支持更复杂的查询条件，包括关联查询
     */
    public function buildQueryBuilder(QueryBuilder $qb, string $alias = 'article'): QueryBuilder
    {
        // 基础条件
        if ($this->merchantId !== null) {
            $qb->andWhere("{$alias}.merchantId = :merchantId")
               ->setParameter('merchantId', $this->merchantId);
        }

        if ($this->userId !== null) {
            $qb->andWhere("{$alias}.userId = :userId")
               ->setParameter('userId', $this->userId);
        }

        if ($this->newsStatus !== null) {
            $qb->andWhere("{$alias}.status = :status")
               ->setParameter('status', $this->newsStatus);
        }

        if ($this->isRecommend !== null) {
            $qb->andWhere("{$alias}.isRecommend = :isRecommend")
               ->setParameter('isRecommend', $this->isRecommend);
        }

        // 模糊搜索
        if ($this->name !== null) {
            $qb->andWhere("{$alias}.name LIKE :name")
               ->setParameter('name', '%' . $this->name . '%');
        }

        // 分类关联查询
        if ($this->categoryCode !== null) {
            $qb->leftJoin("{$alias}.category", 'category')
               ->andWhere("category.code = :categoryCode")
               ->setParameter('categoryCode', $this->categoryCode);
        }

        // 用户名搜索（需要关联用户表）
        if ($this->userName !== null) {
            $qb->leftJoin("{$alias}.user", 'user')
               ->andWhere("(user.username LIKE :userName OR user.nickname LIKE :userName)")
               ->setParameter('userName', '%' . $this->userName . '%');
        }

        // 时间范围查询
        if ($this->releaseTimeFrom !== null) {
            $qb->andWhere("{$alias}.releaseTime >= :releaseTimeFrom")
               ->setParameter('releaseTimeFrom', $this->getReleaseTimeFromDateTime());
        }

        if ($this->releaseTimeTo !== null) {
            $qb->andWhere("{$alias}.releaseTime <= :releaseTimeTo")
               ->setParameter('releaseTimeTo', $this->getReleaseTimeToDateTime());
        }

        // 排序
        if ($this->sortBy) {
            $order = $this->sortDirection === 'desc' ? 'DESC' : 'ASC';
            $qb->orderBy("{$alias}.{$this->sortBy}", $order);
        }

        // 分页
        if ($this->limit !== null) {
            $qb->setMaxResults($this->limit);
            $qb->setFirstResult(($this->page - 1) * $this->limit);
        }

        return $qb;
    }

    /**
     * 获取用于统计的 Criteria（不包含分页和排序）
     */
    public function buildCountCriteria(): Criteria
    {
        $criteria = Criteria::create();

        // 基础条件（不包含分页和排序）
        if ($this->merchantId !== null) {
            $criteria->andWhere(Criteria::expr()->eq('merchantId', $this->merchantId));
        }

        if ($this->userId !== null) {
            $criteria->andWhere(Criteria::expr()->eq('userId', $this->userId));
        }

        if ($this->newsStatus !== null) {
            $criteria->andWhere(Criteria::expr()->eq('status', $this->newsStatus));
        }

        if ($this->isRecommend !== null) {
            $criteria->andWhere(Criteria::expr()->eq('isRecommend', $this->isRecommend));
        }

        if ($this->name !== null) {
            $criteria->andWhere(Criteria::expr()->contains('name', $this->name));
        }

        return $criteria;
    }
}
