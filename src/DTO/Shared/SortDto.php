<?php

namespace App\DTO\Shared;

use App\DTO\Base\AbstractDto;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;
use OpenApi\Attributes as OA;

/**
 * 排序数据传输对象
 * 用于处理排序相关的数据
 */
#[OA\Schema(
    schema: 'SortDto',
    title: '排序数据传输对象',
    description: '用于处理排序相关的数据结构'
)]
class SortDto extends AbstractDto
{
    public const ASC = 'asc';
    public const DESC = 'desc';

    /**
     * 排序字段
     */
    #[Assert\Type(type: 'string', message: '排序字段必须是字符串')]
    #[Assert\NotBlank(message: '排序字段不能为空')]
    #[Assert\Length(max: 100, maxMessage: '排序字段不能超过100个字符')]
    #[Groups(['sort:read', 'sort:write'])]
    protected string $field;

    /**
     * 排序方向
     */
    #[Assert\Type(type: 'string', message: '排序方向必须是字符串')]
    #[Assert\Choice(choices: [self::ASC, self::DESC, 'ASC', 'DESC'], message: '排序方向必须是asc或desc')]
    #[Groups(['sort:read', 'sort:write'])]
    protected string $direction = self::DESC;

    /**
     * 排序优先级（数字越小优先级越高）
     */
    #[Assert\Type(type: 'integer', message: '排序优先级必须是整数')]
    #[Assert\PositiveOrZero(message: '排序优先级不能为负数')]
    #[Groups(['sort:read', 'sort:write'])]
    protected int $priority = 0;

    /**
     * 排序别名（用于数据库查询时的字段映射）
     */
    #[Assert\Type(type: 'string', message: '排序别名必须是字符串')]
    #[Assert\Length(max: 100, maxMessage: '排序别名不能超过100个字符')]
    #[Groups(['sort:read', 'sort:write'])]
    protected ?string $alias = null;

    /**
     * 是否为自定义排序
     */
    #[Assert\Type(type: 'bool', message: '是否为自定义排序必须是布尔值')]
    #[Groups(['sort:read', 'sort:write'])]
    protected bool $custom = false;

    /**
     * 排序描述
     */
    #[Assert\Type(type: 'string', message: '排序描述必须是字符串')]
    #[Assert\Length(max: 255, maxMessage: '排序描述不能超过255个字符')]
    #[Groups(['sort:read', 'sort:write'])]
    protected ?string $description = null;

    /**
     * 可用的排序字段列表（用于验证）
     */
    #[Assert\Type(type: 'array', message: '可用字段列表必须是数组')]
    #[Groups(['sort:read', 'sort:write'])]
    protected array $availableFields = [];

    /**
     * 构造函数
     *
     * @param string $field 排序字段
     * @param string $direction 排序方向
     * @param int $priority 排序优先级
     * @param array $availableFields 可用字段列表
     */
    public function __construct(string $field = '', string $direction = self::DESC, int $priority = 0, array $availableFields = [])
    {
        $this->field = $field;
        $this->direction = strtolower($direction);
        $this->priority = $priority;
        $this->availableFields = $availableFields;
    }

    /**
     * 获取排序字段
     *
     * @return string
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * 设置排序字段
     *
     * @param string $field
     * @return self
     */
    public function setField(string $field): self
    {
        $this->field = $this->cleanString($field);
        return $this;
    }

    /**
     * 获取排序方向
     *
     * @return string
     */
    public function getDirection(): string
    {
        return $this->direction;
    }

    /**
     * 设置排序方向
     *
     * @param string $direction
     * @return self
     */
    public function setDirection(string $direction): self
    {
        $this->direction = strtolower($direction) === self::ASC ? self::ASC : self::DESC;
        return $this;
    }

    /**
     * 获取排序优先级
     *
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * 设置排序优先级
     *
     * @param int $priority
     * @return self
     */
    public function setPriority(int $priority): self
    {
        $this->priority = max(0, $priority);
        return $this;
    }

    /**
     * 获取排序别名
     *
     * @return string|null
     */
    public function getAlias(): ?string
    {
        return $this->alias;
    }

    /**
     * 设置排序别名
     *
     * @param string|null $alias
     * @return self
     */
    public function setAlias(?string $alias): self
    {
        $this->alias = $this->cleanString($alias);
        return $this;
    }

    /**
     * 是否为自定义排序
     *
     * @return bool
     */
    public function isCustom(): bool
    {
        return $this->custom;
    }

    /**
     * 设置是否为自定义排序
     *
     * @param bool $custom
     * @return self
     */
    public function setCustom(bool $custom): self
    {
        $this->custom = $custom;
        return $this;
    }

    /**
     * 获取排序描述
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * 设置排序描述
     *
     * @param string|null $description
     * @return self
     */
    public function setDescription(?string $description): self
    {
        $this->description = $this->cleanString($description);
        return $this;
    }

    /**
     * 获取可用字段列表
     *
     * @return array
     */
    public function getAvailableFields(): array
    {
        return $this->availableFields;
    }

    /**
     * 设置可用字段列表
     *
     * @param array $availableFields
     * @return self
     */
    public function setAvailableFields(array $availableFields): self
    {
        $this->availableFields = array_filter($availableFields, 'is_string');
        return $this;
    }

    /**
     * 检查字段是否在可用列表中
     *
     * @return bool
     */
    public function isFieldValid(): bool
    {
        if (empty($this->availableFields)) {
            return true; // 如果没有限制，则认为有效
        }

        return in_array($this->field, $this->availableFields) ||
               in_array($this->alias, $this->availableFields);
    }

    /**
     * 获取实际的排序字段（优先使用别名）
     *
     * @return string
     */
    public function getActualField(): string
    {
        return $this->alias ?: $this->field;
    }

    /**
     * 检查是否为升序
     *
     * @return bool
     */
    public function isAsc(): bool
    {
        return $this->direction === self::ASC;
    }

    /**
     * 检查是否为降序
     *
     * @return bool
     */
    public function isDesc(): bool
    {
        return $this->direction === self::DESC;
    }

    /**
     * 反转排序方向
     *
     * @return self
     */
    public function reverseDirection(): self
    {
        $this->direction = $this->isAsc() ? self::DESC : self::ASC;
        return $this;
    }

    /**
     * 切换为升序
     *
     * @return self
     */
    public function setAsc(): self
    {
        $this->direction = self::ASC;
        return $this;
    }

    /**
     * 切换为降序
     *
     * @return self
     */
    public function setDesc(): self
    {
        $this->direction = self::DESC;
        return $this;
    }

    /**
     * 获取排序字符串（用于SQL查询）
     *
     * @return string
     */
    public function toSqlString(): string
    {
        $field = $this->getActualField();
        $direction = strtoupper($this->direction);

        if ($this->custom) {
            return $field; // 自定义排序可能包含复杂表达式
        }

        return sprintf('%s %s', $field, $direction);
    }

    /**
     * 获取排序数组（用于QueryBuilder）
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'direction' => $this->direction,
            'priority' => $this->priority,
            'alias' => $this->alias,
            'custom' => $this->custom,
            'description' => $this->description,
            'availableFields' => $this->availableFields,
            'actualField' => $this->getActualField(),
            'isAsc' => $this->isAsc(),
            'isDesc' => $this->isDesc(),
            'sqlString' => $this->toSqlString(),
        ];
    }

    /**
     * 从数组创建实例
     *
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $field = $data['field'] ?? '';
        $direction = $data['direction'] ?? self::DESC;
        $priority = $data['priority'] ?? 0;
        $availableFields = $data['availableFields'] ?? [];

        $instance = new static($field, $direction, $priority, $availableFields);

        if (isset($data['alias'])) {
            $instance->setAlias($data['alias']);
        }

        if (isset($data['custom'])) {
            $instance->setCustom($data['custom']);
        }

        if (isset($data['description'])) {
            $instance->setDescription($data['description']);
        }

        return $instance;
    }

    /**
     * 创建升序排序
     *
     * @param string $field
     * @param int $priority
     * @param array $availableFields
     * @return static
     */
    public static function asc(string $field, int $priority = 0, array $availableFields = []): static
    {
        return new static($field, self::ASC, $priority, $availableFields);
    }

    /**
     * 创建降序排序
     *
     * @param string $field
     * @param int $priority
     * @param array $availableFields
     * @return static
     */
    public static function desc(string $field, int $priority = 0, array $availableFields = []): static
    {
        return new static($field, self::DESC, $priority, $availableFields);
    }

    /**
     * 从字符串解析排序（格式：field:asc 或 field:desc）
     *
     * @param string $sortString
     * @param int $priority
     * @param array $availableFields
     * @return static
     */
    public static function fromString(string $sortString, int $priority = 0, array $availableFields = []): static
    {
        $parts = explode(':', $sortString, 2);
        $field = trim($parts[0]);
        $direction = isset($parts[1]) ? trim($parts[1]) : self::DESC;

        return new static($field, $direction, $priority, $availableFields);
    }

    /**
     * 克隆并修改字段
     *
     * @param string $newField
     * @return static
     */
    public function withField(string $newField): static
    {
        $clone = clone $this;
        $clone->setField($newField);
        return $clone;
    }

    /**
     * 克隆并修改方向
     *
     * @param string $newDirection
     * @return static
     */
    public function withDirection(string $newDirection): static
    {
        $clone = clone $this;
        $clone->setDirection($newDirection);
        return $clone;
    }

    /**
     * 克隆并修改优先级
     *
     * @param int $newPriority
     * @return static
     */
    public function withPriority(int $newPriority): static
    {
        $clone = clone $this;
        $clone->setPriority($newPriority);
        return $clone;
    }

    /**
     * 获取排序的字符串表示
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toSqlString();
    }

    /**
     * 验证排序对象
     *
     * @param array $data 额外的验证数据
     * @return array 验证错误数组
     */
    public function validate(array $data = []): array
    {
        $errors = [];

        if (empty($this->field)) {
            $errors['field'] = '排序字段不能为空';
        }

        if (!in_array($this->direction, [self::ASC, self::DESC])) {
            $errors['direction'] = '排序方向必须是asc或desc';
        }

        if (!$this->isFieldValid()) {
            $errors['field'] = sprintf('排序字段"%s"不在可用字段列表中', $this->field);
        }

        return $errors;
    }

    /**
     * 获取排序摘要信息
     *
     * @return array
     */
    public function getSummary(): array
    {
        return [
            'field' => $this->field,
            'direction' => $this->direction,
            'priority' => $this->priority,
            'description' => $this->description ?: sprintf('按%s%s排序', $this->field, $this->isAsc() ? '升序' : '降序'),
            'custom' => $this->custom,
        ];
    }
}
