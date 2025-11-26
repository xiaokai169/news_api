<?php

namespace App\DTO\Request\WechatPublicAccount;

use App\DTO\Base\AbstractRequestDto;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * 更新微信公众号请求DTO
 *
 * 用于更新微信公众号时的请求数据传输对象
 * 包含更新公众号的字段（大部分字段可选）
 */
#[OA\Schema(
    title: '更新微信公众号请求DTO',
    description: '更新微信公众号的请求数据结构',
    required: [] // 所有字段都是可选的
)]
class UpdateWechatAccountDto extends AbstractRequestDto
{
    /**
     * 公众号名称
     */
    #[Assert\Length(max: 255, maxMessage: '公众号名称不能超过255个字符')]
    #[OA\Property(
        description: '公众号名称',
        example: '官方公众号更新版',
        maxLength: 255
    )]
    #[Groups(['updateWechatAccount:read', 'updateWechatAccount:write'])]
    public ?string $name = null;

    /**
     * 公众号描述
     */
    #[Assert\Length(max: 1000, maxMessage: '公众号描述不能超过1000个字符')]
    #[OA\Property(
        description: '公众号描述',
        example: '这是更新后的官方微信公众号描述',
        maxLength: 1000
    )]
    #[Groups(['updateWechatAccount:read', 'updateWechatAccount:write'])]
    public ?string $description = null;

    /**
     * 头像URL
     */
    #[Assert\Url(message: '头像URL格式不正确')]
    #[Assert\Length(max: 500, maxMessage: '头像URL不能超过500个字符')]
    #[OA\Property(
        description: '公众号头像URL',
        example: 'https://example.com/new-avatar.jpg',
        maxLength: 500
    )]
    #[Groups(['updateWechatAccount:read', 'updateWechatAccount:write'])]
    public ?string $avatarUrl = null;

    /**
     * 微信AppId
     */
    #[Assert\Length(max: 128, maxMessage: 'AppId不能超过128个字符')]
    #[Assert\Regex(pattern: '/^wx[a-f0-9]{16}$/', message: 'AppId格式不正确，应以wx开头后跟16位十六进制字符')]
    #[OA\Property(
        description: '微信公众号AppId',
        example: 'wx1234567890abcdef',
        maxLength: 128
    )]
    #[Groups(['updateWechatAccount:read', 'updateWechatAccount:write'])]
    public ?string $appId = null;

    /**
     * 微信AppSecret
     */
    #[Assert\Length(max: 128, maxMessage: 'AppSecret不能超过128个字符')]
    #[Assert\Regex(pattern: '/^[a-f0-9]{32}$/', message: 'AppSecret格式不正确，应为32位十六进制字符')]
    #[OA\Property(
        description: '微信公众号AppSecret',
        example: '1234567890abcdef1234567890abcdef',
        maxLength: 128
    )]
    #[Groups(['updateWechatAccount:read', 'updateWechatAccount:write'])]
    public ?string $appSecret = null;

    /**
     * 是否激活
     */
    #[Assert\Type(type: 'bool', message: '是否激活必须是布尔值')]
    #[OA\Property(
        description: '是否激活公众号',
        example: false
    )]
    #[Groups(['updateWechatAccount:read', 'updateWechatAccount:write'])]
    public ?bool $isActive = null;

    /**
     * 微信Token（可选）
     */
    #[Assert\Length(max: 32, maxMessage: 'Token不能超过32个字符')]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9_\-]+$/', message: 'Token只能包含字母、数字、下划线和连字符')]
    #[OA\Property(
        description: '微信Token（用于消息验证）',
        example: 'new_token_here',
        maxLength: 32
    )]
    #[Groups(['updateWechatAccount:read', 'updateWechatAccount:write'])]
    public ?string $token = null;

    /**
     * 消息加密密钥（可选）
     */
    #[Assert\Length(max: 128, maxMessage: '消息加密密钥不能超过128个字符')]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9]{43}$/', message: '消息加密密钥格式不正确，应为43位字母数字')]
    #[OA\Property(
        description: '消息加密密钥（EncodingAESKey）',
        example: 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ123',
        maxLength: 128
    )]
    #[Groups(['updateWechatAccount:read', 'updateWechatAccount:write'])]
    public ?string $encodingAESKey = null;

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
            $this->name = $data['name'] !== null ? $this->cleanString($data['name']) : null;
        }

        if (isset($data['description'])) {
            $this->description = $data['description'] !== null ? $this->cleanString($data['description']) : null;
        }

        if (isset($data['avatarUrl'])) {
            $this->avatarUrl = $data['avatarUrl'] !== null ? $this->cleanString($data['avatarUrl']) : null;
        }

        if (isset($data['appId'])) {
            $this->appId = $data['appId'] !== null ? $this->cleanString($data['appId']) : null;
        }

        if (isset($data['appSecret'])) {
            $this->appSecret = $data['appSecret'] !== null ? $this->cleanString($data['appSecret']) : null;
        }

        if (isset($data['isActive'])) {
            $this->isActive = $data['isActive'] !== null ? (bool) $data['isActive'] : null;
        }

        if (isset($data['token'])) {
            $this->token = $data['token'] !== null ? $this->cleanString($data['token']) : null;
        }

        if (isset($data['encodingAESKey'])) {
            $this->encodingAESKey = $data['encodingAESKey'] !== null ? $this->cleanString($data['encodingAESKey']) : null;
        }

        return $this;
    }

    /**
     * 验证AppId格式
     *
     * @return bool|null
     */
    public function isValidAppId(): ?bool
    {
        if ($this->appId === null) {
            return null;
        }
        return preg_match('/^wx[a-f0-9]{16}$/', $this->appId) === 1;
    }

    /**
     * 验证AppSecret格式
     *
     * @return bool|null
     */
    public function isValidAppSecret(): ?bool
    {
        if ($this->appSecret === null) {
            return null;
        }
        return preg_match('/^[a-f0-9]{32}$/', $this->appSecret) === 1;
    }

    /**
     * 验证Token格式
     *
     * @return bool|null
     */
    public function isValidToken(): ?bool
    {
        if ($this->token === null) {
            return null;
        }

        if (empty($this->token)) {
            return true; // 空Token是有效的
        }
        return preg_match('/^[a-zA-Z0-9_\-]+$/', $this->token) === 1;
    }

    /**
     * 验证EncodingAESKey格式
     *
     * @return bool|null
     */
    public function isValidEncodingAESKey(): ?bool
    {
        if ($this->encodingAESKey === null) {
            return null;
        }

        if (empty($this->encodingAESKey)) {
            return true; // 空EncodingAESKey是有效的
        }
        return preg_match('/^[a-zA-Z0-9]{43}$/', $this->encodingAESKey) === 1;
    }

    /**
     * 检查是否启用了消息加密
     *
     * @return bool|null
     */
    public function isMessageEncryptionEnabled(): ?bool
    {
        if ($this->token === null && $this->encodingAESKey === null) {
            return null;
        }

        $token = $this->token ?? '';
        $encodingKey = $this->encodingAESKey ?? '';

        return !empty($token) && !empty($encodingKey);
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
        if ($this->token === null && $this->encodingAESKey === null) {
            return null;
        }

        if ($this->isMessageEncryptionEnabled()) {
            return '已启用消息加密';
        } elseif (!empty($this->token) || !empty($this->encodingAESKey)) {
            return '加密配置不完整';
        } else {
            return '未启用消息加密';
        }
    }

    /**
     * 检查是否有任何字段被更新
     *
     * @return bool
     */
    public function hasUpdates(): bool
    {
        $fields = [
            'name', 'description', 'avatarUrl', 'appId', 'appSecret',
            'isActive', 'token', 'encodingAESKey'
        ];

        foreach ($fields as $field) {
            if ($this->$field !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取已更新的字段列表
     *
     * @return array
     */
    public function getUpdatedFields(): array
    {
        $updatedFields = [];
        $fields = [
            'name', 'description', 'avatarUrl', 'appId', 'appSecret',
            'isActive', 'token', 'encodingAESKey'
        ];

        foreach ($fields as $field) {
            if ($this->$field !== null) {
                $updatedFields[$field] = $this->$field;
            }
        }

        return $updatedFields;
    }

    /**
     * 获取敏感字段的更新状态
     *
     * @return array
     */
    public function getSensitiveFieldUpdates(): array
    {
        $sensitiveFields = ['appId', 'appSecret', 'token', 'encodingAESKey'];
        $updates = [];

        foreach ($sensitiveFields as $field) {
            if ($this->$field !== null) {
                $updates[$field] = [
                    'updated' => true,
                    'value' => $this->$field,
                    'isValid' => match($field) {
                        'appId' => $this->isValidAppId(),
                        'appSecret' => $this->isValidAppSecret(),
                        'token' => $this->isValidToken(),
                        'encodingAESKey' => $this->isValidEncodingAESKey(),
                        default => null
                    }
                ];
            }
        }

        return $updates;
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
        if ($this->appId !== null && !$this->isValidAppId()) {
            $errors['appId'] = 'AppId格式不正确，应以wx开头后跟16位十六进制字符';
        }

        // 验证AppSecret格式
        if ($this->appSecret !== null && !$this->isValidAppSecret()) {
            $errors['appSecret'] = 'AppSecret格式不正确，应为32位十六进制字符';
        }

        // 验证Token格式
        if ($this->token !== null && !$this->isValidToken()) {
            $errors['token'] = 'Token格式不正确，只能包含字母、数字、下划线和连字符';
        }

        // 验证EncodingAESKey格式
        if ($this->encodingAESKey !== null && !$this->isValidEncodingAESKey()) {
            $errors['encodingAESKey'] = '消息加密密钥格式不正确，应为43位字母数字';
        }

        // 验证头像URL
        if ($this->avatarUrl !== null && !$this->validateUrl($this->avatarUrl)) {
            $errors['avatarUrl'] = '头像URL格式不正确';
        }

        // 验证加密配置的完整性
        $hasToken = $this->token !== null;
        $hasEncodingKey = $this->encodingAESKey !== null;

        if ($hasToken && $hasEncodingKey) {
            // 两个字段都被更新，检查完整性
            if (!empty($this->token) && empty($this->encodingAESKey)) {
                $errors['encryption'] = '设置了Token但未设置EncodingAESKey，加密配置不完整';
            } elseif (empty($this->token) && !empty($this->encodingAESKey)) {
                $errors['encryption'] = '设置了EncodingAESKey但未设置Token，加密配置不完整';
            }
        }

        // 检查是否有任何更新
        if (!$this->hasUpdates()) {
            $errors['noUpdates'] = '没有提供任何要更新的字段';
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
        $data = parent::toArray();

        // 只包含非null的字段
        $fields = [
            'name' => $this->name,
            'description' => $this->description,
            'avatarUrl' => $this->avatarUrl,
            'appId' => $this->appId,
            'appSecret' => $this->appSecret,
            'isActive' => $this->isActive,
            'token' => $this->token,
            'encodingAESKey' => $this->encodingAESKey,
        ];

        foreach ($fields as $key => $value) {
            if ($value !== null) {
                $data[$key] = $value;
            }
        }

        // 添加计算字段
        if ($this->isActive !== null) {
            $data['statusDescription'] = $this->getStatusDescription();
        }

        $encryptionStatus = $this->getEncryptionStatusDescription();
        if ($encryptionStatus !== null) {
            $data['encryptionStatusDescription'] = $encryptionStatus;
        }

        $messageEncryption = $this->isMessageEncryptionEnabled();
        if ($messageEncryption !== null) {
            $data['isMessageEncryptionEnabled'] = $messageEncryption;
        }

        $data['hasUpdates'] = $this->hasUpdates();
        $data['updatedFields'] = array_keys($this->getUpdatedFields());
        $data['sensitiveFieldUpdates'] = $this->getSensitiveFieldUpdates();

        return $data;
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
