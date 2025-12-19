<?php

namespace App\DTO\Filter;

use App\DTO\Base\AbstractFilterDto;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * 微信公众号过滤器DTO
 *
 * 用于微信公众号查询过滤条件的数据传输对象
 * 包含公众号查询过滤的各种字段和验证约束
 */
#[OA\Schema(
    title: '微信公众号过滤器DTO',
    description: '微信公众号查询过滤条件的数据结构',
    required: []
)]
class WechatAccountFilterDto extends AbstractFilterDto
{
    /**
     * 公众号名称过滤
     */
    #[Assert\Type(type: 'string', message: '公众号名称必须是字符串')]
    #[Assert\Length(max: 255, maxMessage: '公众号名称不能超过255个字符')]
    #[OA\Property(
        description: '公众号名称模糊搜索',
        example: '官方',
        maxLength: 255
    )]
    #[Groups(['wechatAccountFilter:read', 'wechatAccountFilter:write'])]
    public ?string $name = null;

    /**
     * 微信AppId过滤
     */
    #[Assert\Type(type: 'string', message: 'AppId必须是字符串')]
    #[Assert\Length(max: 128, maxMessage: 'AppId不能超过128个字符')]
    #[Assert\Regex(pattern: '/^wx[a-f0-9]{16}$/', message: 'AppId格式不正确，应以wx开头后跟16位十六进制字符')]
    #[OA\Property(
        description: '微信公众号AppId精确匹配',
        example: 'wx1234567890abcdef',
        maxLength: 128
    )]
    #[Groups(['wechatAccountFilter:read', 'wechatAccountFilter:write'])]
    public ?string $appId = null;

    /**
     * 是否激活过滤
     */
    #[Assert\Type(type: 'bool', message: '是否激活必须是布尔值')]
    #[OA\Property(
        description: '是否激活状态过滤',
        example: true
    )]
    #[Groups(['wechatAccountFilter:read', 'wechatAccountFilter:write'])]
    public ?bool $isActive = null;

    /**
     * 是否启用消息加密过滤
     */
    #[Assert\Type(type: 'bool', message: '是否启用消息加密必须是布尔值')]
    #[OA\Property(
        description: '是否启用消息加密过滤',
        example: false
    )]
    #[Groups(['wechatAccountFilter:read', 'wechatAccountFilter:write'])]
    public ?bool $hasMessageEncryption = null;

    /**
     * 描述关键词过滤
     */
    #[Assert\Type(type: 'string', message: '描述关键词必须是字符串')]
    #[Assert\Length(max: 500, maxMessage: '描述关键词不能超过500个字符')]
    #[OA\Property(
        description: '公众号描述关键词搜索',
        example: '科技',
        maxLength: 500
    )]
    #[Groups(['wechatAccountFilter:read', 'wechatAccountFilter:write'])]
    public ?string $descriptionKeyword = null;

    /**
     * 头像URL域名过滤
     */
    #[Assert\Type(type: 'string', message: '头像域名必须是字符串')]
    #[Assert\Length(max: 255, maxMessage: '头像域名不能超过255个字符')]
    #[OA\Property(
        description: '头像URL域名过滤',
        example: 'example.com',
        maxLength: 255
    )]
    #[Groups(['wechatAccountFilter:read', 'wechatAccountFilter:write'])]
    public ?string $avatarDomain = null;

    /**
     * Token是否设置过滤
     */
    #[Assert\Type(type: 'bool', message: 'Token设置状态必须是布尔值')]
    #[OA\Property(
        description: '是否设置了Token',
        example: true
    )]
    #[Groups(['wechatAccountFilter:read', 'wechatAccountFilter:write'])]
    public ?bool $hasToken = null;

    /**
     * EncodingAESKey是否设置过滤
     */
    #[Assert\Type(type: 'bool', message: 'EncodingAESKey设置状态必须是布尔值')]
    #[OA\Property(
        description: '是否设置了EncodingAESKey',
        example: false
    )]
    #[Groups(['wechatAccountFilter:read', 'wechatAccountFilter:write'])]
    public ?bool $hasEncodingAESKey = null;

    /**
     * 创建时间范围开始
     */
    #[Assert\Type(type: 'string', message: '创建时间开始必须是字符串')]
    #[Assert\DateTime(message: '创建时间开始格式不正确')]
    #[OA\Property(
        description: '创建时间范围开始（格式：Y-m-d H:i:s）',
        example: '2024-01-01 00:00:00',
        format: 'date-time'
    )]
    #[Groups(['wechatAccountFilter:read', 'wechatAccountFilter:write'])]
    public ?string $createdAtFrom = null;

    /**
     * 创建时间范围结束
     */
    #[Assert\Type(type: 'string', message: '创建时间结束必须是字符串')]
    #[Assert\DateTime(message: '创建时间结束格式不正确')]
    #[OA\Property(
        description: '创建时间范围结束（格式：Y-m-d H:i:s）',
        example: '2024-12-31 23:59:59',
        format: 'date-time'
    )]
    #[Groups(['wechatAccountFilter:read', 'wechatAccountFilter:write'])]
    public ?string $createdAtTo = null;

    /**
     * 更新时间范围开始
     */
    #[Assert\Type(type: 'string', message: '更新时间开始必须是字符串')]
    #[Assert\DateTime(message: '更新时间开始格式不正确')]
    #[OA\Property(
        description: '更新时间范围开始（格式：Y-m-d H:i:s）',
        example: '2024-01-01 00:00:00',
        format: 'date-time'
    )]
    #[Groups(['wechatAccountFilter:read', 'wechatAccountFilter:write'])]
    public ?string $updatedAtFrom = null;

    /**
     * 更新时间范围结束
     */
    #[Assert\Type(type: 'string', message: '更新时间结束必须是字符串')]
    #[Assert\DateTime(message: '更新时间结束格式不正确')]
    #[OA\Property(
        description: '更新时间范围结束（格式：Y-m-d H:i:s）',
        example: '2024-12-31 23:59:59',
        format: 'date-time'
    )]
    #[Groups(['wechatAccountFilter:read', 'wechatAccountFilter:write'])]
    public ?string $updatedAtTo = null;

    /**
     * AppId前缀过滤
     */
    #[Assert\Type(type: 'string', message: 'AppId前缀必须是字符串')]
    #[Assert\Length(max: 10, maxMessage: 'AppId前缀不能超过10个字符')]
    #[Assert\Regex(pattern: '/^wx[a-f0-9]*$/', message: 'AppId前缀格式不正确，应以wx开头后跟十六进制字符')]
    #[OA\Property(
        description: 'AppId前缀过滤（用于批量查询相似AppId）',
        example: 'wx1234',
        maxLength: 10
    )]
    #[Groups(['wechatAccountFilter:read', 'wechatAccountFilter:write'])]
    public ?string $appIdPrefix = null;

    /**
     * 是否包含敏感信息
     */
    #[Assert\Type(type: 'bool', message: '包含敏感信息必须是布尔值')]
    #[OA\Property(
        description: '是否在结果中包含AppSecret等敏感信息',
        example: false
    )]
    #[Groups(['wechatAccountFilter:read', 'wechatAccountFilter:write'])]
    public bool $includeSensitive = false;

    /**
     * 构造函数
     *
     * @param array $data 初始化数据
     */
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        $this->populateFromData($data);
    }

    /**
     * 从数据填充DTO
     *
     * @param array $data
     * @return self
     */
    public function populateFromData(array $data): self
    {
        // 设置父类属性 - 支持新旧参数名
        if (isset($data['page'])) {
            $this->setPage($data['page']);
        } elseif (isset($data['current'])) {
            $this->setPage($data['current']);
        }

        if (isset($data['size'])) {
            $this->setSize($data['size']);
        } elseif (isset($data['pageSize'])) {
            $this->setSize($data['pageSize']);
        } elseif (isset($data['limit'])) {
            $this->setSize($data['limit']);
        }

        if (isset($data['sortBy'])) {
            $this->setSortBy($data['sortBy']);
        }

        if (isset($data['sortDirection'])) {
            $this->setSortDirection($data['sortDirection']);
        }

        if (isset($data['keyword'])) {
            $this->setKeyword($data['keyword']);
        }

        if (isset($data['searchFields'])) {
            $this->setSearchFields($data['searchFields']);
        }

        if (isset($data['status']) && is_array($data['status'])) {
            $this->setStatus($data['status']);
        }

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

        if (isset($data['ids'])) {
            $this->setIds($data['ids']);
        }

        if (isset($data['excludeIds'])) {
            $this->setExcludeIds($data['excludeIds']);
        }

        if (isset($data['includeDeleted'])) {
            $this->setIncludeDeleted($data['includeDeleted']);
        }

        if (isset($data['countOnly'])) {
            $this->setCountOnly($data['countOnly']);
        }

        if (isset($data['customFilters'])) {
            $this->setCustomFilters($data['customFilters']);
        }

        if (isset($data['name'])) {
            $this->name = $data['name'] !== null ? $this->cleanString($data['name']) : null;
        }

        if (isset($data['appId'])) {
            $this->appId = $data['appId'] !== null ? $this->cleanString($data['appId']) : null;
        }

        if (isset($data['isActive'])) {
            $this->isActive = $data['isActive'] !== null ? (bool) $data['isActive'] : null;
        }

        if (isset($data['hasMessageEncryption'])) {
            $this->hasMessageEncryption = $data['hasMessageEncryption'] !== null ? (bool) $data['hasMessageEncryption'] : null;
        }

        if (isset($data['descriptionKeyword'])) {
            $this->descriptionKeyword = $data['descriptionKeyword'] !== null ? $this->cleanString($data['descriptionKeyword']) : null;
        }

        if (isset($data['avatarDomain'])) {
            $this->avatarDomain = $data['avatarDomain'] !== null ? $this->cleanString($data['avatarDomain']) : null;
        }

        if (isset($data['hasToken'])) {
            $this->hasToken = $data['hasToken'] !== null ? (bool) $data['hasToken'] : null;
        }

        if (isset($data['hasEncodingAESKey'])) {
            $this->hasEncodingAESKey = $data['hasEncodingAESKey'] !== null ? (bool) $data['hasEncodingAESKey'] : null;
        }

        if (isset($data['createdAtFrom'])) {
            $this->createdAtFrom = $data['createdAtFrom'] !== null ? $data['createdAtFrom'] : null;
        }

        if (isset($data['createdAtTo'])) {
            $this->createdAtTo = $data['createdAtTo'] !== null ? $data['createdAtTo'] : null;
        }

        if (isset($data['updatedAtFrom'])) {
            $this->updatedAtFrom = $data['updatedAtFrom'] !== null ? $data['updatedAtFrom'] : null;
        }

        if (isset($data['updatedAtTo'])) {
            $this->updatedAtTo = $data['updatedAtTo'] !== null ? $data['updatedAtTo'] : null;
        }

        if (isset($data['appIdPrefix'])) {
            $this->appIdPrefix = $data['appIdPrefix'] !== null ? $this->cleanString($data['appIdPrefix']) : null;
        }

        if (isset($data['includeSensitive'])) {
            $this->includeSensitive = (bool) $data['includeSensitive'];
        }

        return $this;
    }

    /**
     * 获取创建时间开始DateTime对象
     *
     * @return \DateTimeInterface|null
     * @throws \Exception
     */
    public function getCreatedAtFromDateTime(): ?\DateTimeInterface
    {
        return $this->createdAtFrom ? $this->parseDateTime($this->createdAtFrom) : null;
    }

    /**
     * 获取创建时间结束DateTime对象
     *
     * @return \DateTimeInterface|null
     * @throws \Exception
     */
    public function getCreatedAtToDateTime(): ?\DateTimeInterface
    {
        return $this->createdAtTo ? $this->parseDateTime($this->createdAtTo) : null;
    }

    /**
     * 获取更新时间开始DateTime对象
     *
     * @return \DateTimeInterface|null
     * @throws \Exception
     */
    public function getUpdatedAtFromDateTime(): ?\DateTimeInterface
    {
        return $this->updatedAtFrom ? $this->parseDateTime($this->updatedAtFrom) : null;
    }

    /**
     * 获取更新时间结束DateTime对象
     *
     * @return \DateTimeInterface|null
     * @throws \Exception
     */
    public function getUpdatedAtToDateTime(): ?\DateTimeInterface
    {
        return $this->updatedAtTo ? $this->parseDateTime($this->updatedAtTo) : null;
    }

    /**
     * 获取状态描述
     *
     * @return string|null
     */
    public function getStatusDescription(): ?string
    {
        if ($this->isActive === null) {
            return null;
        }
        return $this->isActive ? '已激活' : '未激活';
    }

    /**
     * 获取加密状态描述
     *
     * @return string|null
     */
    public function getEncryptionStatusDescription(): ?string
    {
        if ($this->hasMessageEncryption === null) {
            return null;
        }
        return $this->hasMessageEncryption ? '已启用消息加密' : '未启用消息加密';
    }

    /**
     * 检查是否有公众号特定的过滤条件
     *
     * @return bool
     */
    public function hasWechatSpecificFilters(): bool
    {
        return $this->name !== null ||
               $this->appId !== null ||
               $this->isActive !== null ||
               $this->hasMessageEncryption !== null ||
               $this->descriptionKeyword !== null ||
               $this->avatarDomain !== null ||
               $this->hasToken !== null ||
               $this->hasEncodingAESKey !== null ||
               $this->appIdPrefix !== null ||
               $this->createdAtFrom !== null ||
               $this->createdAtTo !== null ||
               $this->updatedAtFrom !== null ||
               $this->updatedAtTo !== null;
    }

    /**
     * 检查是否有时间相关的过滤条件
     *
     * @return bool
     */
    public function hasTimeFilters(): bool
    {
        return parent::hasDateRangeConditions() ||
               $this->createdAtFrom !== null ||
               $this->createdAtTo !== null ||
               $this->updatedAtFrom !== null ||
               $this->updatedAtTo !== null;
    }

    /**
     * 检查是否有加密相关的过滤条件
     *
     * @return bool
     */
    public function hasEncryptionFilters(): bool
    {
        return $this->hasMessageEncryption !== null ||
               $this->hasToken !== null ||
               $this->hasEncodingAESKey !== null;
    }

    /**
     * 获取过滤条件摘要
     *
     * @return array
     */
    public function getFilterSummary(): array
    {
        return array_merge(parent::getFilterSummary(), [
            'name' => $this->name,
            'appId' => $this->appId,
            'isActive' => $this->isActive,
            'statusDescription' => $this->getStatusDescription(),
            'hasMessageEncryption' => $this->hasMessageEncryption,
            'encryptionStatusDescription' => $this->getEncryptionStatusDescription(),
            'descriptionKeyword' => $this->descriptionKeyword,
            'avatarDomain' => $this->avatarDomain,
            'hasToken' => $this->hasToken,
            'hasEncodingAESKey' => $this->hasEncodingAESKey,
            'createdAtFrom' => $this->createdAtFrom,
            'createdAtTo' => $this->createdAtTo,
            'updatedAtFrom' => $this->updatedAtFrom,
            'updatedAtTo' => $this->updatedAtTo,
            'appIdPrefix' => $this->appIdPrefix,
            'includeSensitive' => $this->includeSensitive,
            'hasWechatSpecificFilters' => $this->hasWechatSpecificFilters(),
            'hasTimeFilters' => $this->hasTimeFilters(),
            'hasEncryptionFilters' => $this->hasEncryptionFilters(),
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
        if ($this->name !== null) {
            $criteria['name'] = ['like' => '%' . $this->name . '%'];
        }

        if ($this->appId !== null) {
            $criteria['appId'] = $this->appId;
        }

        if ($this->isActive !== null) {
            $criteria['isActive'] = $this->isActive;
        }

        // 描述关键词搜索
        if ($this->descriptionKeyword !== null) {
            $criteria['description'] = ['like' => '%' . $this->descriptionKeyword . '%'];
        }

        // 头像域名过滤
        if ($this->avatarDomain !== null) {
            $criteria['avatarUrl'] = ['like' => '%' . $this->avatarDomain . '%'];
        }

        // AppId前缀过滤
        if ($this->appIdPrefix !== null) {
            $criteria['appId'] = ['like' => $this->appIdPrefix . '%'];
        }

        // 加密相关过滤
        if ($this->hasMessageEncryption !== null) {
            if ($this->hasMessageEncryption) {
                $criteria['token'] = ['notNull' => true];
                $criteria['encodingAESKey'] = ['notNull' => true];
            } else {
                $criteria['token'] = ['or' => ['isNull' => true, 'eq' => '']];
                $criteria['encodingAESKey'] = ['or' => ['isNull' => true, 'eq' => '']];
            }
        }

        if ($this->hasToken !== null) {
            if ($this->hasToken) {
                $criteria['token'] = ['notNull' => true];
            } else {
                $criteria['token'] = ['or' => ['isNull' => true, 'eq' => '']];
            }
        }

        if ($this->hasEncodingAESKey !== null) {
            if ($this->hasEncodingAESKey) {
                $criteria['encodingAESKey'] = ['notNull' => true];
            } else {
                $criteria['encodingAESKey'] = ['or' => ['isNull' => true, 'eq' => '']];
            }
        }

        // 时间范围条件
        if ($this->createdAtFrom !== null) {
            $criteria['createdAt'] = $criteria['createdAt'] ?? [];
            $criteria['createdAt']['from'] = $this->createdAtFrom;
        }

        if ($this->createdAtTo !== null) {
            $criteria['createdAt'] = $criteria['createdAt'] ?? [];
            $criteria['createdAt']['to'] = $this->createdAtTo;
        }

        if ($this->updatedAtFrom !== null) {
            $criteria['updatedAt'] = $criteria['updatedAt'] ?? [];
            $criteria['updatedAt']['from'] = $this->updatedAtFrom;
        }

        if ($this->updatedAtTo !== null) {
            $criteria['updatedAt'] = $criteria['updatedAt'] ?? [];
            $criteria['updatedAt']['to'] = $this->updatedAtTo;
        }

        // 合并父类过滤条件（基类没有getFilterCriteria方法，直接返回当前条件）
        return $criteria;
    }

    /**
     * 验证过滤条件
     *
     * @return array 验证错误数组
     */
    public function validateFilters(): array
    {
        $errors = parent::validateDateRanges();

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

        // 验证AppId格式
        if ($this->appId !== null && !preg_match('/^wx[a-f0-9]{16}$/', $this->appId)) {
            $errors['appId'] = 'AppId格式不正确，应以wx开头后跟16位十六进制字符';
        }

        // 验证AppId前缀格式
        if ($this->appIdPrefix !== null && !preg_match('/^wx[a-f0-9]*$/', $this->appIdPrefix)) {
            $errors['appIdPrefix'] = 'AppId前缀格式不正确，应以wx开头后跟十六进制字符';
        }

        // 验证加密过滤条件的一致性
        if ($this->hasMessageEncryption !== null && $this->hasToken !== null) {
            if ($this->hasMessageEncryption && !$this->hasToken) {
                $errors['encryptionFilter'] = '启用消息加密过滤与Token设置过滤冲突';
            }
        }

        if ($this->hasMessageEncryption !== null && $this->hasEncodingAESKey !== null) {
            if ($this->hasMessageEncryption && !$this->hasEncodingAESKey) {
                $errors['encryptionFilter'] = '启用消息加密过滤与EncodingAESKey设置过滤冲突';
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
            'name' => $this->name,
            'appId' => $this->appId,
            'isActive' => $this->isActive,
            'statusDescription' => $this->getStatusDescription(),
            'hasMessageEncryption' => $this->hasMessageEncryption,
            'encryptionStatusDescription' => $this->getEncryptionStatusDescription(),
            'descriptionKeyword' => $this->descriptionKeyword,
            'avatarDomain' => $this->avatarDomain,
            'hasToken' => $this->hasToken,
            'hasEncodingAESKey' => $this->hasEncodingAESKey,
            'createdAtFrom' => $this->createdAtFrom,
            'createdAtTo' => $this->createdAtTo,
            'updatedAtFrom' => $this->updatedAtFrom,
            'updatedAtTo' => $this->updatedAtTo,
            'appIdPrefix' => $this->appIdPrefix,
            'includeSensitive' => $this->includeSensitive,
            'hasWechatSpecificFilters' => $this->hasWechatSpecificFilters(),
            'hasTimeFilters' => $this->hasTimeFilters(),
            'hasEncryptionFilters' => $this->hasEncryptionFilters(),
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
}
