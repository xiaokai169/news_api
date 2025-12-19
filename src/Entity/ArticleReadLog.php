<?php

namespace App\Entity;

use App\Repository\ArticleReadLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ArticleReadLogRepository::class)]
#[ORM\Table(name: 'article_read_logs')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['article_id'], name: 'idx_article_read_logs_article_id')]
#[ORM\Index(columns: ['user_id'], name: 'idx_article_read_logs_user_id')]
#[ORM\Index(columns: ['read_time'], name: 'idx_article_read_logs_read_time')]
#[ORM\Index(columns: ['ip_address'], name: 'idx_article_read_logs_ip_address')]
#[ORM\Index(columns: ['session_id', 'article_id'], name: 'idx_article_read_logs_session_article')]
class ArticleReadLog
{
    public const DEVICE_TYPE_DESKTOP = 'desktop';
    public const DEVICE_TYPE_MOBILE = 'mobile';
    public const DEVICE_TYPE_TABLET = 'tablet';
    public const DEVICE_TYPE_UNKNOWN = 'unknown';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['articleReadLog:read'])]
    private ?int $id = null;

    #[ORM\Column(name: 'article_id', type: Types::INTEGER)]
    #[Groups(['articleReadLog:read', 'articleReadLog:write'])]
    private int $articleId = 0;

    #[ORM\Column(name: 'user_id', type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['articleReadLog:read', 'articleReadLog:write'])]
    private int $userId = 0;

    #[ORM\Column(name: 'ip_address', type: Types::STRING, length: 45, nullable: true)]
    #[Groups(['articleReadLog:read'])]
    private ?string $ipAddress = null;

    #[ORM\Column(name: 'user_agent', type: Types::STRING, length: 500, nullable: true)]
    #[Groups(['articleReadLog:read'])]
    private ?string $userAgent = null;

    #[ORM\Column(name: 'read_time', type: Types::DATETIME_MUTABLE)]
    #[Groups(['articleReadLog:read'])]
    private \DateTimeInterface $readTime;

    #[ORM\Column(name: 'session_id', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['articleReadLog:read', 'articleReadLog:write'])]
    private ?string $sessionId = null;

    #[ORM\Column(name: 'device_type', type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['articleReadLog:read', 'articleReadLog:write'])]
    private ?string $deviceType = self::DEVICE_TYPE_UNKNOWN;

    #[ORM\Column(name: 'referer', type: Types::STRING, length: 500, nullable: true)]
    #[Groups(['articleReadLog:read'])]
    private ?string $referer = null;

    #[ORM\Column(name: 'duration_seconds', type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['articleReadLog:read', 'articleReadLog:write'])]
    private int $durationSeconds = 0;

    #[ORM\Column(name: 'is_completed', type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['articleReadLog:read', 'articleReadLog:write'])]
    private bool $isCompleted = false;

    #[ORM\Column(name: 'create_at', type: Types::DATETIME_MUTABLE)]
    #[Groups(['articleReadLog:read'])]
    private \DateTimeInterface $createAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE)]
    #[Groups(['articleReadLog:read'])]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->readTime = new \DateTime();
        $this->createAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PrePersist]
    private function setCreateAtValue(): void
    {
        if ($this->createAt === null) {
            $this->createAt = new \DateTime();
        }
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    private function setUpdateAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArticleId(): int
    {
        return $this->articleId;
    }

    public function setArticleId(int $articleId): self
    {
        $this->articleId = $articleId;
        return $this;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getReadTime(): \DateTimeInterface
    {
        return $this->readTime;
    }

    public function setReadTime(\DateTimeInterface $readTime): self
    {
        $this->readTime = $readTime;
        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): self
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    public function getDeviceType(): ?string
    {
        return $this->deviceType;
    }

    public function setDeviceType(?string $deviceType): self
    {
        $this->deviceType = $deviceType;
        return $this;
    }

    public function getReferer(): ?string
    {
        return $this->referer;
    }

    public function setReferer(?string $referer): self
    {
        $this->referer = $referer;
        return $this;
    }

    public function getDurationSeconds(): int
    {
        return $this->durationSeconds;
    }

    public function setDurationSeconds(int $durationSeconds): self
    {
        $this->durationSeconds = $durationSeconds;
        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->isCompleted;
    }

    public function setCompleted(bool $isCompleted): self
    {
        $this->isCompleted = $isCompleted;
        return $this;
    }

    public function getCreateAt(): \DateTimeInterface
    {
        return $this->createAt;
    }

    public function setCreateAt(\DateTimeInterface $createAt): self
    {
        $this->createAt = $createAt;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
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
            self::DEVICE_TYPE_DESKTOP => '桌面设备',
            self::DEVICE_TYPE_MOBILE => '移动设备',
            self::DEVICE_TYPE_TABLET => '平板设备',
            default => '未知设备'
        };
    }

    /**
     * 从User-Agent字符串检测设备类型
     */
    public function detectDeviceType(): string
    {
        if (empty($this->userAgent)) {
            return self::DEVICE_TYPE_UNKNOWN;
        }

        $userAgent = strtolower($this->userAgent);

        // 检测移动设备
        if (preg_match('/mobile|android|iphone|ipod|phone/i', $userAgent)) {
            return self::DEVICE_TYPE_MOBILE;
        }

        // 检测平板设备
        if (preg_match('/tablet|ipad/i', $userAgent)) {
            return self::DEVICE_TYPE_TABLET;
        }

        // 默认为桌面设备
        return self::DEVICE_TYPE_DESKTOP;
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
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'articleId' => $this->articleId,
            'userId' => $this->userId,
            'ipAddress' => $this->ipAddress,
            'userAgent' => $this->userAgent,
            'readTime' => $this->readTime->format('Y-m-d H:i:s'),
            'sessionId' => $this->sessionId,
            'deviceType' => $this->deviceType,
            'deviceTypeDescription' => $this->getDeviceTypeDescription(),
            'referer' => $this->referer,
            'durationSeconds' => $this->durationSeconds,
            'formattedDuration' => $this->getFormattedDuration(),
            'isCompleted' => $this->isCompleted,
            'isAnonymousUser' => $this->isAnonymousUser(),
            'isRegisteredUser' => $this->isRegisteredUser(),
            'createAt' => $this->createAt->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
