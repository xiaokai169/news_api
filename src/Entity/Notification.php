<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * 通知消息实体
 */
#[ORM\Entity(repositoryClass: 'App\Repository\NotificationRepository')]
#[ORM\Table(name: 'notifications')]
#[ORM\HasLifecycleCallbacks]
class Notification
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 255)]
    private string $id;

    #[ORM\Column(type: 'string', length: 100)]
    private string $type;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(type: 'string', length: 255)]
    private string $recipient;

    #[ORM\Column(type: 'integer')]
    private int $priority = 5;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $channels = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'pending';

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $lastChannel = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $attemptedChannels = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $scheduledAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $sentAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $readAt = null;

    #[ORM\Column(type: 'integer')]
    private int $retryCount = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $isRead = false;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getRecipient(): string
    {
        return $this->recipient;
    }

    public function setRecipient(string $recipient): self
    {
        $this->recipient = $recipient;
        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function getChannels(): ?string
    {
        return $this->channels;
    }

    public function setChannels(?string $channels): self
    {
        $this->channels = $channels;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getLastChannel(): ?string
    {
        return $this->lastChannel;
    }

    public function setLastChannel(?string $lastChannel): self
    {
        $this->lastChannel = $lastChannel;
        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): self
    {
        $this->lastError = $lastError;
        return $this;
    }

    public function getAttemptedChannels(): ?string
    {
        return $this->attemptedChannels;
    }

    public function setAttemptedChannels(?string $attemptedChannels): self
    {
        $this->attemptedChannels = $attemptedChannels;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getScheduledAt(): ?\DateTime
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTime $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;
        return $this;
    }

    public function getSentAt(): ?\DateTime
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTime $sentAt): self
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getReadAt(): ?\DateTime
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTime $readAt): self
    {
        $this->readAt = $readAt;
        return $this;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function setRetryCount(int $retryCount): self
    {
        $this->retryCount = $retryCount;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): self
    {
        $this->isRead = $isRead;
        return $this;
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    /**
     * 标记为已读
     */
    public function markAsRead(): self
    {
        $this->isRead = true;
        $this->readAt = new \DateTime();
        return $this;
    }

    /**
     * 标记为已发送
     */
    public function markAsSent(): self
    {
        $this->status = 'sent';
        $this->sentAt = new \DateTime();
        return $this;
    }

    /**
     * 增加重试次数
     */
    public function incrementRetryCount(): self
    {
        $this->retryCount++;
        return $this;
    }

    /**
     * 获取渠道数组
     */
    public function getChannelsArray(): array
    {
        return $this->channels ? explode(',', $this->channels) : [];
    }

    /**
     * 获取尝试的渠道数组
     */
    public function getAttemptedChannelsArray(): array
    {
        return $this->attemptedChannels ? explode(',', $this->attemptedChannels) : [];
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'content' => $this->content,
            'recipient' => $this->recipient,
            'priority' => $this->priority,
            'channels' => $this->getChannelsArray(),
            'status' => $this->status,
            'last_channel' => $this->lastChannel,
            'last_error' => $this->lastError,
            'attempted_channels' => $this->getAttemptedChannelsArray(),
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'scheduled_at' => $this->scheduledAt?->format('Y-m-d H:i:s'),
            'sent_at' => $this->sentAt?->format('Y-m-d H:i:s'),
            'read_at' => $this->readAt?->format('Y-m-d H:i:s'),
            'retry_count' => $this->retryCount,
            'is_read' => $this->isRead
        ];
    }
}
