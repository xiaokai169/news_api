<?php

namespace App\DTO\Request;

use App\DTO\Base\AbstractRequestDto;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * 文章阅读记录请求DTO
 *
 * 用于记录文章阅读行为的请求数据传输对象
 * 包含记录阅读行为所需的所有字段和验证约束
 */
#[OA\Schema(
    title: '文章阅读记录请求DTO',
    description: '记录文章阅读行为的请求数据结构',
    required: ['articleId']
)]
class ArticleReadLogDto extends AbstractRequestDto
{
    /**
     * 文章ID
     */
    #[Assert\NotBlank(message: '文章ID不能为空')]
    #[Assert\Positive(message: '文章ID必须是正整数')]
    #[OA\Property(
        description: '文章ID',
        example: 1,
        minimum: 1
    )]
    #[Groups(['articleReadLog:read', 'articleReadLog:write'])]
    public int $articleId = 0;

    /**
     * 用户ID
     */
    #[Assert\PositiveOrZero(message: '用户ID必须是非负整数')]
    #[OA\Property(
        description: '用户ID，0表示匿名用户',
        example: 1,
        minimum: 0
    )]
    #[Groups(['articleReadLog:read', 'articleReadLog:write'])]
    public int $userId = 0;

    /**
     * 会话ID
     */
    #[Assert\Length(max: 255, maxMessage: '会话ID不能超过255个字符')]
    #[OA\Property(
        description: '会话ID，用于识别同一会话的多次阅读',
        example: 'sess_abc123def456'
    )]
    #[Groups(['articleReadLog:read', 'articleReadLog:write'])]
    public ?string $sessionId = null;

    /**
     * IP地址
     */
    #[Assert\Ip(message: 'IP地址格式不正确')]
    #[OA\Property(
        description: '用户IP地址',
        example: '192.168.1.1'
    )]
    #[Groups(['articleReadLog:read', 'articleReadLog:write'])]
    public ?string $ipAddress = null;

    /**
     * 用户代理
     */
    #[Assert\Length(max: 500, maxMessage: '用户代理不能超过500个字符')]
    #[OA\Property(
        description: '用户代理字符串',
        example: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    )]
    #[Groups(['articleReadLog:read', 'articleReadLog:write'])]
    public ?string $userAgent = null;

    /**
     * 来源页面
     */
    #[Assert\Length(max: 500, maxMessage: '来源页面不能超过500个字符')]
    #[Assert\Url(message: '来源页面必须是有效的URL')]
    #[OA\Property(
        description: '来源页面URL',
        example: 'https://example.com/news-list'
    )]
    #[Groups(['articleReadLog:read', 'articleReadLog:write'])]
    public ?string $referer = null;

    /**
     * 阅读时长（秒）
     */
    #[Assert\PositiveOrZero(message: '阅读时长必须是非负整数')]
    #[OA\Property(
        description: '阅读时长（秒）',
        example: 120,
        minimum: 0
    )]
    #[Groups(['articleReadLog:read', 'articleReadLog:write'])]
    public int $durationSeconds = 0;

    /**
     * 是否完成阅读
     */
    #[Assert\Type(type: 'bool', message: '是否完成阅读必须是布尔值')]
    #[OA\Property(
        description: '是否完成阅读（例如滚动到底部）',
        example: true
    )]
    #[Groups(['articleReadLog:read', 'articleReadLog:write'])]
    public bool $isCompleted = false;

    /**
     * 设备类型
     */
    #[Assert\Choice(choices: ['desktop', 'mobile', 'tablet', 'unknown'], message: '设备类型必须是desktop、mobile、tablet或unknown')]
    #[OA\Property(
        description: '设备类型',
        example: 'desktop',
        enum: ['desktop', 'mobile', 'tablet', 'unknown']
    )]
    #[Groups(['articleReadLog:read', 'articleReadLog:write'])]
    public ?string $deviceType = null;

    /**
     * 阅读时间
     */
    #[Assert\DateTime(message: '阅读时间格式不正确')]
    #[OA\Property(
        description: '阅读时间（格式：Y-m-d H:i:s）',
        example: '2024-01-01 10:30:00',
        format: 'date-time'
    )]
    #[Groups(['articleReadLog:read', 'articleReadLog:write'])]
    public ?string $readTime = null;

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
        if (isset($data['articleId'])) {
            $this->articleId = (int) $data['articleId'];
        }

        if (isset($data['userId'])) {
            $this->userId = (int) $data['userId'];
        }

        if (isset($data['sessionId'])) {
            $this->sessionId = $data['sessionId'] !== null ? $this->cleanString($data['sessionId']) : null;
        }

        if (isset($data['ipAddress'])) {
            $this->ipAddress = $data['ipAddress'] !== null ? $this->cleanString($data['ipAddress']) : null;
        }

        if (isset($data['userAgent'])) {
            $this->userAgent = $data['userAgent'] !== null ? $this->cleanString($data['userAgent']) : null;
        }

        if (isset($data['referer'])) {
            $this->referer = $data['referer'] !== null ? $this->cleanString($data['referer']) : null;
        }

        if (isset($data['durationSeconds'])) {
            $this->durationSeconds = (int) $data['durationSeconds'];
        }

        if (isset($data['isCompleted'])) {
            $this->isCompleted = (bool) $data['isCompleted'];
        }

        if (isset($data['deviceType'])) {
            $this->deviceType = $data['deviceType'] !== null ? $this->cleanString($data['deviceType']) : null;
        }

        if (isset($data['readTime'])) {
            $this->readTime = $data['readTime'] !== null ? $data['readTime'] : null;
        }

        return $this;
    }

    /**
     * 获取阅读时间的DateTime对象
     *
     * @return \DateTimeInterface|null
     * @throws \Exception
     */
    public function getReadTimeDateTime(): ?\DateTimeInterface
    {
        return $this->readTime ? $this->parseDateTime($this->readTime) : null;
    }

    /**
     * 设置阅读时间为DateTime对象
     *
     * @param \DateTimeInterface|null $dateTime
     * @return self
     */
    public function setReadTimeDateTime(?\DateTimeInterface $dateTime): self
    {
        $this->readTime = $dateTime ? $this->formatDateTime($dateTime) : null;
        return $this;
    }

    /**
     * 检查是否为匿名用户
     */
    public function isAnonymousUser(): bool
    {
        return $this->userId === 0;
    }

    /**
     * 检查是否为注册用户
     */
    public function isRegisteredUser(): bool
    {
        return $this->userId > 0;
    }

    /**
     * 获取设备类型描述
     */
    public function getDeviceTypeDescription(): string
    {
        return match($this->deviceType) {
            'desktop' => '桌面设备',
            'mobile' => '移动设备',
            'tablet' => '平板设备',
            'unknown' => '未知设备',
            default => '未设置'
        };
    }

    /**
     * 获取格式化的阅读时长
     */
    public function getFormattedDuration(): string
    {
        if ($this->durationSeconds < 60) {
            return $this->durationSeconds . '秒';
        } elseif ($this->durationSeconds < 3600) {
            $minutes = floor($this->durationSeconds / 60);
            $seconds = $this->durationSeconds % 60;
            return $minutes . '分' . $seconds . '秒';
        } else {
            $hours = floor($this->durationSeconds / 3600);
            $minutes = floor(($this->durationSeconds % 3600) / 60);
            return $hours . '小时' . $minutes . '分';
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

        // 验证阅读时长和完成状态的一致性
        if ($this->isCompleted && $this->durationSeconds === 0) {
            $errors['duration'] = '完成阅读时应该提供阅读时长';
        }

        // 验证阅读时长是否合理（不超过24小时）
        if ($this->durationSeconds > 86400) {
            $errors['duration'] = '阅读时长不能超过24小时';
        }

        // 验证用户ID和会话ID的至少一个存在
        if ($this->userId === 0 && empty($this->sessionId) && empty($this->ipAddress)) {
            $errors['identification'] = '用户ID、会话ID或IP地址至少需要提供一个';
        }

        // 验证阅读时间是否在合理范围内
        if ($this->readTime) {
            try {
                $readTime = new \DateTime($this->readTime);
                $now = new \DateTime();
                $future = (clone $now)->add(new \DateInterval('P1D')); // 允许1小时的时间差

                if ($readTime > $future) {
                    $errors['readTime'] = '阅读时间不能是未来时间';
                }

                // 不允许超过1年前的阅读时间
                $past = (clone $now)->sub(new \DateInterval('P1Y'));
                if ($readTime < $past) {
                    $errors['readTime'] = '阅读时间不能超过1年前';
                }
            } catch (\Exception $e) {
                $errors['readTime'] = '阅读时间格式不正确';
            }
        }

        return $errors;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'articleId' => $this->articleId,
            'userId' => $this->userId,
            'sessionId' => $this->sessionId,
            'ipAddress' => $this->ipAddress,
            'userAgent' => $this->userAgent,
            'referer' => $this->referer,
            'durationSeconds' => $this->durationSeconds,
            'formattedDuration' => $this->getFormattedDuration(),
            'isCompleted' => $this->isCompleted,
            'deviceType' => $this->deviceType,
            'deviceTypeDescription' => $this->getDeviceTypeDescription(),
            'readTime' => $this->readTime,
            'isAnonymousUser' => $this->isAnonymousUser(),
            'isRegisteredUser' => $this->isRegisteredUser(),
        ]);
    }

    /**
     * 从数组创建实例
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
