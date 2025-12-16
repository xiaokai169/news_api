<?php

namespace App\DTO;

/**
 * 通知消息数据传输对象
 */
class NotificationMessage
{
    public function __construct(
        private readonly string $id,
        private readonly string $type,
        private readonly string $title,
        private readonly string $content,
        private readonly string $recipient,
        private readonly int $priority = 5,
        private readonly array $metadata = [],
        private readonly ?\DateTime $scheduledAt = null,
        private readonly ?string $template = null
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getRecipient(): string
    {
        return $this->recipient;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getScheduledAt(): ?\DateTime
    {
        return $this->scheduledAt;
    }

    public function getTemplate(): ?string
    {
        return $this->template;
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
            'metadata' => $this->metadata,
            'scheduled_at' => $this->scheduledAt?->format('Y-m-d H:i:s'),
            'template' => $this->template
        ];
    }

    /**
     * 从数组创建消息
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['type'],
            $data['title'],
            $data['content'],
            $data['recipient'],
            $data['priority'] ?? 5,
            $data['metadata'] ?? [],
            isset($data['scheduled_at']) ? new \DateTime($data['scheduled_at']) : null,
            $data['template'] ?? null
        );
    }

    /**
     * 创建任务完成通知
     */
    public static function createTaskCompleted(string $taskId, string $recipient, array $result = []): self
    {
        return new self(
            uniqid('notif_', true),
            'task_completed',
            '任务完成',
            sprintf('任务 %s 已成功完成', $taskId),
            $recipient,
            5,
            ['task_id' => $taskId, 'result' => $result]
        );
    }

    /**
     * 创建任务失败通知
     */
    public static function createTaskFailed(string $taskId, string $recipient, string $error): self
    {
        return new self(
            uniqid('notif_', true),
            'task_failed',
            '任务失败',
            sprintf('任务 %s 执行失败: %s', $taskId, $error),
            $recipient,
            8,
            ['task_id' => $taskId, 'error' => $error]
        );
    }
}
