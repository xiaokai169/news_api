<?php

namespace App\Message;

/**
 * 微信同步消息
 */
class WechatSyncMessage
{
    public function __construct(
        private readonly string $taskId,
        private readonly string $accountId,
        private readonly string $syncType,
        private readonly string $syncScope,
        private readonly int $articleLimit,
        private readonly bool $forceSync,
        private readonly array $customOptions = []
    ) {
    }

    public function getTaskId(): string
    {
        return $this->taskId;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getSyncType(): string
    {
        return $this->syncType;
    }

    public function getSyncScope(): string
    {
        return $this->syncScope;
    }

    public function getArticleLimit(): int
    {
        return $this->articleLimit;
    }

    public function isForceSync(): bool
    {
        return $this->forceSync;
    }

    public function getCustomOptions(): array
    {
        return $this->customOptions;
    }

    /**
     * 获取消息内容（用于序列化）
     */
    public function toArray(): array
    {
        return [
            'task_id' => $this->taskId,
            'account_id' => $this->accountId,
            'sync_type' => $this->syncType,
            'sync_scope' => $this->syncScope,
            'article_limit' => $this->articleLimit,
            'force_sync' => $this->forceSync,
            'custom_options' => $this->customOptions,
        ];
    }

    /**
     * 从数组创建消息
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['task_id'],
            $data['account_id'],
            $data['sync_type'],
            $data['sync_scope'],
            $data['article_limit'],
            $data['force_sync'] ?? false,
            $data['custom_options'] ?? []
        );
    }

    /**
     * 获取消息的唯一标识符
     */
    public function getUniqueId(): string
    {
        return sprintf('wechat_sync_%s_%s', $this->accountId, $this->taskId);
    }

    /**
     * 获取消息的描述
     */
    public function getDescription(): string
    {
        return sprintf(
            '微信同步任务 - 账号: %s, 类型: %s, 范围: %s, 限制: %d',
            $this->accountId,
            $this->syncType,
            $this->syncScope,
            $this->articleLimit
        );
    }

    /**
     * 获取消息的队列名称
     */
    public function getQueueName(): string
    {
        return 'wechat_sync';
    }

    /**
     * 获取消息的优先级
     */
    public function getPriority(): int
    {
        return $this->forceSync ? 8 : 5;
    }

    /**
     * 获取消息的重试配置
     */
    public function getRetryConfig(): array
    {
        return [
            'max_retries' => 3,
            'delay' => 1000, // 1秒
            'multiplier' => 2,
            'max_delay' => 30000, // 30秒
        ];
    }

    /**
     * 获取消息的超时时间（秒）
     */
    public function getTimeout(): int
    {
        return $this->customOptions['timeout'] ?? 600; // 默认10分钟
    }

    /**
     * 获取消息的TTL（秒）
     */
    public function getTtl(): int
    {
        return $this->customOptions['ttl'] ?? 3600; // 默认1小时
    }

    /**
     * 检查消息是否有效
     */
    public function isValid(): bool
    {
        return !empty($this->taskId) &&
               !empty($this->accountId) &&
               !empty($this->syncType) &&
               !empty($this->syncScope) &&
               $this->articleLimit > 0;
    }

    /**
     * 获取批处理大小
     */
    public function getBatchSize(): int
    {
        return $this->customOptions['batch_size'] ?? 20;
    }

    /**
     * 是否处理媒体资源
     */
    public function shouldProcessMedia(): bool
    {
        return $this->customOptions['process_media'] ?? true;
    }

    /**
     * 是否强制重新下载
     */
    public function shouldForceDownload(): bool
    {
        return $this->customOptions['force_download'] ?? false;
    }

    /**
     * 获取回调URL
     */
    public function getCallbackUrl(): ?string
    {
        return $this->customOptions['callback_url'] ?? null;
    }

    /**
     * 获取创建者信息
     */
    public function getCreatedBy(): ?string
    {
        return $this->customOptions['created_by'] ?? null;
    }

    /**
     * 获取过期时间
     */
    public function getExpiresAt(): ?\DateTime
    {
        $ttl = $this->getTtl();
        $expiresAt = new \DateTime();
        $expiresAt->add(new \DateInterval("PT{$ttl}S"));

        return $expiresAt;
    }

    /**
     * 转换为任务负载
     */
    public function toTaskPayload(): array
    {
        return [
            'account_id' => $this->accountId,
            'sync_type' => $this->syncType,
            'sync_scope' => $this->syncScope,
            'article_limit' => $this->articleLimit,
            'force_sync' => $this->forceSync,
            'batch_size' => $this->getBatchSize(),
            'process_media' => $this->shouldProcessMedia(),
            'force_download' => $this->shouldForceDownload(),
            'callback_url' => $this->getCallbackUrl(),
            'timeout' => $this->getTimeout(),
            'custom_options' => $this->customOptions,
        ];
    }

    /**
     * 创建微信同步消息
     */
    public static function create(
        string $taskId,
        string $accountId,
        string $syncType = 'articles',
        string $syncScope = 'recent',
        int $articleLimit = 100,
        bool $forceSync = false,
        array $customOptions = []
    ): self {
        return new self(
            $taskId,
            $accountId,
            $syncType,
            $syncScope,
            $articleLimit,
            $forceSync,
            $customOptions
        );
    }

    /**
     * 创建全量同步消息
     */
    public static function createFullSync(
        string $taskId,
        string $accountId,
        int $articleLimit = 1000,
        array $customOptions = []
    ): self {
        return new self(
            $taskId,
            $accountId,
            'articles',
            'all',
            $articleLimit,
            true,
            $customOptions
        );
    }

    /**
     * 创建增量同步消息
     */
    public static function createIncrementalSync(
        string $taskId,
        string $accountId,
        int $articleLimit = 100,
        array $customOptions = []
    ): self {
        return new self(
            $taskId,
            $accountId,
            'articles',
            'recent',
            $articleLimit,
            false,
            $customOptions
        );
    }

    /**
     * 创建强制同步消息
     */
    public static function createForceSync(
        string $taskId,
        string $accountId,
        string $syncScope = 'recent',
        int $articleLimit = 100,
        array $customOptions = []
    ): self {
        return new self(
            $taskId,
            $accountId,
            'articles',
            $syncScope,
            $articleLimit,
            true,
            $customOptions
        );
    }
}
