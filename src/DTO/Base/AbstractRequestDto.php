<?php

namespace App\DTO\Base;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * 抽象请求DTO基类
 * 用于处理API请求数据的传输对象
 */
abstract class AbstractRequestDto extends AbstractDto
{
    /**
     * 请求时间戳
     */
    #[Assert\Type(type: 'integer', message: '时间戳必须是整数')]
    #[Groups(['request:read', 'request:write'])]
    protected ?int $timestamp = null;

    /**
     * 请求唯一标识
     */
    #[Assert\Type(type: 'string', message: '请求ID必须是字符串')]
    #[Assert\Length(max: 100, maxMessage: '请求ID不能超过100个字符')]
    #[Groups(['request:read', 'request:write'])]
    protected ?string $requestId = null;

    /**
     * 客户端版本
     */
    #[Assert\Type(type: 'string', message: '客户端版本必须是字符串')]
    #[Assert\Length(max: 50, maxMessage: '客户端版本不能超过50个字符')]
    #[Groups(['request:read', 'request:write'])]
    protected ?string $clientVersion = null;

    /**
     * 设备信息
     */
    #[Assert\Type(type: 'string', message: '设备信息必须是字符串')]
    #[Assert\Length(max: 255, maxMessage: '设备信息不能超过255个字符')]
    #[Groups(['request:read', 'request:write'])]
    protected ?string $deviceInfo = null;

    /**
     * 用户代理
     */
    #[Assert\Type(type: 'string', message: '用户代理必须是字符串')]
    #[Assert\Length(max: 500, maxMessage: '用户代理不能超过500个字符')]
    #[Groups(['request:read', 'request:write'])]
    protected ?string $userAgent = null;

    /**
     * IP地址
     */
    #[Assert\Type(type: 'string', message: 'IP地址必须是字符串')]
    #[Assert\Ip(message: 'IP地址格式不正确')]
    #[Groups(['request:read', 'request:write'])]
    protected ?string $ipAddress = null;

    /**
     * 请求来源
     */
    #[Assert\Type(type: 'string', message: '请求来源必须是字符串')]
    #[Assert\Length(max: 100, maxMessage: '请求来源不能超过100个字符')]
    #[Groups(['request:read', 'request:write'])]
    protected ?string $source = null;

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

        // 自动设置时间戳
        if ($this->timestamp === null) {
            $this->timestamp = time();
        }

        // 自动生成请求ID
        if ($this->requestId === null) {
            $this->requestId = $this->generateRequestId();
        }
    }

    /**
     * 生成唯一的请求ID
     *
     * @return string
     */
    protected function generateRequestId(): string
    {
        return uniqid('req_', true) . '_' . mt_rand(1000, 9999);
    }

    /**
     * 获取时间戳
     *
     * @return int|null
     */
    public function getTimestamp(): ?int
    {
        return $this->timestamp;
    }

    /**
     * 设置时间戳
     *
     * @param int|null $timestamp
     * @return self
     */
    public function setTimestamp(?int $timestamp): self
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    /**
     * 获取请求ID
     *
     * @return string|null
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * 设置请求ID
     *
     * @param string|null $requestId
     * @return self
     */
    public function setRequestId(?string $requestId): self
    {
        $this->requestId = $this->cleanString($requestId);
        return $this;
    }

    /**
     * 获取客户端版本
     *
     * @return string|null
     */
    public function getClientVersion(): ?string
    {
        return $this->clientVersion;
    }

    /**
     * 设置客户端版本
     *
     * @param string|null $clientVersion
     * @return self
     */
    public function setClientVersion(?string $clientVersion): self
    {
        $this->clientVersion = $this->cleanString($clientVersion);
        return $this;
    }

    /**
     * 获取设备信息
     *
     * @return string|null
     */
    public function getDeviceInfo(): ?string
    {
        return $this->deviceInfo;
    }

    /**
     * 设置设备信息
     *
     * @param string|null $deviceInfo
     * @return self
     */
    public function setDeviceInfo(?string $deviceInfo): self
    {
        $this->deviceInfo = $this->cleanString($deviceInfo);
        return $this;
    }

    /**
     * 获取用户代理
     *
     * @return string|null
     */
    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    /**
     * 设置用户代理
     *
     * @param string|null $userAgent
     * @return self
     */
    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $this->cleanString($userAgent);
        return $this;
    }

    /**
     * 获取IP地址
     *
     * @return string|null
     */
    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    /**
     * 设置IP地址
     *
     * @param string|null $ipAddress
     * @return self
     */
    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $this->cleanString($ipAddress);
        return $this;
    }

    /**
     * 获取请求来源
     *
     * @return string|null
     */
    public function getSource(): ?string
    {
        return $this->source;
    }

    /**
     * 设置请求来源
     *
     * @param string|null $source
     * @return self
     */
    public function setSource(?string $source): self
    {
        $this->source = $this->cleanString($source);
        return $this;
    }

    /**
     * 验证请求数据
     *
     * @return array 验证错误数组
     */
    public function validateRequest(): array
    {
        $errors = [];

        // 验证时间戳
        if ($this->timestamp !== null && !$this->validateIntRange($this->timestamp, 0, PHP_INT_MAX)) {
            $errors['timestamp'] = '时间戳格式不正确';
        }

        // 验证请求ID
        if ($this->requestId !== null && !$this->validateStringLength($this->requestId, 1, 100)) {
            $errors['requestId'] = '请求ID长度必须在1-100个字符之间';
        }

        // 验证客户端版本
        if ($this->clientVersion !== null && !$this->validateStringLength($this->clientVersion, 0, 50)) {
            $errors['clientVersion'] = '客户端版本不能超过50个字符';
        }

        // 验证设备信息
        if ($this->deviceInfo !== null && !$this->validateStringLength($this->deviceInfo, 0, 255)) {
            $errors['deviceInfo'] = '设备信息不能超过255个字符';
        }

        // 验证用户代理
        if ($this->userAgent !== null && !$this->validateStringLength($this->userAgent, 0, 500)) {
            $errors['userAgent'] = '用户代理不能超过500个字符';
        }

        // 验证IP地址
        if ($this->ipAddress !== null && !$this->validateIp($this->ipAddress)) {
            $errors['ipAddress'] = 'IP地址格式不正确';
        }

        // 验证请求来源
        if ($this->source !== null && !$this->validateStringLength($this->source, 0, 100)) {
            $errors['source'] = '请求来源不能超过100个字符';
        }

        return $errors;
    }

    /**
     * 验证IP地址格式
     *
     * @param string $ip
     * @return bool
     */
    protected function validateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * 检查请求是否过期
     *
     * @param int $maxAge 最大有效时间（秒）
     * @return bool
     */
    public function isExpired(int $maxAge = 300): bool
    {
        if ($this->timestamp === null) {
            return false;
        }

        return (time() - $this->timestamp) > $maxAge;
    }

    /**
     * 获取请求年龄（秒）
     *
     * @return int
     */
    public function getAge(): int
    {
        if ($this->timestamp === null) {
            return 0;
        }

        return time() - $this->timestamp;
    }

    /**
     * 获取格式化的请求时间
     *
     * @param string $format
     * @return string|null
     */
    public function getFormattedTime(string $format = 'Y-m-d H:i:s'): ?string
    {
        if ($this->timestamp === null) {
            return null;
        }

        return date($format, $this->timestamp);
    }

    /**
     * 设置从HTTP请求头信息
     *
     * @param array $headers
     * @return self
     */
    public function setFromHeaders(array $headers): self
    {
        if (isset($headers['User-Agent'])) {
            $this->setUserAgent($headers['User-Agent']);
        }

        if (isset($headers['X-Client-Version'])) {
            $this->setClientVersion($headers['X-Client-Version']);
        }

        if (isset($headers['X-Device-Info'])) {
            $this->setDeviceInfo($headers['X-Device-Info']);
        }

        if (isset($headers['X-Request-Source'])) {
            $this->setSource($headers['X-Request-Source']);
        }

        return $this;
    }

    /**
     * 获取请求摘要信息
     *
     * @return array
     */
    public function getRequestSummary(): array
    {
        return [
            'requestId' => $this->requestId,
            'timestamp' => $this->timestamp,
            'formattedTime' => $this->getFormattedTime(),
            'age' => $this->getAge(),
            'source' => $this->source,
            'ipAddress' => $this->ipAddress,
            'clientVersion' => $this->clientVersion,
        ];
    }

    /**
     * 转换为数组（包含基础请求信息）
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_merge($this->getPublicProperties(), [
            'timestamp' => $this->timestamp,
            'requestId' => $this->requestId,
            'clientVersion' => $this->clientVersion,
            'deviceInfo' => $this->deviceInfo,
            'userAgent' => $this->userAgent,
            'ipAddress' => $this->ipAddress,
            'source' => $this->source,
        ]);
    }

    /**
     * 从数组创建实例（包含基础请求信息）
     *
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $instance = new static();

        // 设置基础属性
        if (isset($data['timestamp'])) {
            $instance->setTimestamp($data['timestamp']);
        }

        if (isset($data['requestId'])) {
            $instance->setRequestId($data['requestId']);
        }

        if (isset($data['clientVersion'])) {
            $instance->setClientVersion($data['clientVersion']);
        }

        if (isset($data['deviceInfo'])) {
            $instance->setDeviceInfo($data['deviceInfo']);
        }

        if (isset($data['userAgent'])) {
            $instance->setUserAgent($data['userAgent']);
        }

        if (isset($data['ipAddress'])) {
            $instance->setIpAddress($data['ipAddress']);
        }

        if (isset($data['source'])) {
            $instance->setSource($data['source']);
        }

        // 设置其他公共属性
        $instance->setPublicProperties($data);

        return $instance;
    }
}
