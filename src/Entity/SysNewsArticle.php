<?php
// src/Entity/SysNewsArticle.php

namespace App\Entity;

use App\Repository\SysNewsArticleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SysNewsArticleRepository::class)]
#[ORM\Table(name: 'sys_news_article')]
#[ORM\HasLifecycleCallbacks]
class SysNewsArticle
{
    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 2;
    public const STATUS_DELETED = 3;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['sysNewsArticle:read'])]
    private ?int $id = null;

    #[ORM\Column(name: 'merchant_id', type: Types::INTEGER, options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    #[Groups(['sysNewsArticle:read', 'sysNewsArticle:write'])]
    private int $merchantId = 0;

    #[ORM\Column(name: 'user_id', type: Types::INTEGER, options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    #[Groups(['sysNewsArticle:read', 'sysNewsArticle:write'])]
    private int $userId = 0;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank(message: '文章名称不能为空')]
    #[Assert\Length(max: 50, maxMessage: '文章名称不能超过50个字符')]
    #[Groups(['sysNewsArticle:read', 'sysNewsArticle:write'])]
    private string $name = '';

    #[ORM\Column(type: Types::STRING)]
    #[Assert\NotBlank(message: '封面图不能为空')]
    #[Groups(['sysNewsArticle:read', 'sysNewsArticle:write'])]
    private string $cover = '';

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: '文章内容不能为空')]
    #[Assert\Length(max: 255, maxMessage: '文章内容不能超过255个字符')]
    #[Groups(['sysNewsArticle:read', 'sysNewsArticle:write'])]
    private string $content = '';

    #[ORM\Column(name: 'release_time', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['sysNewsArticle:read', 'sysNewsArticle:write'])]
    private ?\DateTimeInterface $releaseTime = null;

    #[ORM\Column(name: 'original_url', type: Types::STRING, options: ['default' => ''])]
    #[Groups(['sysNewsArticle:read', 'sysNewsArticle:write'])]
    private string $originalUrl = '';

    #[ORM\Column(name: 'status', type: Types::SMALLINT, options: ['default' => 1])]
    #[Assert\Choice(choices: [1, 2], message: '状态值必须是1（激活）或2（非激活）')]
    #[Groups(['sysNewsArticle:read', 'sysNewsArticle:write'])]
    private int $status = self::STATUS_ACTIVE;

    #[ORM\Column(name: 'is_recommend', type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['sysNewsArticle:read', 'sysNewsArticle:write'])]
    private bool $isRecommend = false;

    #[ORM\Column(name: 'perfect', type: Types::STRING, length: 255)]
    #[Assert\Length(max: 255, maxMessage: '完美描述不能超过255个字符')]
    #[Groups(['sysNewsArticle:read', 'sysNewsArticle:write'])]
    private string $perfect = '';

    #[ORM\ManyToOne(targetEntity: SysNewsArticleCategory::class, inversedBy: 'articles', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull(message: '文章分类不能为空')]
    #[Groups(['sysNewsArticle:read', 'sysNewsArticle:write'])]
    private ?SysNewsArticleCategory $category = null;

    #[ORM\Column(name: 'update_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['sysNewsArticle:read'])]
    private ?\DateTimeInterface $updateTime = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['sysNewsArticle:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'view_count', type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['sysNewsArticle:read'])]
    private int $viewCount = 0;

    public function __construct()
    {
        // 移除这里的初始化，让生命周期回调来处理
    }

    #[ORM\PrePersist]
    private function setCreatedAtValue(): void
    {
        $currentTime = new \DateTime();

        if ($this->createdAt === null) {
            $this->createdAt = $currentTime;
        }
        if ($this->updateTime === null) {
            $this->updateTime = $currentTime;
        }

        // 预约发布状态自动判定
        $this->determinePublishStatus($currentTime);
    }

    #[ORM\PreUpdate]
    private function setUpdateTimeValue(): void
    {
        $originalStatus = $this->status;
        $this->updateTime = new \DateTime();

        // 如果状态被设置为已删除，绝对不要修改它
        if ($this->status === self::STATUS_DELETED) {
            error_log("DEBUG: PreUpdate - Status is DELETED, preserving it");
            return;
        }

        // 只有在状态不是手动设置的情况下才重新判定发布状态
        // 手动设置的状态优先级高于自动判定逻辑
        if (!$this->isStatusManuallySet()) {
            error_log("DEBUG: PreUpdate calling determinePublishStatus - Current status: {$this->status}");
            $this->determinePublishStatus(new \DateTime());
            error_log("DEBUG: PreUpdate after determinePublishStatus - New status: {$this->status}");
        } else {
            error_log("DEBUG: PreUpdate skipped determinePublishStatus - Status is manually set");
        }

        if ($originalStatus !== $this->status) {
            error_log("DEBUG: PreUpdate status changed from {$originalStatus} to {$this->status}");
        }
    }

    /**
     * 预约发布状态自动判定逻辑
     */
    public function determinePublishStatus(\DateTimeInterface $currentTime): void
    {
        if ($this->releaseTime !== null) {
            // 比较 releaseTime 与当前时间
            if ($this->releaseTime > $currentTime) {
                // 如果 releaseTime > 当前时间：状态设为非激活（等待定时发布）
                $this->status = self::STATUS_INACTIVE;
            } else {
                // 如果 releaseTime <= 当前时间：状态设为激活（立即发布）
                $this->status = self::STATUS_ACTIVE;
            }
        } else {
            // 如果 releaseTime 为空：状态设为激活（立即发布）
            $this->status = self::STATUS_ACTIVE;
            // releaseTime 自动设置为当前时间
            $this->releaseTime = $currentTime;
        }
    }

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMerchantId(): int
    {
        return $this->merchantId;
    }

    public function setMerchantId(int $merchantId): self
    {
        $this->merchantId = $merchantId;
        // 移除手动更新时间调用，让 PreUpdate 处理
        return $this;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        // 移除手动更新时间调用，让 PreUpdate 处理
        return $this;
    }

    public function getCover(): string
    {
        return $this->cover;
    }

    public function setCover(string $cover): self
    {
        $this->cover = $cover;
        // 移除手动更新时间调用，让 PreUpdate 处理
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        // 移除手动更新时间调用，让 PreUpdate 处理
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    // 移除 setCreatedAt 方法，防止外部修改创建时间
    // public function setCreatedAt(\DateTimeInterface $createdAt): self
    // {
    //     $this->createdAt = $createdAt;
    //     return $this;
    // }

    public function getUpdateTime(): ?\DateTimeInterface
    {
        return $this->updateTime;
    }

    public function setUpdateTime(?\DateTimeInterface $updateTime): self
    {
        $this->updateTime = $updateTime;
        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;
        // 移除手动更新时间调用，让 PreUpdate 处理
        return $this;
    }

    public function isRecommend(): bool
    {
        return $this->isRecommend;
    }

    public function setIsRecommend(bool $isRecommend): self
    {
        $this->isRecommend = $isRecommend;
        // 移除手动更新时间调用，让 PreUpdate 处理
        return $this;
    }

    public function getPerfect(): string
    {
        return $this->perfect;
    }

    public function setPerfect(string $perfect): self
    {
        $this->perfect = $perfect;
        // 移除手动更新时间调用，让 PreUpdate 处理
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        // 移除手动更新时间调用，让 PreUpdate 处理
        return $this;
    }

    public function setReleaseTime(?\DateTimeInterface $releaseTime): self
    {
        $this->releaseTime = $releaseTime ;
        // 移除手动更新时间调用，让 PreUpdate 处理
        return $this;
    }

    public function getReleaseTime(): ?\DateTimeInterface
    {
        return $this->releaseTime;
    }

    public function setOriginalUrl(string $originalUrl): self
    {
        $this->originalUrl = $originalUrl;
        // 移除手动更新时间调用，让 PreUpdate 处理
        return $this;
    }

    public function getOriginalUrl(): string
    {
        return $this->originalUrl;
    }

    public function getCategory(): ?SysNewsArticleCategory
    {
        return $this->category;
    }

    public function setCategory(?SysNewsArticleCategory $category): self
    {
        $this->category = $category;
        // 移除手动更新时间调用，让 PreUpdate 处理
        return $this;
    }

    /**
     * 添加格式化时间的方法，方便前端显示
     */
    public function getCreatedAtFormatted(): string
    {
        return $this->createdAt ? $this->createdAt->format('Y-m-d H:i:s') : '';
    }

    public function getUpdateTimeFormatted(): string
    {
        return $this->updateTime ? $this->updateTime->format('Y-m-d H:i:s') : '';
    }

    public function getReleaseTimeFormatted(): string
    {
        return $this->releaseTime ? $this->releaseTime->format('Y-m-d H:i:s') : '';
    }

    /**
     * 检查文章是否已发布
     */
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * 检查文章是否待发布
     */
    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_INACTIVE && $this->releaseTime !== null;
    }

    /**
     * 检查文章是否已删除
     */
    public function isDeleted(): bool
    {
        return $this->status === self::STATUS_DELETED;
    }

    /**
     * 逻辑删除文章
     */
    public function markAsDeleted(): self
    {
        error_log("DEBUG: markAsDeleted() called - ID: {$this->id}, Current status: {$this->status}");
        $this->status = self::STATUS_DELETED;
        $this->updateTime = new \DateTime();
        error_log("DEBUG: markAsDeleted() completed - New status: {$this->status}, UpdateTime: {$this->updateTime->format('Y-m-d H:i:s')}");
        return $this;
    }

    /**
     * 恢复文章
     */
    public function restore(): self
    {
        $currentTime = new \DateTime();
        $this->determinePublishStatus($currentTime);
        $this->updateTime = $currentTime;
        return $this;
    }

    /**
     * 检查是否应该立即发布
     */
    public function shouldPublishNow(): bool
    {
        if ($this->releaseTime === null) {
            return true;
        }

        return $this->releaseTime <= new \DateTime();
    }

    /**
     * 检查状态是否被手动设置
     * 用于判断是否应该跳过自动状态判定逻辑
     */
    private function isStatusManuallySet(): bool
    {
        // 这里我们使用一个简单的启发式方法：
        // 如果状态与根据releaseTime自动计算的结果不一致，则认为状态是手动设置的
        $currentTime = new \DateTime();
        $autoCalculatedStatus = self::STATUS_ACTIVE; // 默认值

        if ($this->releaseTime !== null) {
            $autoCalculatedStatus = $this->releaseTime > $currentTime ? self::STATUS_INACTIVE : self::STATUS_ACTIVE;
        }

        $isManuallySet = $this->status !== $autoCalculatedStatus;
        error_log("DEBUG: isStatusManuallySet - ID: {$this->id}, Current status: {$this->status}, Auto calculated: {$autoCalculatedStatus}, Result: " . ($isManuallySet ? 'true' : 'false'));

        return $isManuallySet;
    }

    /**
     * 获取发布状态描述
     */
    public function getStatusDescription(): string
    {
        return match($this->status) {
            self::STATUS_ACTIVE => '已发布',
            self::STATUS_INACTIVE => '待发布',
            self::STATUS_DELETED => '已删除',
            default => '未知状态'
        };
    }

    /**
     * 获取阅读数量
     */
    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    /**
     * 设置阅读数量
     */
    public function setViewCount(int $viewCount): self
    {
        $this->viewCount = $viewCount;
        return $this;
    }

    /**
     * 增加阅读数量
     */
    public function incrementViewCount(int $increment = 1): self
    {
        $this->viewCount += $increment;
        return $this;
    }

    /**
     * 获取格式化的阅读数量
     */
    public function getFormattedViewCount(): string
    {
        if ($this->viewCount < 1000) {
            return (string) $this->viewCount;
        } elseif ($this->viewCount < 10000) {
            return number_format($this->viewCount / 1000, 1) . 'k';
        } elseif ($this->viewCount < 1000000) {
            return number_format($this->viewCount / 1000, 0) . 'k';
        } elseif ($this->viewCount < 1000000000) {
            return number_format($this->viewCount / 1000000, 1) . 'M';
        } else {
            return number_format($this->viewCount / 1000000, 0) . 'M';
        }
    }

    /**
     * 获取阅读热度等级
     */
    public function getReadHeatLevel(): string
    {
        if ($this->viewCount < 10) {
            return 'cold'; // 冷门
        } elseif ($this->viewCount < 50) {
            return 'warm'; // 温热
        } elseif ($this->viewCount < 200) {
            return 'hot'; // 热门
        } elseif ($this->viewCount < 1000) {
            return 'very_hot'; // 很热
        } else {
            return 'explosive'; // 爆款
        }
    }

    /**
     * 获取阅读热度描述
     */
    public function getReadHeatDescription(): string
    {
        return match($this->getReadHeatLevel()) {
            'cold' => '冷门',
            'warm' => '温热',
            'hot' => '热门',
            'very_hot' => '很热',
            'explosive' => '爆款',
            default => '未知'
        };
    }

    /**
     * 检查是否为热门文章
     */
    public function isPopular(): bool
    {
        return $this->viewCount >= 50;
    }

    /**
     * 检查是否为爆款文章
     */
    public function isExplosive(): bool
    {
        return $this->viewCount >= 1000;
    }
}
