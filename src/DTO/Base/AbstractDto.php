<?php

namespace App\DTO\Base;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * 抽象DTO基类
 * 定义所有DTO的通用方法和属性
 */
abstract class AbstractDto
{
    /**
     * 将DTO转换为数组
     *
     * @return array
     */
    abstract public function toArray(): array;

    /**
     * 从数组创建DTO实例
     *
     * @param array $data
     * @return static
     */
    abstract public static function fromArray(array $data): static;

    /**
     * 验证DTO数据
     *
     * @param array $data
     * @return array 验证错误数组，空数组表示验证通过
     */
    public function validate(array $data): array
    {
        // 这里可以添加通用的验证逻辑
        // 具体验证由Symfony Validator组件处理
        return [];
    }

    /**
     * 获取DTO的公共属性
     *
     * @return array
     */
    public function getPublicProperties(): array
    {
        $reflection = new \ReflectionClass($this);
        $properties = [];

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();
            $properties[$propertyName] = $this->$propertyName;
        }

        return $properties;
    }

    /**
     * 设置DTO的公共属性
     *
     * @param array $data
     * @return self
     */
    public function setPublicProperties(array $data): self
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        return $this;
    }

    /**
     * 将DateTime对象转换为字符串
     *
     * @param \DateTimeInterface|null $dateTime
     * @param string $format
     * @return string|null
     */
    protected function formatDateTime(?\DateTimeInterface $dateTime, string $format = 'Y-m-d H:i:s'): ?string
    {
        return $dateTime ? $dateTime->format($format) : null;
    }

    /**
     * 将字符串转换为DateTime对象
     *
     * @param string|null $dateTimeString
     * @return \DateTimeInterface|null
     * @throws \Exception
     */
    protected function parseDateTime(?string $dateTimeString): ?\DateTimeInterface
    {
        if (empty($dateTimeString)) {
            return null;
        }

        return new \DateTime($dateTimeString);
    }

    /**
     * 清理字符串数据（去除前后空格）
     *
     * @param string|null $value
     * @return string
     */
    protected function cleanString(?string $value): string
    {
        return trim($value ?? '');
    }

    /**
     * 转换为JSON字符串
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 从JSON字符串创建DTO实例
     *
     * @param string $json
     * @return static
     * @throws \Exception
     */
    public static function fromJson(string $json): static
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('无效的JSON数据: ' . json_last_error_msg());
        }

        return static::fromArray($data);
    }

    /**
     * 检查属性是否存在且不为空
     *
     * @param string $propertyName
     * @return bool
     */
    protected function hasValue(string $propertyName): bool
    {
        if (!property_exists($this, $propertyName)) {
            return false;
        }

        $value = $this->$propertyName;

        if ($value === null || $value === '' || $value === []) {
            return false;
        }

        return true;
    }

    /**
     * 获取默认值如果属性为空
     *
     * @param mixed $value
     * @param mixed $default
     * @return mixed
     */
    protected function valueOrDefault($value, $default)
    {
        return $value !== null && $value !== '' ? $value : $default;
    }

    /**
     * 验证整数范围
     *
     * @param int $value
     * @param int $min
     * @param int $max
     * @return bool
     */
    protected function validateIntRange(int $value, int $min, int $max): bool
    {
        return $value >= $min && $value <= $max;
    }

    /**
     * 验证字符串长度
     *
     * @param string $value
     * @param int $min
     * @param int $max
     * @return bool
     */
    protected function validateStringLength(string $value, int $min = 0, int $max = 255): bool
    {
        $length = mb_strlen($value, 'UTF-8');
        return $length >= $min && $length <= $max;
    }

    /**
     * 验证邮箱格式
     *
     * @param string $email
     * @return bool
     */
    protected function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * 验证URL格式
     *
     * @param string $url
     * @return bool
     */
    protected function validateUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * 验证手机号格式（中国大陆）
     *
     * @param string $phone
     * @return bool
     */
    protected function validatePhone(string $phone): bool
    {
        return preg_match('/^1[3-9]\d{9}$/', $phone) === 1;
    }

    /**
     * 克隆DTO对象
     *
     * @return static
     */
    public function clone(): static
    {
        return clone $this;
    }

    /**
     * 合并另一个DTO的数据
     *
     * @param AbstractDto $other
     * @return self
     */
    public function merge(AbstractDto $other): self
    {
        if (get_class($other) !== get_class($this)) {
            throw new \InvalidArgumentException('只能合并相同类型的DTO对象');
        }

        $otherData = $other->toArray();
        return $this->setPublicProperties($otherData);
    }

    /**
     * 获取DTO类型的简短名称
     *
     * @return string
     */
    public function getShortName(): string
    {
        $className = static::class;
        return substr($className, strrpos($className, '\\') + 1);
    }

    /**
     * 获取DTO的完整类名
     *
     * @return string
     */
    public function getClassName(): string
    {
        return static::class;
    }
}
