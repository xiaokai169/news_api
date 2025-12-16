<?php

namespace App\Entity;

use App\Repository\TaskExecutionLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TaskExecutionLogRepository::class)]
#[ORM\Table(name: 'task_execution_logs')]
#[ORM\Index(name: 'idx_task_id', columns: ['task_id'])]
#[ORM\Index(name: 'idx_execution_id', columns: ['execution_id'])]
#[ORM\Index(name: 'idx_started_at', columns: ['started_at'])]
#[ORM\HasLifecycleCallbacks]
class TaskExecutionLog
{
    public const STATUS_STARTED = 'started';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    #[Groups(['task_log:read'])]
    private ?int $id = null;

    #[ORM\Column(name: 'task_id', type: Types::STRING, length: 36)]
    #[Groups(['task_log:read'])]
    private string $taskId;

    #[ORM\Column(name: 'execution_id', type: Types::STRING, length: 36)]
    #[Groups(['task_log:read'])]
    private string $executionId;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Groups(['task_log:read'])]
    private string $status = self::STATUS_STARTED;

    #[ORM\Column(name: 'started_at', type: Types::DATETIME_MUTABLE)]
    #[Groups(['task_log:read'])]
    private \DateTimeInterface $startedAt;

    #[ORM\Column(name: 'completed_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['task_log:read'])]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\Column(name: 'duration_ms', type: Types::INTEGER, nullable: true)]
    #[Groups(['task_log:read'])]
    private ?int $durationMs = null;

    #[ORM\Column(name: 'memory_usage', type: Types::INTEGER, nullable: true)]
    #[Groups(['task_log:read'])]
    private ?int $memoryUsage = null;

    #[ORM\Column(name: 'processed_items', type: Types::INTEGER, nullable: true)]
    #[Groups(['task_log:read'])]
    private ?int $processedItems = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['task_log:read'])]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['task_log:read'])]
    private ?string $stackTrace = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['task_log:read'])]
    private ?array $metadata = null;

    public function __construct()
    {
        $this->startedAt = new \DateTime();
        $this->executionId = $this->generateUuid();
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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTaskId(): string
    {
        return $this->taskId;
    }

    public function setTaskId(string $taskId): self
    {
        $this->taskId = $taskId;
        return $this;
    }

    public function getExecutionId(): string
    {
        return $this->executionId;
    }

    public function setExecutionId(string $executionId): self
    {
        $this->executionId = $executionId;
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

    public function getStartedAt(): \DateTimeInterface
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeInterface $startedAt): self
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

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    public function setDurationMs(?int $durationMs): self
    {
        $this->durationMs = $durationMs;
        return $this;
    }

    public function getMemoryUsage(): ?int
    {
        return $this->memoryUsage;
    }

    public function setMemoryUsage(?int $memoryUsage): self
    {
        $this->memoryUsage = $memoryUsage;
        return $this;
    }

    public function getProcessedItems(): ?int
    {
        return $this->processedItems;
    }

    public function setProcessedItems(?int $processedItems): self
    {
        $this->processedItems = $processedItems;
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

    public function getStackTrace(): ?string
    {
        return $this->stackTrace;
    }

    public function setStackTrace(?string $stackTrace): self
    {
        $this->stackTrace = $stackTrace;
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

    /**
     * 标记执行开始
     */
    public function markAsStarted(): self
    {
        $this->status = self::STATUS_STARTED;
        $this->startedAt = new \DateTime();
        $this->memoryUsage = memory_get_usage(true);
        return $this;
    }

    /**
     * 标记执行为运行中
     */
    public function markAsRunning(): self
    {
        $this->status = self::STATUS_RUNNING;
        return $this;
    }

    /**
     * 标记执行完成
     */
    public function markAsCompleted(?int $processedItems = null): self
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = new \DateTime();
        $this->processedItems = $processedItems;
        $this->calculateDuration();
        return $this;
    }

    /**
     * 标记执行失败
     */
    public function markAsFailed(\Throwable $exception, ?int $processedItems = null): self
    {
        $this->status = self::STATUS_FAILED;
        $this->completedAt = new \DateTime();
        $this->errorMessage = $exception->getMessage();
        $this->stackTrace = $exception->getTraceAsString();
        $this->processedItems = $processedItems;
        $this->calculateDuration();
        return $this;
    }

    /**
     * 标记执行取消
     */
    public function markAsCancelled(?int $processedItems = null): self
    {
        $this->status = self::STATUS_CANCELLED;
        $this->completedAt = new \DateTime();
        $this->processedItems = $processedItems;
        $this->calculateDuration();
        return $this;
    }

    /**
     * 计算执行时长（毫秒）
     */
    private function calculateDuration(): void
    {
        if ($this->startedAt && $this->completedAt) {
            $duration = $this->completedAt->getTimestamp() - $this->startedAt->getTimestamp();
            $this->durationMs = $duration * 1000; // 转换为毫秒
        }
    }

    /**
     * 检查执行是否已完成
     */
    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED]);
    }

    /**
     * 检查执行是否失败
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * 检查执行是否成功
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * 获取执行时长（秒）
     */
    public function getExecutionDuration(): ?float
    {
        if ($this->durationMs) {
            return $this->durationMs / 1000;
        }
        return null;
    }

    /**
     * 添加元数据
     */
    public function addMetadata(string $key, mixed $value): self
    {
        if ($this->metadata === null) {
            $this->metadata = [];
        }
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * 获取所有可用状态
     */
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_STARTED,
            self::STATUS_RUNNING,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ];
    }
}
