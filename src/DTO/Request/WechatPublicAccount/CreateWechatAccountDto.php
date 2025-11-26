<?php

namespace App\DTO\Request\WechatPublicAccount;

use App\DTO\Base\AbstractRequestDto;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * 创建微信公众号请求DTO
 *
 * 用于创建微信公众号时的请求数据传输对象
 * 包含创建公众号所需的所有字段和验证约束
 */
#[OA\Schema(
    title: '创建微信公众号请求DTO',
    description: '创建微信公众号的请求数据结构',
    required: ['name', 'appId', 'appSecret']
)]
class CreateWechatAccountDto extends AbstractRequestDto
{
    /**
     * 公众号名称
     */
    #[Assert\NotBlank(message: '公众号名称不能为空')]
    #[Assert\Length(max: 255, maxMessage: '公众号名称不能超过255个字符')]
    #[OA\Property(
        description: '公众号名称',
        maxLength: 255,
        example: '官方公众号'
    )]
    #[Groups(['createWechatAccount:read', 'createWechatAccount:write'])]
    public string $name = '';

    /**
     * 公众号描述
     */
    #[Assert\Length(max: 1000, maxMessage: '公众号描述不能超过1000个字符')]
    #[OA\Property(
        description: '公众号描述',
        maxLength: 1000,
        example: '这是我们的官方微信公众号，提供最新资讯和服务'
    )]
    #[Groups(['createWechatAccount:read', 'createWechatAccount:write'])]
    public string $description = '';

    /**
     * 头像URL
     */
    #[Assert\Url(message: '头像URL格式不正确')]
    #[Assert\Length(max: 500, maxMessage: '头像URL不能超过500个字符')]
    #[OA\Property(
        description: '公众号头像URL',
        maxLength: 500,
        example: 'https://example.com/avatar.jpg'
    )]
    #[Groups(['createWechatAccount:read', 'createWechatAccount:write'])]
    public string $avatarUrl = '';

    /**
     * 微信AppId
     */
    #[Assert\NotBlank(message: 'AppId不能为空')]
    #[Assert\Length(max: 128, maxMessage: 'AppId不能超过128个字符')]
    #[Assert\Regex(pattern: '/^wx[a-f0-9]{16}$/', message: 'AppId格式不正确，应以wx开头后跟16位十六进制字符')]
    #[OA\Property(
        description: '微信公众号AppId',
        maxLength: 128,
        example: 'wx1234567890abcdef'
    )]
    #[Groups(['createWechatAccount:read', 'createWechatAccount:write'])]
    public string $appId = '';

    /**
     * 微信AppSecret
     */
    #[Assert\NotBlank(message: 'AppSecret不能为空')]
    #[Assert\Length(max: 128, maxMessage: 'AppSecret不能超过128个字符')]
    #[Assert\Regex(pattern: '/^[a-f0-9]{32}$/', message: 'AppSecret格式不正确，应为32位十六进制字符')]
    #[OA\Property(
        description: '微信公众号AppSecret',
        maxLength: 128,
        example: '1234567890abcdef1234567890abcdef'
    )]
    #[Groups(['createWechatAccount:read', 'createWechatAccount:write'])]
    public string $appSecret = '';

    /**
     * 是否激活
     */
    #[Assert\Type(type: 'bool', message: '是否激活必须是布尔值')]
    #[OA\Property(
        description: '是否激活公众号',
        example: true
    )]
    #[Groups(['createWechatAccount:read', 'createWechatAccount:write'])]
    public bool $isActive = true;

    /**
     * 微信Token（可选）
     */
    #[Assert\Length(max: 32, maxMessage: 'Token不能超过32个字符')]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9_\-]+$/', message: 'Token只能包含字母、数字、下划线和连字符')]
    #[OA\Property(
        description: '微信Token（用于消息验证）',
        maxLength: 32,
        example: 'your_token_here'
    )]
    #[Groups(['createWechatAccount:read', 'createWechatAccount:write'])]
    public string $token = '';

    /**
     * 消息加密密钥（可选）
     */
    #[Assert\Length(max: 128, maxMessage: '消息加密密钥不能超过128个字符')]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9]{43}$/', message: '消息加密密钥格式不正确，应为43位字母数字')]
    #[OA\Property(
        description: '消息加密密钥（EncodingAESKey）',
        maxLength: 128,
        example: 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ123'
    )]
    #[Groups(['createWechatAccount:read', 'createWechatAccount:write'])]
    public string $encodingAESKey = '';

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
        if (isset($data['name'])) {
            $this->name = $this->cleanString($data['name']);
        }

        if (isset($data['description'])) {
            $this->description = $this->cleanString($data['description']);
        }

        if (isset($data['avatarUrl'])) {
            $this->avatarUrl = $this->cleanString($data['avatarUrl']);
        }

        if (isset($data['appId'])) {
            $this->appId = $this->cleanString($data['appId']);
        }

        if (isset($data['appSecret'])) {
            $this->appSecret = $this->cleanString($data['appSecret']);
        }

        if (isset($data['isActive'])) {
            $this->isActive = (bool) $data['isActive'];
        }

        if (isset($data['token'])) {
            $this->token = $this->cleanString($data['token']);
        }

        if (isset($data['encodingAESKey'])) {
            $this->encodingAESKey = $this->cleanString($data['encodingAESKey']);
        }

        return $this;
    }

    /**
     * 验证AppId格式
     *
     * @return bool
     */
    public function isValidAppId(): bool
    {
        return preg_match('/^wx[a-f0-9]{16}$/', $this->appId) === 1;
    }

    /**
     * 验证AppSecret格式
     *
     * @return bool
     */
    public function isValidAppSecret(): bool
    {
        return preg_match('/^[a-f0-9]{32}$/', $this->appSecret) === 1;
    }

    /**
     * 验证Token格式
     *
     * @return bool
     */
    public function isValidToken(): bool
    {
        if (empty($this->token)) {
            return true; // Token是可选的
        }
        return preg_match('/^[a-zA-Z0-9_\-]+$/', $this->token) === 1;
    }

    /**
     * 验证EncodingAESKey格式
     *
     * @return bool
     */
    public function isValidEncodingAESKey(): bool
    {
        if (empty($this->encodingAESKey)) {
            return true; // EncodingAESKey是可选的
        }
        return preg_match('/^[a-zA-Z0-9]{43}$/', $this->encodingAESKey) === 1;
    }

    /**
     * 检查是否启用了消息加密
     *
     * @return bool
     */
    public function isMessageEncryptionEnabled(): bool
    {
        return !empty($this->encodingAESKey) && !empty($this->token);
    }

    /**
     * 获取状态描述
     *
     * @return string
     */
    public function getStatusDescription(): string
    {
        return $this->isActive ? '已激活' : '未激活';
    }

    /**
     * 获取加密状态描述
     *
     * @return string
     */
    public function getEncryptionStatusDescription(): string
    {
        if ($this->isMessageEncryptionEnabled()) {
            return '已启用消息加密';
        } elseif (!empty($this->token) || !empty($this->encodingAESKey)) {
            return '加密配置不完整';
        } else {
            return '未启用消息加密';
        }
    }

    /**
     * 验证业务逻辑
     *
     * @return array 验证错误数组
     */
    public function validateBusinessRules(): array
    {
        $errors = [];

        // 验证AppId格式
        if (!$this->isValidAppId()) {
            $errors['appId'] = 'AppId格式不正确，应以wx开头后跟16位十六进制字符';
        }

        // 验证AppSecret格式
        if (!$this->isValidAppSecret()) {
            $errors['appSecret'] = 'AppSecret格式不正确，应为32位十六进制字符';
        }

        // 验证Token格式
        if (!$this->isValidToken()) {
            $errors['token'] = 'Token格式不正确，只能包含字母、数字、下划线和连字符';
        }

        // 验证EncodingAESKey格式
        if (!$this->isValidEncodingAESKey()) {
            $errors['encodingAESKey'] = '消息加密密钥格式不正确，应为43位字母数字';
        }

        // 验证头像URL
        if (!empty($this->avatarUrl) && !$this->validateUrl($this->avatarUrl)) {
            $errors['avatarUrl'] = '头像URL格式不正确';
        }

        // 验证加密配置的完整性
        if (!empty($this->token) && empty($this->encodingAESKey)) {
            $errors['encryption'] = '设置了Token但未设置EncodingAESKey，加密配置不完整';
        }

        if (empty($this->token) && !empty($this->encodingAESKey)) {
            $errors['encryption'] = '设置了EncodingAESKey但未设置Token，加密配置不完整';
        }

        return $errors;
    }

    /**
     * 获取敏感信息（用于日志记录时隐藏敏感字段）
     *
     * @return array
     */
    public function getSafeData(): array
    {
        $data = $this->toArray();

        // 隐藏敏感信息
        if (isset($data['appSecret']) && !empty($data['appSecret'])) {
            $data['appSecret'] = substr($data['appSecret'], 0, 8) . '****';
        }

        if (isset($data['encodingAESKey']) && !empty($data['encodingAESKey'])) {
            $data['encodingAESKey'] = substr($data['encodingAESKey'], 0, 8) . '****';
        }

        return $data;
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
            'description' => $this->description,
            'avatarUrl' => $this->avatarUrl,
            'appId' => $this->appId,
            'appSecret' => $this->appSecret,
            'isActive' => $this->isActive,
            'token' => $this->token,
            'encodingAESKey' => $this->encodingAESKey,
            'statusDescription' => $this->getStatusDescription(),
            'encryptionStatusDescription' => $this->getEncryptionStatusDescription(),
            'isMessageEncryptionEnabled' => $this->isMessageEncryptionEnabled(),
            'isValidAppId' => $this->isValidAppId(),
            'isValidAppSecret' => $this->isValidAppSecret(),
            'isValidToken' => $this->isValidToken(),
            'isValidEncodingAESKey' => $this->isValidEncodingAESKey(),
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
