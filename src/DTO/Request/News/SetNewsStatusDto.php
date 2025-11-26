<?php

namespace App\DTO\Request\News;

use App\DTO\Base\AbstractRequestDto;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * 设置新闻文章状态请求DTO
 *
 * 用于设置新闻文章状态时的请求数据传输对象
 * 包含设置状态所需的核心字段和验证约束
 */
#[OA\Schema(
    title: '设置新闻文章状态请求DTO',
    description: '设置新闻文章状态的请求数据结构',
    required: ['status']
)]
class SetNewsStatusDto extends AbstractRequestDto
{
    /**
     * 文章状态
     */
    #[Assert\NotBlank(message: '状态不能为空')]
    #[Assert\Choice(choices: [1, 2, 3], message: '状态值必须是1（激活）、2（非激活）或3（已删除）')]
    #[OA\Property(
        description: '文章状态：1-激活（已发布），2-非激活（待发布），3-已删除',
        example: 1,
        enum: [1, 2, 3]
    )]
    #[Groups(['setNewsStatus:read', 'setNewsStatus:write'])]
    public int $status;

    /**
     * 状态变更原因
     */
    #[Assert\Length(max: 255, maxMessage: '状态变更原因不能超过255个字符')]
    #[OA\Property(
        description: '状态变更原因说明',
        example: '内容审核通过，发布文章',
        maxLength: 255
    )]
    #[Groups(['setNewsStatus:read', 'setNewsStatus:write'])]
    public ?string $reason = null;

    /**
     * 是否强制设置（忽略状态检查）
     */
    #[Assert\Type(type: 'bool', message: '强制设置必须是布尔值')]
    #[OA\Property(
        description: '是否强制设置状态（忽略业务逻辑检查）',
        example: false
    )]
    #[Groups(['setNewsStatus:read', 'setNewsStatus:write'])]
    public bool $force = false;

    /**
     * 批量操作的文章ID列表（可选）
     */
    #[Assert\Type(type: 'array', message: '文章ID列表必须是数组')]
    #[Assert\All([
        new Assert\Type(type: 'integer', message: '文章ID必须是整数'),
        new Assert\Positive(message: '文章ID必须大于0')
    ])]
    #[Assert\Count(max: 100, maxMessage: '批量操作的文章数量不能超过100个')]
    #[OA\Property(
        description: '批量操作的文章ID列表（为空时为单个文章操作）',
        example: [1, 2, 3],
        maxItems: 100
    )]
    #[Groups(['setNewsStatus:read', 'setNewsStatus:write'])]
    public array $articleIds = [];

    /**
     * 操作员ID
     */
    #[Assert\Positive(message: '操作员ID必须是正整数')]
    #[OA\Property(
        description: '执行状态变更的操作员ID',
        example: 1,
        minimum: 1
    )]
    #[Groups(['setNewsStatus:read', 'setNewsStatus:write'])]
    public ?int $operatorId = null;

    /**
     * 操作时间（可选，默认为当前时间）
     */
    #[Assert\DateTime(message: '操作时间格式不正确')]
    #[OA\Property(
        description: '操作时间（格式：Y-m-d H:i:s，为空时使用当前时间）',
        example: '2024-01-01 10:00:00',
        format: 'date-time'
    )]
    #[Groups(['setNewsStatus:read', 'setNewsStatus:write'])]
    public ?string $operationTime = null;

    /**
     * 是否发送通知
     */
    #[Assert\Type(type: 'bool', message: '发送通知必须是布尔值')]
    #[OA\Property(
        description: '是否发送状态变更通知',
        example: true
    )]
    #[Groups(['setNewsStatus:read', 'setNewsStatus:write'])]
    public bool $sendNotification = true;

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
        if (isset($data['status'])) {
            $this->status = (int) $data['status'];
        }

        if (isset($data['reason'])) {
            $this->reason = $data['reason'] !== null ? $this->cleanString($data['reason']) : null;
        }

        if (isset($data['force'])) {
            $this->force = (bool) $data['force'];
        }

        if (isset($data['articleIds'])) {
            $this->articleIds = is_array($data['articleIds']) ? array_map('intval', $data['articleIds']) : [];
        }

        if (isset($data['operatorId'])) {
            $this->operatorId = $data['operatorId'] !== null ? (int) $data['operatorId'] : null;
        }

        if (isset($data['operationTime'])) {
            $this->operationTime = $data['operationTime'] !== null ? $data['operationTime'] : null;
        }

        if (isset($data['sendNotification'])) {
            $this->sendNotification = (bool) $data['sendNotification'];
        }

        return $this;
    }

    /**
     * 获取格式化的操作时间
     *
     * @return \DateTimeInterface|null
     * @throws \Exception
     */
    public function getOperationTimeDateTime(): ?\DateTimeInterface
    {
        return $this->operationTime ? $this->parseDateTime($this->operationTime) : null;
    }

    /**
     * 设置操作时间为DateTime对象
     *
     * @param \DateTimeInterface|null $dateTime
     * @return self
     */
    public function setOperationTimeDateTime(?\DateTimeInterface $dateTime): self
    {
        $this->operationTime = $dateTime ? $this->formatDateTime($dateTime) : null;
        return $this;
    }

    /**
     * 获取状态描述
     *
     * @return string
     */
    public function getStatusDescription(): string
    {
        return match($this->status) {
            1 => '激活（已发布）',
            2 => '非激活（待发布）',
            3 => '已删除',
            default => '未知状态'
        };
    }

    /**
     * 获取状态对应的英文标识
     *
     * @return string
     */
    public function getStatusKey(): string
    {
        return match($this->status) {
            1 => 'active',
            2 => 'inactive',
            3 => 'deleted',
            default => 'unknown'
        };
    }

    /**
     * 检查是否为删除操作
     *
     * @return bool
     */
    public function isDeleteOperation(): bool
    {
        return $this->status === 3;
    }

    /**
     * 检查是否为激活操作
     *
     * @return bool
     */
    public function isActivateOperation(): bool
    {
        return $this->status === 1;
    }

    /**
     * 检查是否为停用操作
     *
     * @return bool
     */
    public function isDeactivateOperation(): bool
    {
        return $this->status === 2;
    }

    /**
     * 检查是否为批量操作
     *
     * @return bool
     */
    public function isBatchOperation(): bool
    {
        return !empty($this->articleIds);
    }

    /**
     * 获取操作的文章数量
     *
     * @return int
     */
    public function getArticleCount(): int
    {
        return $this->isBatchOperation() ? count($this->articleIds) : 1;
    }

    /**
     * 验证业务逻辑
     *
     * @return array 验证错误数组
     */
    public function validateBusinessRules(): array
    {
        $errors = [];

        // 验证操作时间
        if ($this->operationTime !== null) {
            try {
                $operationTime = new \DateTime($this->operationTime);
                $now = new \DateTime();

                // 操作时间不能是未来时间（除非强制执行）
                if ($operationTime > $now && !$this->force) {
                    $errors['operationTime'] = '操作时间不能是未来时间，除非设置force=true';
                }

                // 操作时间不能太旧（超过1年）
                $oneYearAgo = (clone $now)->modify('-1 year');
                if ($operationTime < $oneYearAgo) {
                    $errors['operationTime'] = '操作时间不能早于一年前';
                }
            } catch (\Exception $e) {
                $errors['operationTime'] = '操作时间格式不正确';
            }
        }

        // 验证批量操作的文章ID
        if ($this->isBatchOperation()) {
            $uniqueIds = array_unique($this->articleIds);
            if (count($uniqueIds) !== count($this->articleIds)) {
                $errors['articleIds'] = '文章ID列表中存在重复的ID';
            }
        }

        // 验证删除操作的特殊规则
        if ($this->isDeleteOperation()) {
            if (empty($this->reason) && !$this->force) {
                $errors['reason'] = '删除操作必须提供原因说明，除非设置force=true';
            }
        }

        // 验证操作员ID
        if ($this->operatorId !== null && $this->operatorId <= 0) {
            $errors['operatorId'] = '操作员ID必须是正整数';
        }

        return $errors;
    }

    /**
     * 获取操作摘要信息
     *
     * @return array
     */
    public function getOperationSummary(): array
    {
        return [
            'status' => $this->status,
            'statusDescription' => $this->getStatusDescription(),
            'statusKey' => $this->getStatusKey(),
            'isBatchOperation' => $this->isBatchOperation(),
            'articleCount' => $this->getArticleCount(),
            'articleIds' => $this->articleIds,
            'isDeleteOperation' => $this->isDeleteOperation(),
            'isActivateOperation' => $this->isActivateOperation(),
            'isDeactivateOperation' => $this->isDeactivateOperation(),
            'force' => $this->force,
            'sendNotification' => $this->sendNotification,
            'operatorId' => $this->operatorId,
            'operationTime' => $this->operationTime,
            'reason' => $this->reason,
        ];
    }

    /**
     * 转换为数组
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'status' => $this->status,
            'statusDescription' => $this->getStatusDescription(),
            'statusKey' => $this->getStatusKey(),
            'reason' => $this->reason,
            'force' => $this->force,
            'articleIds' => $this->articleIds,
            'operatorId' => $this->operatorId,
            'operationTime' => $this->operationTime,
            'sendNotification' => $this->sendNotification,
            'isBatchOperation' => $this->isBatchOperation(),
            'articleCount' => $this->getArticleCount(),
            'isDeleteOperation' => $this->isDeleteOperation(),
            'isActivateOperation' => $this->isActivateOperation(),
            'isDeactivateOperation' => $this->isDeactivateOperation(),
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
