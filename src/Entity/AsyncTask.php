<?php

namespace App\Entity;

use App\Repository\AsyncTaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: AsyncTaskRepository::class)]
#[ORM\Table(name: 'async_tasks')]
#[ORM\Index(name: 'idx_status_priority', columns: ['status', 'priority'])]
#[ORM\Index(name: 'idx_type_status', columns: ['type', 'status'])]
#[ORM\Index(name: 'idx_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_queue_name', columns: ['queue_name'])]
#[ORM\HasLifecycleCallbacks]
class AsyncTask
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_RETRYING = 'retrying';

    public const TYPE_WECHAT_SYNC = 'wechat_sync';
    public const TYPE_MEDIA_PROCESS = 'media_process';
    public const TYPE_BATCH_PROCESS = 'batch_process';

    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    #[Groups(['async_task:read'])]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Groups(['async_task:read', 'async_task:write'])]
    private string $type;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['async_task:read', 'async_task:write'])]
    private int $priority = 5;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Groups(['async_task:read'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['async_task:read', 'async_task:write'])]
    private array $payload = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['async_task:read'])]
    private ?array $result = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['async_task:read'])]
    private ?string $errorMessage = null;

    #[ORM\Column(name: 'retry_count', type: Types::INTEGER)]
    #[Groups(['async_task:read'])]
    private int $retryCount = 0;

    #[ORM\Column(name: 'max_retries', type: Types::INTEGER)]
    #[Groups(['async_task:read', 'async_task:write'])]
    private int $maxRetries = 3;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    #[Groups(['async_task:read'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'started_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['async_task:read'])]
    private ?\DateTimeInterface $startedAt = null;

    #[ORM\Column(name: 'completed_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['async_task:read'])]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['async_task:read'])]
    private ?\DateTimeInterface $expiresAt = null;

    #[ORM\Column(name: 'created_by', type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['async_task:read', 'async_task:write'])]
    private ?string $createdBy = null;

    #[ORM\Column(name: 'queue_name', type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['async_task:read', 'async_task:write'])]
    private ?string $queueName = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->id = $this->generateUuid();
    }

    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        // 可以在这里添加更新时间的逻辑
    }

    /**
     * 生成UUID v4
     */
    private function generateUuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
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

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;
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

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    public function getResult(): ?array
    {
        return $this->result;
    }

    public function setResult(?array $result): self
    {
        $this->result = $result;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
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

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function setMaxRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getStartedAt(): ?\DateTimeInterface
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeInterface $startedAt): self
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): self
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getQueueName(): ?string
    {
        return $this->queueName;
    }

    public function setQueueName(?string $queueName): self
    {
        $this->queueName = $queueName;
        return $this;
    }

    /**
     * 检查任务是否可以重试
     */
    public function canRetry(): bool
    {
        return $this->retryCount < $this->maxRetries && in_array($this->status, [self::STATUS_FAILED, self::STATUS_CANCELLED]);
    }

    /**
     * 检查任务是否已完成（成功或失败）
     */
    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED]);
    }

    /**
     * 检查任务是否正在运行
     */
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * 检查任务是否已过期
     */
    public function isExpired(): bool
    {
        return $this->expiresAt && $this->expiresAt < new \DateTime();
    }

    /**
     * 获取任务执行时长（秒）
     */
    public function getExecutionDuration(): ?float
    {
        if (!$this->startedAt) {
            return null;
        }

        $endTime = $this->completedAt ?? new \DateTime();
        return $endTime->getTimestamp() - $this->startedAt->getTimestamp();
    }

    /**
     * 获取所有可用状态
     */
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_RUNNING,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
            self::STATUS_RETRYING,
        ];
    }

    /**
     * 获取所有可用任务类型
     */
    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_WECHAT_SYNC,
            self::TYPE_MEDIA_PROCESS,
            self::TYPE_BATCH_PROCESS,
        ];
    }
}
