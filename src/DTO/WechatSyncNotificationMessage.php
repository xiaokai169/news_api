<?php

namespace App\DTO;

/**
 * 微信同步专用通知消息
 */
class WechatSyncNotificationMessage extends NotificationMessage
{
    public function __construct(
        string $id,
        string $syncType,
        string $accountId,
        string $title,
        string $content,
        string $recipient,
        int $priority = 5,
        array $metadata = [],
        ?\DateTime $scheduledAt = null,
        ?string $template = null,
        private readonly ?string $taskId = null,
        private readonly ?array $syncResult = null,
        private readonly ?array $statistics = null,
        private readonly ?array $errorDetails = null
    ) {
        parent::__construct(
            $id,
            $syncType,
            $title,
            $content,
            $recipient,
            $priority,
            array_merge($metadata, [
                'task_id' => $taskId,
                'account_id' => $accountId,
                'sync_result' => $syncResult,
                'statistics' => $statistics,
                'error_details' => $errorDetails
            ]),
            $scheduledAt,
            $template
        );
    }

    public function getTaskId(): ?string
    {
        return $this->taskId;
    }

    public function getAccountId(): string
    {
        return $this->metadata['account_id'] ?? '';
    }

    public function getSyncResult(): ?array
    {
        return $this->syncResult;
    }

    public function getStatistics(): ?array
    {
        return $this->statistics;
    }

    public function getErrorDetails(): ?array
    {
        return $this->errorDetails;
    }

    /**
     * 创建同步开始通知
     */
    public static function createSyncStarted(string $taskId, string $accountId, string $recipient, array $options = []): self
    {
        return new self(
            uniqid('wechat_sync_', true),
            'wechat_sync_started',
            $accountId,
            '微信同步开始',
            sprintf('账号 %s 的微信同步任务已开始执行', $accountId),
            $recipient,
            5,
            $options,
            null,
            'wechat_sync_started',
            $taskId,
            null,
            null,
            null
        );
    }

    /**
     * 创建同步完成通知
     */
    public static function createSyncCompleted(string $taskId, string $accountId, string $recipient, array $statistics, array $result = []): self
    {
        $title = sprintf('微信同步完成 - %s', $accountId);
        $content = sprintf(
            '账号 %s 的微信同步已完成，成功处理 %d 篇文章',
            $accountId,
            $statistics['processed'] ?? 0
        );

        return new self(
            uniqid('wechat_sync_', true),
            'wechat_sync_completed',
            $accountId,
            $title,
            $content,
            $recipient,
            3,
            [],
            null,
            'wechat_sync_completed',
            $taskId,
            $result,
            $statistics,
            null
        );
    }

    /**
     * 创建同步失败通知
     */
    public static function createSyncFailed(string $taskId, string $accountId, string $recipient, string $error, array $errorDetails = []): self
    {
        $title = sprintf('微信同步失败 - %s', $accountId);
        $content = sprintf('账号 %s 的微信同步失败: %s', $accountId, $error);

        return new self(
            uniqid('wechat_sync_', true),
            'wechat_sync_failed',
            $accountId,
            $title,
            $content,
            $recipient,
            8,
            [],
            null,
            'wechat_sync_failed',
            $taskId,
            null,
            null,
            array_merge(['error' => $error], $errorDetails)
        );
    }

    /**
     * 创建同步进度通知
     */
    public static function createSyncProgress(string $taskId, string $accountId, string $recipient, int $current, int $total, float $percentage): self
    {
        $title = sprintf('微信同步进度 - %s', $accountId);
        $content = sprintf(
            '账号 %s 的微信同步进度: %d/%d (%.1f%%)',
            $accountId,
            $current,
            $total,
            $percentage
        );

        return new self(
            uniqid('wechat_sync_', true),
            'wechat_sync_progress',
            $accountId,
            $title,
            $content,
            $recipient,
            4,
            ['current' => $current, 'total' => $total, 'percentage' => $percentage],
            null,
            'wechat_sync_progress',
            $taskId,
            null,
            ['current' => $current, 'total' => $total, 'percentage' => $percentage],
            null
        );
    }
}
