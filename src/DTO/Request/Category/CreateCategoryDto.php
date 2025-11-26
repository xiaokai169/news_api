<?php

namespace App\DTO\Request\Category;

use App\DTO\Base\AbstractRequestDto;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

/**
 * 创建分类请求DTO
 */
#[OA\Schema(
    schema: 'CreateCategoryDto',
    title: '创建分类请求',
    description: '用于创建新闻文章分类的请求数据传输对象'
)]
class CreateCategoryDto extends AbstractRequestDto
{
    /**
     * 分类编码
     */
    #[Assert\NotBlank(message: '分类编码不能为空')]
    #[Assert\Length(max: 255, maxMessage: '分类编码不能超过255个字符')]
    #[OA\Property(
        description: '分类编码，唯一标识',
        example: 'tech_news',
        maxLength: 255
    )]
    protected string $code = '';

    /**
     * 分类名称
     */
    #[Assert\NotBlank(message: '分类名称不能为空')]
    #[Assert\Length(max: 255, maxMessage: '分类名称不能超过255个字符')]
    #[OA\Property(
        description: '分类显示名称',
        example: '科技新闻',
        maxLength: 255
    )]
    protected string $name = '';

    /**
     * 创建者
     */
    #[Assert\Length(max: 255, maxMessage: '创建者名称不能超过255个字符')]
    #[OA\Property(
        description: '分类创建者',
        example: 'admin',
        maxLength: 255
    )]
    protected string $creator = '';

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
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * 设置分类编码
     *
     * @param string $code
     * @return self
     */
    public function setCode(string $code): self
    {
        $this->code = $this->cleanString($code);
        return $this;
    }

    /**
     * 获取分类名称
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 设置分类名称
     *
     * @param string $name
     * @return self
     */
    public function setName(string $name): self
    {
        $this->name = $this->cleanString($name);
        return $this;
    }

    /**
     * 获取创建者
     *
     * @return string
     */
    public function getCreator(): string
    {
        return $this->creator;
    }

    /**
     * 设置创建者
     *
     * @param string $creator
     * @return self
     */
    public function setCreator(string $creator): self
    {
        $this->creator = $this->cleanString($creator);
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
     * 验证创建数据
     *
     * @return array 验证错误数组
     */
    public function validateCreateData(): array
    {
        $errors = [];

        // 验证分类编码
        if (empty($this->code)) {
            $errors['code'] = '分类编码不能为空';
        } elseif (strlen($this->code) > 255) {
            $errors['code'] = '分类编码不能超过255个字符';
        }

        // 验证分类名称
        if (empty($this->name)) {
            $errors['name'] = '分类名称不能为空';
        } elseif (strlen($this->name) > 255) {
            $errors['name'] = '分类名称不能超过255个字符';
        }

        // 验证创建者
        if (strlen($this->creator) > 255) {
            $errors['creator'] = '创建者名称不能超过255个字符';
        }

        return array_merge($errors, $this->validateRequest());
    }

    /**
     * 检查是否有有效的分类数据
     *
     * @return bool
     */
    public function hasValidCategoryData(): bool
    {
        return !empty($this->code) && !empty($this->name);
    }

    /**
     * 获取创建分类的摘要信息
     *
     * @return array
     */
    public function getCreateSummary(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'creator' => $this->creator ?: '系统',
            'hasValidData' => $this->hasValidCategoryData(),
            'requestSummary' => $this->getRequestSummary(),
        ];
    }
}
