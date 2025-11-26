<?php

namespace App\DTO\Base;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;
use OpenApi\Attributes as OA;
use App\DTO\Shared\PaginationDto;
use App\DTO\Shared\SortDto;

/**
 * 抽象过滤器DTO基类
 * 用于处理查询过滤条件的传输对象
 */
abstract class AbstractFilterDto extends AbstractDto
{
    /**
     * 页码
     */
    #[Assert\Type(type: 'integer', message: '页码必须是整数')]
    #[Assert\Positive(message: '页码必须大于0')]
    #[Groups(['filter:read', 'filter:write'])]
    protected int $page = 1;

    /**
     * 每页数量
     */
    #[Assert\Type(type: 'integer', message: '每页数量必须是整数')]
    #[Assert\Positive(message: '每页数量必须大于0')]
    #[Assert\LessThanOrEqual(value: 100, message: '每页数量不能超过100')]
    #[Groups(['filter:read', 'filter:write'])]
    protected int $limit = 20;

    /**
     * 排序字段
     */
    #[Assert\Type(type: 'string', message: '排序字段必须是字符串')]
    #[Assert\Length(max: 100, maxMessage: '排序字段不能超过100个字符')]
    #[Groups(['filter:read', 'filter:write'])]
    protected ?string $sortBy = null;

    /**
     * 排序方向
     */
    #[Assert\Type(type: 'string', message: '排序方向必须是字符串')]
    #[Assert\Choice(choices: ['asc', 'desc', 'ASC', 'DESC'], message: '排序方向必须是asc或desc')]
    #[Groups(['filter:read', 'filter:write'])]
    protected string $sortDirection = 'desc';

    /**
     * 搜索关键词
     */
    #[Assert\Type(type: 'string', message: '搜索关键词必须是字符串')]
    #[Assert\Length(max: 255, maxMessage: '搜索关键词不能超过255个字符')]
    #[Groups(['filter:read', 'filter:write'])]
    protected ?string $keyword = null;

    /**
     * 搜索字段
     */
    #[Assert\Type(type: 'array', message: '搜索字段必须是数组')]
    #[Groups(['filter:read', 'filter:write'])]
    protected array $searchFields = [];

    /**
     * 状态过滤
     */
    #[Assert\Type(type: 'array', message: '状态过滤必须是数组')]
    #[Groups(['filter:read', 'filter:write'])]
    protected array $status = [];

    /**
     * 日期范围开始
     */
    #[Assert\Type(type: 'string', message: '开始日期必须是字符串')]
    #[Assert\Date(message: '开始日期格式不正确')]
    #[Groups(['filter:read', 'filter:write'])]
    protected ?string $dateFrom = null;

    /**
     * 日期范围结束
     */
    #[Assert\Type(type: 'string', message: '结束日期必须是字符串')]
    #[Assert\Date(message: '结束日期格式不正确')]
    #[Groups(['filter:read', 'filter:write'])]
    protected ?string $dateTo = null;

    /**
     * 创建时间范围开始
     */
    #[Assert\Type(type: 'string', message: '创建时间开始必须是字符串')]
    #[Assert\Date(message: '创建时间开始格式不正确')]
    #[Groups(['filter:read', 'filter:write'])]
    protected ?string $createdAtFrom = null;

    /**
     * 创建时间范围结束
     */
    #[Assert\Type(type: 'string', message: '创建时间结束必须是字符串')]
    #[Assert\Date(message: '创建时间结束格式不正确')]
    #[Groups(['filter:read', 'filter:write'])]
    protected ?string $createdAtTo = null;

    /**
     * 更新时间范围开始
     */
    #[Assert\Type(type: 'string', message: '更新时间开始必须是字符串')]
    #[Assert\Date(message: '更新时间开始格式不正确')]
    #[Groups(['filter:read', 'filter:write'])]
    protected ?string $updatedAtFrom = null;

    /**
     * 更新时间范围结束
     */
    #[Assert\Type(type: 'string', message: '更新时间结束必须是字符串')]
    #[Assert\Date(message: '更新时间结束格式不正确')]
    #[Groups(['filter:read', 'filter:write'])]
    protected ?string $updatedAtTo = null;

    /**
     * ID列表
     */
    #[Assert\Type(type: 'array', message: 'ID列表必须是数组')]
    #[Assert\All([
        new Assert\Type(type: 'integer', message: 'ID必须是整数'),
        new Assert\Positive(message: 'ID必须大于0')
    ])]
    #[Groups(['filter:read', 'filter:write'])]
    protected array $ids = [];

    /**
     * 排除的ID列表
     */
    #[Assert\Type(type: 'array', message: '排除ID列表必须是数组')]
    #[Assert\All([
        new Assert\Type(type: 'integer', message: '排除ID必须是整数'),
        new Assert\Positive(message: '排除ID必须大于0')
    ])]
    #[Groups(['filter:read', 'filter:write'])]
    protected array $excludeIds = [];

    /**
     * 是否包含已删除数据
     */
    #[Assert\Type(type: 'bool', message: '包含已删除数据必须是布尔值')]
    #[Groups(['filter:read', 'filter:write'])]
    protected bool $includeDeleted = false;

    /**
     * 是否仅获取总数
     */
    #[Assert\Type(type: 'bool', message: '仅获取总数必须是布尔值')]
    #[Groups(['filter:read', 'filter:write'])]
    protected bool $countOnly = false;

    /**
     * 自定义过滤条件
     */
    #[Assert\Type(type: 'array', message: '自定义过滤条件必须是数组')]
    #[Groups(['filter:read', 'filter:write'])]
    protected array $customFilters = [];

    /**
     * 分页信息（用于API文档生成）
     */
    #[OA\Property(ref: '#/components/schemas/PaginationDto')]
    protected ?PaginationDto $pagination = null;

    /**
     * 排序信息（用于API文档生成）
     */
    #[OA\Property(ref: '#/components/schemas/SortDto')]
    protected ?SortDto $sort = null;

    /**
     * 构造函数
     *
     * @param array $data 初始化数据
     */
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $this->fromArray($data);
        }
    }

    /**
     * 获取页码
     *
     * @return int
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * 设置页码
     *
     * @param int $page
     * @return self
     */
    public function setPage(int $page): self
    {
        $this->page = max(1, $page);
        return $this;
    }

    /**
     * 获取每页数量
     *
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * 设置每页数量
     *
     * @param int $limit
     * @return self
     */
    public function setLimit(int $limit): self
    {
        $this->limit = max(1, min(100, $limit));
        return $this;
    }

    /**
     * 获取排序字段
     *
     * @return string|null
     */
    public function getSortBy(): ?string
    {
        return $this->sortBy;
    }

    /**
     * 设置排序字段
     *
     * @param string|null $sortBy
     * @return self
     */
    public function setSortBy(?string $sortBy): self
    {
        $this->sortBy = $this->cleanString($sortBy);
        return $this;
    }

    /**
     * 获取排序方向
     *
     * @return string
     */
    public function getSortDirection(): string
    {
        return $this->sortDirection;
    }

    /**
     * 设置排序方向
     *
     * @param string $sortDirection
     * @return self
     */
    public function setSortDirection(string $sortDirection): self
    {
        $this->sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';
        return $this;
    }

    /**
     * 获取搜索关键词
     *
     * @return string|null
     */
    public function getKeyword(): ?string
    {
        return $this->keyword;
    }

    /**
     * 设置搜索关键词
     *
     * @param string|null $keyword
     * @return self
     */
    public function setKeyword(?string $keyword): self
    {
        $this->keyword = $this->cleanString($keyword);
        return $this;
    }

    /**
     * 获取搜索字段
     *
     * @return array
     */
    public function getSearchFields(): array
    {
        return $this->searchFields;
    }

    /**
     * 设置搜索字段
     *
     * @param array $searchFields
     * @return self
     */
    public function setSearchFields(array $searchFields): self
    {
        $this->searchFields = array_filter($searchFields, 'is_string');
        return $this;
    }

    /**
     * 获取状态过滤
     *
     * @return array
     */
    public function getStatus(): array
    {
        return $this->status;
    }

    /**
     * 设置状态过滤
     *
     * @param array $status
     * @return self
     */
    public function setStatus(array $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * 获取日期范围开始
     *
     * @return string|null
     */
    public function getDateFrom(): ?string
    {
        return $this->dateFrom;
    }

    /**
     * 设置日期范围开始
     *
     * @param string|null $dateFrom
     * @return self
     */
    public function setDateFrom(?string $dateFrom): self
    {
        $this->dateFrom = $this->cleanString($dateFrom);
        return $this;
    }

    /**
     * 获取日期范围结束
     *
     * @return string|null
     */
    public function getDateTo(): ?string
    {
        return $this->dateTo;
    }

    /**
     * 设置日期范围结束
     *
     * @param string|null $dateTo
     * @return self
     */
    public function setDateTo(?string $dateTo): self
    {
        $this->dateTo = $this->cleanString($dateTo);
        return $this;
    }

    /**
     * 获取创建时间范围开始
     *
     * @return string|null
     */
    public function getCreatedAtFrom(): ?string
    {
        return $this->createdAtFrom;
    }

    /**
     * 设置创建时间范围开始
     *
     * @param string|null $createdAtFrom
     * @return self
     */
    public function setCreatedAtFrom(?string $createdAtFrom): self
    {
        $this->createdAtFrom = $this->cleanString($createdAtFrom);
        return $this;
    }

    /**
     * 获取创建时间范围结束
     *
     * @return string|null
     */
    public function getCreatedAtTo(): ?string
    {
        return $this->createdAtTo;
    }

    /**
     * 设置创建时间范围结束
     *
     * @param string|null $createdAtTo
     * @return self
     */
    public function setCreatedAtTo(?string $createdAtTo): self
    {
        $this->createdAtTo = $this->cleanString($createdAtTo);
        return $this;
    }

    /**
     * 获取更新时间范围开始
     *
     * @return string|null
     */
    public function getUpdatedAtFrom(): ?string
    {
        return $this->updatedAtFrom;
    }

    /**
     * 设置更新时间范围开始
     *
     * @param string|null $updatedAtFrom
     * @return self
     */
    public function setUpdatedAtFrom(?string $updatedAtFrom): self
    {
        $this->updatedAtFrom = $this->cleanString($updatedAtFrom);
        return $this;
    }

    /**
     * 获取更新时间范围结束
     *
     * @return string|null
     */
    public function getUpdatedAtTo(): ?string
    {
        return $this->updatedAtTo;
    }

    /**
     * 设置更新时间范围结束
     *
     * @param string|null $updatedAtTo
     * @return self
     */
    public function setUpdatedAtTo(?string $updatedAtTo): self
    {
        $this->updatedAtTo = $this->cleanString($updatedAtTo);
        return $this;
    }

    /**
     * 获取ID列表
     *
     * @return array
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    /**
     * 设置ID列表
     *
     * @param array $ids
     * @return self
     */
    public function setIds(array $ids): self
    {
        $this->ids = array_filter($ids, 'is_int');
        return $this;
    }

    /**
     * 获取排除的ID列表
     *
     * @return array
     */
    public function getExcludeIds(): array
    {
        return $this->excludeIds;
    }

    /**
     * 设置排除的ID列表
     *
     * @param array $excludeIds
     * @return self
     */
    public function setExcludeIds(array $excludeIds): self
    {
        $this->excludeIds = array_filter($excludeIds, 'is_int');
        return $this;
    }

    /**
     * 是否包含已删除数据
     *
     * @return bool
     */
    public function isIncludeDeleted(): bool
    {
        return $this->includeDeleted;
    }

    /**
     * 设置是否包含已删除数据
     *
     * @param bool $includeDeleted
     * @return self
     */
    public function setIncludeDeleted(bool $includeDeleted): self
    {
        $this->includeDeleted = $includeDeleted;
        return $this;
    }

    /**
     * 是否仅获取总数
     *
     * @return bool
     */
    public function isCountOnly(): bool
    {
        return $this->countOnly;
    }

    /**
     * 设置是否仅获取总数
     *
     * @param bool $countOnly
     * @return self
     */
    public function setCountOnly(bool $countOnly): self
    {
        $this->countOnly = $countOnly;
        return $this;
    }

    /**
     * 获取自定义过滤条件
     *
     * @return array
     */
    public function getCustomFilters(): array
    {
        return $this->customFilters;
    }

    /**
     * 设置自定义过滤条件
     *
     * @param array $customFilters
     * @return self
     */
    public function setCustomFilters(array $customFilters): self
    {
        $this->customFilters = $customFilters;
        return $this;
    }

    /**
     * 添加自定义过滤条件
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function addCustomFilter(string $key, $value): self
    {
        $this->customFilters[$key] = $value;
        return $this;
    }

    /**
     * 获取偏移量
     *
     * @return int
     */
    public function getOffset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    /**
     * 检查是否有搜索条件
     *
     * @return bool
     */
    public function hasSearchConditions(): bool
    {
        return !empty($this->keyword) ||
               !empty($this->searchFields) ||
               !empty($this->status) ||
               !empty($this->ids) ||
               !empty($this->excludeIds) ||
               !empty($this->customFilters);
    }

    /**
     * 检查是否有日期范围条件
     *
     * @return bool
     */
    public function hasDateRangeConditions(): bool
    {
        return !empty($this->dateFrom) ||
               !empty($this->dateTo) ||
               !empty($this->createdAtFrom) ||
               !empty($this->createdAtTo) ||
               !empty($this->updatedAtFrom) ||
               !empty($this->updatedAtTo);
    }

    /**
     * 获取排序数组
     *
     * @return array
     */
    public function getSortArray(): array
    {
        if (empty($this->sortBy)) {
            return [];
        }

        return [$this->sortBy => $this->sortDirection];
    }

    /**
     * 验证日期范围
     *
     * @return array 验证错误数组
     */
    public function validateDateRanges(): array
    {
        $errors = [];

        // 验证日期范围
        if ($this->dateFrom && $this->dateTo) {
            if (strtotime($this->dateFrom) > strtotime($this->dateTo)) {
                $errors['dateRange'] = '开始日期不能大于结束日期';
            }
        }

        // 验证创建时间范围
        if ($this->createdAtFrom && $this->createdAtTo) {
            if (strtotime($this->createdAtFrom) > strtotime($this->createdAtTo)) {
                $errors['createdDateRange'] = '创建时间开始不能大于创建时间结束';
            }
        }

        // 验证更新时间范围
        if ($this->updatedAtFrom && $this->updatedAtTo) {
            if (strtotime($this->updatedAtFrom) > strtotime($this->updatedAtTo)) {
                $errors['updatedDateRange'] = '更新时间开始不能大于更新时间结束';
            }
        }

        return $errors;
    }

    /**
     * 获取过滤条件摘要
     *
     * @return array
     */
    public function getFilterSummary(): array
    {
        return [
            'page' => $this->page,
            'limit' => $this->limit,
            'offset' => $this->getOffset(),
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
            'keyword' => $this->keyword,
            'searchFields' => $this->searchFields,
            'status' => $this->status,
            'hasSearchConditions' => $this->hasSearchConditions(),
            'hasDateRangeConditions' => $this->hasDateRangeConditions(),
            'includeDeleted' => $this->includeDeleted,
            'countOnly' => $this->countOnly,
        ];
    }

    /**
     * 转换为数组（包含过滤条件）
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_merge($this->getPublicProperties(), [
            'page' => $this->page,
            'limit' => $this->limit,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
            'keyword' => $this->keyword,
            'searchFields' => $this->searchFields,
            'status' => $this->status,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'createdAtFrom' => $this->createdAtFrom,
            'createdAtTo' => $this->createdAtTo,
            'updatedAtFrom' => $this->updatedAtFrom,
            'updatedAtTo' => $this->updatedAtTo,
            'ids' => $this->ids,
            'excludeIds' => $this->excludeIds,
            'includeDeleted' => $this->includeDeleted,
            'countOnly' => $this->countOnly,
            'customFilters' => $this->customFilters,
        ]);
    }

    /**
     * 从数组创建实例（包含过滤条件）
     *
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $instance = new static();

        // 设置分页参数
        if (isset($data['page'])) {
            $instance->setPage($data['page']);
        }

        if (isset($data['limit'])) {
            $instance->setLimit($data['limit']);
        }

        // 设置排序参数
        if (isset($data['sortBy'])) {
            $instance->setSortBy($data['sortBy']);
        }

        if (isset($data['sortDirection'])) {
            $instance->setSortDirection($data['sortDirection']);
        }

        // 设置搜索参数
        if (isset($data['keyword'])) {
            $instance->setKeyword($data['keyword']);
        }

        if (isset($data['searchFields'])) {
            $instance->setSearchFields($data['searchFields']);
        }

        if (isset($data['status'])) {
            $instance->setStatus($data['status']);
        }

        // 设置日期范围
        if (isset($data['dateFrom'])) {
            $instance->setDateFrom($data['dateFrom']);
        }

        if (isset($data['dateTo'])) {
            $instance->setDateTo($data['dateTo']);
        }

        if (isset($data['createdAtFrom'])) {
            $instance->setCreatedAtFrom($data['createdAtFrom']);
        }

        if (isset($data['createdAtTo'])) {
            $instance->setCreatedAtTo($data['createdAtTo']);
        }

        if (isset($data['updatedAtFrom'])) {
            $instance->setUpdatedAtFrom($data['updatedAtFrom']);
        }

        if (isset($data['updatedAtTo'])) {
            $instance->setUpdatedAtTo($data['updatedAtTo']);
        }

        // 设置ID过滤
        if (isset($data['ids'])) {
            $instance->setIds($data['ids']);
        }

        if (isset($data['excludeIds'])) {
            $instance->setExcludeIds($data['excludeIds']);
        }

        // 设置其他选项
        if (isset($data['includeDeleted'])) {
            $instance->setIncludeDeleted($data['includeDeleted']);
        }

        if (isset($data['countOnly'])) {
            $instance->setCountOnly($data['countOnly']);
        }

        if (isset($data['customFilters'])) {
            $instance->setCustomFilters($data['customFilters']);
        }

        // 设置其他公共属性
        $instance->setPublicProperties($data);

        return $instance;
    }

    /**
     * 创建分页DTO实例
     *
     * @return PaginationDto
     */
    #[OA\Property(ref: '#/components/schemas/PaginationDto')]
    public function createPaginationDto(): PaginationDto
    {
        return new PaginationDto([
            'page' => $this->page,
            'limit' => $this->limit
        ]);
    }

    /**
     * 创建排序DTO实例
     *
     * @return SortDto
     */
    #[OA\Property(ref: '#/components/schemas/SortDto')]
    public function createSortDto(): SortDto
    {
        return new SortDto([
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection
        ]);
    }

    /**
     * 从数据填充DTO
     *
     * @param array $data
     * @return self
     */
    public function populateFromData(array $data): self
    {
        // 设置分页参数
        if (isset($data['page'])) {
            $this->setPage((int)$data['page']);
        }

        if (isset($data['limit'])) {
            $this->setLimit((int)$data['limit']);
        }

        // 设置排序参数
        if (isset($data['sortBy'])) {
            $this->setSortBy($data['sortBy']);
        }

        if (isset($data['sortDirection'])) {
            $this->setSortDirection($data['sortDirection']);
        }

        // 设置搜索参数
        if (isset($data['keyword'])) {
            $this->setKeyword($data['keyword']);
        }

        if (isset($data['searchFields'])) {
            $this->setSearchFields($data['searchFields']);
        }

        if (isset($data['status'])) {
            $this->setStatus(is_array($data['status']) ? $data['status'] : [(int)$data['status']]);
        }

        // 设置日期范围
        if (isset($data['dateFrom'])) {
            $this->setDateFrom($data['dateFrom']);
        }

        if (isset($data['dateTo'])) {
            $this->setDateTo($data['dateTo']);
        }

        if (isset($data['createdAtFrom'])) {
            $this->setCreatedAtFrom($data['createdAtFrom']);
        }

        if (isset($data['createdAtTo'])) {
            $this->setCreatedAtTo($data['createdAtTo']);
        }

        if (isset($data['updatedAtFrom'])) {
            $this->setUpdatedAtFrom($data['updatedAtFrom']);
        }

        if (isset($data['updatedAtTo'])) {
            $this->setUpdatedAtTo($data['updatedAtTo']);
        }

        // 设置ID过滤
        if (isset($data['ids'])) {
            $this->setIds(is_array($data['ids']) ? $data['ids'] : [(int)$data['ids']]);
        }

        if (isset($data['excludeIds'])) {
            $this->setExcludeIds(is_array($data['excludeIds']) ? $data['excludeIds'] : [(int)$data['excludeIds']]);
        }

        // 设置其他选项
        if (isset($data['includeDeleted'])) {
            $this->setIncludeDeleted((bool)$data['includeDeleted']);
        }

        if (isset($data['countOnly'])) {
            $this->setCountOnly((bool)$data['countOnly']);
        }

        if (isset($data['customFilters'])) {
            $this->setCustomFilters($data['customFilters']);
        }

        return $this;
    }

    /**
     * 获取过滤条件数组
     * 用于Repository查询
     */
    public function getFilterCriteria(): array
    {
        $criteria = [];

        // 基础过滤条件
        if (!empty($this->merchantId)) {
            $criteria['merchantId'] = $this->merchantId;
        }

        if (!empty($this->userId)) {
            $criteria['userId'] = $this->userId;
        }

        if ($this->status !== null) {
            $criteria['status'] = $this->status;
        }

        if ($this->isRecommend !== null) {
            $criteria['isRecommend'] = $this->isRecommend;
        }

        if (!empty($this->categoryCode)) {
            $criteria['categoryCode'] = $this->categoryCode;
        }

        // 搜索条件
        if (!empty($this->name)) {
            $criteria['name'] = $this->name;
        }

        if (!empty($this->userName)) {
            $criteria['userName'] = $this->userName;
        }

        // 发布状态过滤
        if (!empty($this->publishStatus)) {
            $criteria['publishStatus'] = $this->publishStatus;
        }

        // 日期范围过滤
        if (!empty($this->startDate)) {
            $criteria['startDate'] = $this->startDate;
        }

        if (!empty($this->endDate)) {
            $criteria['endDate'] = $this->endDate;
        }

        return $criteria;
    }
}
