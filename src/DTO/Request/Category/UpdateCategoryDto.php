<?php

namespace App\DTO\Request\Category;

use App\DTO\Base\AbstractRequestDto;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

/**
 * 更新分类请求DTO
 */
#[OA\Schema(
    schema: 'UpdateCategoryDto',
    title: '更新分类请求',
    description: '用于更新新闻文章分类的请求数据传输对象'
)]
class UpdateCategoryDto extends AbstractRequestDto
{
    /**
     * 分类编码（可选）
     */
    #[Assert\Length(max: 255, maxMessage: '分类编码不能超过255个字符')]
    #[OA\Property(
        description: '分类编码，唯一标识（更新时可选）',
        example: 'tech_news_updated',
        maxLength: 255
    )]
    protected ?string $code = null;

    /**
     * 分类名称（可选）
     */
    #[Assert\Length(max: 255, maxMessage: '分类名称不能超过255个字符')]
    #[OA\Property(
        description: '分类显示名称（更新时可选）',
        example: '科技新闻更新版',
        maxLength: 255
    )]
    protected ?string $name = null;

    /**
     * 创建者（可选）
     */
    #[Assert\Length(max: 255, maxMessage: '创建者名称不能超过255个字符')]
    #[OA\Property(
        description: '分类创建者（更新时可选）',
        example: 'admin_updated',
        maxLength: 255
    )]
    protected ?string $creator = null;

    /**
     * 原始数据（用于比较更新）
     */
    protected ?array $originalData = null;

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
     * 获取分类编码
     *
     * @return string|null
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * 设置分类编码
     *
     * @param string|null $code
     * @return self
     */
    public function setCode(?string $code): self
    {
        $this->code = $code ? $this->cleanString($code) : null;
        return $this;
    }

    /**
     * 获取分类名称
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * 设置分类名称
     *
     * @param string|null $name
     * @return self
     */
    public function setName(?string $name): self
    {
        $this->name = $name ? $this->cleanString($name) : null;
        return $this;
    }

    /**
     * 获取创建者
     *
     * @return string|null
     */
    public function getCreator(): ?string
    {
        return $this->creator;
    }

    /**
     * 设置创建者
     *
     * @param string|null $creator
     * @return self
     */
    public function setCreator(?string $creator): self
    {
        $this->creator = $creator ? $this->cleanString($creator) : null;
        return $this;
    }

    /**
     * 获取原始数据
     *
     * @return array|null
     */
    public function getOriginalData(): ?array
    {
        return $this->originalData;
    }

    /**
     * 设置原始数据
     *
     * @param array|null $originalData
     * @return self
     */
    public function setOriginalData(?array $originalData): self
    {
        $this->originalData = $originalData;
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
        if (isset($data['code'])) {
            $this->setCode($data['code']);
        }

        if (isset($data['name'])) {
            $this->setName($data['name']);
        }

        if (isset($data['creator'])) {
            $this->setCreator($data['creator']);
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
            'code' => $this->code,
            'name' => $this->name,
            'creator' => $this->creator,
        ]);
    }

    /**
     * 检查是否有更新
     *
     * @return bool
     */
    public function hasUpdates(): bool
    {
        return !empty($this->getUpdatedFields());
    }

    /**
     * 获取已更新的字段
     *
     * @return array
     */
    public function getUpdatedFields(): array
    {
        $updatedFields = [];

        if ($this->code !== null && $this->code !== '') {
            $updatedFields['code'] = $this->code;
        }

        if ($this->name !== null && $this->name !== '') {
            $updatedFields['name'] = $this->name;
        }

        if ($this->creator !== null) {
            $updatedFields['creator'] = $this->creator;
        }

        return $updatedFields;
    }

    /**
     * 验证更新数据
     *
     * @return array 验证错误数组
     */
    public function validateUpdateData(): array
    {
        $errors = [];

        // 验证分类编码（如果提供）
        if ($this->code !== null) {
            if (empty($this->code)) {
                $errors['code'] = '分类编码不能为空';
            } elseif (strlen($this->code) > 255) {
                $errors['code'] = '分类编码不能超过255个字符';
            }
        }

        // 验证分类名称（如果提供）
        if ($this->name !== null) {
            if (empty($this->name)) {
                $errors['name'] = '分类名称不能为空';
            } elseif (strlen($this->name) > 255) {
                $errors['name'] = '分类名称不能超过255个字符';
            }
        }

        // 验证创建者（如果提供）
        if ($this->creator !== null && strlen($this->creator) > 255) {
            $errors['creator'] = '创建者名称不能超过255个字符';
        }

        return array_merge($errors, $this->validateRequest());
    }

    /**
     * 检查是否有有效的更新数据
     *
     * @return bool
     */
    public function hasValidUpdateData(): bool
    {
        return ($this->code !== null && $this->code !== '') ||
               ($this->name !== null && $this->name !== '') ||
               $this->creator !== null;
    }

    /**
     * 获取更新分类的摘要信息
     *
     * @return array
     */
    public function getUpdateSummary(): array
    {
        return [
            'updatedFields' => $this->getUpdatedFields(),
            'hasUpdates' => $this->hasUpdates(),
            'hasValidData' => $this->hasValidUpdateData(),
            'requestSummary' => $this->getRequestSummary(),
        ];
    }

    /**
     * 比较与原始数据的差异
     *
     * @param array $originalData 原始数据
     * @return array 差异数组
     */
    public function getDifferences(array $originalData): array
    {
        $differences = [];

        if (isset($originalData['code']) && $this->code !== null && $this->code !== $originalData['code']) {
            $differences['code'] = [
                'old' => $originalData['code'],
                'new' => $this->code
            ];
        }

        if (isset($originalData['name']) && $this->name !== null && $this->name !== $originalData['name']) {
            $differences['name'] = [
                'old' => $originalData['name'],
                'new' => $this->name
            ];
        }

        if (isset($originalData['creator']) && $this->creator !== null && $this->creator !== $originalData['creator']) {
            $differences['creator'] = [
                'old' => $originalData['creator'],
                'new' => $this->creator
            ];
        }

        return $differences;
    }

    /**
     * 应用更新到原始数据
     *
     * @param array $originalData 原始数据
     * @return array 更新后的数据
     */
    public function applyToOriginal(array $originalData): array
    {
        $updatedData = $originalData;

        if ($this->code !== null) {
            $updatedData['code'] = $this->code;
        }

        if ($this->name !== null) {
            $updatedData['name'] = $this->name;
        }

        if ($this->creator !== null) {
            $updatedData['creator'] = $this->creator;
        }

        return $updatedData;
    }
}
