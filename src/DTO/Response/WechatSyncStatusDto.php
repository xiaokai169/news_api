<?php

namespace App\DTO\Response;

use App\DTO\Base\AbstractResponseDto;
use OpenApi\Attributes as OA;

/**
 * 微信同步状态响应DTO
 */
#[OA\Schema(
    schema: 'WechatSyncStatusDto',
    title: '微信同步状态响应',
    description: '微信同步任务状态查询响应数据结构'
)]
class WechatSyncStatusDto extends AbstractResponseDto
{
    /**
     * 任务ID
     */
    #[OA\Property(
        description: '异步任务ID',
        example: '550e8400-e29b-41d4-a716-446655440000'
    )]
    protected string $taskId = '';

    /**
     * 公众号ID
     */
    #[OA\Property(
        description: '微信公众号ID',
        example: 'wx1234567890abcdef'
    )]
    protected string $accountId = '';

    /**
     * 任务状态
     */
    #[OA\Property(
        description: '任务状态',
        enum: ['pending', 'running', 'completed', 'failed', 'cancelled', 'retrying'],
        example: 'running'
    )]
    protected string $status = '';

    /**
     * 同步类型
     */
    #[OA\Property(
        description: '同步类型',
        enum: ['info', 'articles', 'menu', 'all'],
        example: 'articles'
    )]
    protected string $syncType = '';

    /**
     * 同步范围
     */
    #[OA\Property(
        description: '同步范围',
        enum: ['recent', 'all', 'custom'],
        example: 'recent'
    )]
    protected string $syncScope = '';

    /**
     * 进度百分比
     */
    #[OA\Property(
        description: '同步进度百分比（0-100）',
        example: 65,
        minimum: 0,
        maximum: 100
    )]
    protected int $progressPercentage = 0;

    /**
     * 当前步骤
     */
    #[OA\Property(
        description: '当前执行步骤',
        example: '处理文章内容'
    )]
    protected string $currentStep = '';

    /**
     * 已处理数量
     */
    #[OA\Property(
        description: '已处理的数量',
        example: 65,
        minimum: 0
    )]
    protected int $processedCount = 0;

    /**
     * 总数量
     */
    #[OA\Property(
        description: '需要处理的总数量',
        example: 100,
        minimum: 0
    )]
    protected int $totalCount = 0;

    /**
     * 成功数量
     */
    #[OA\Property(
        description: '处理成功的数量',
        example: 60,
        minimum: 0
    )]
    protected int $successCount = 0;

    /**
     * 失败数量
     */
    #[OA\Property(
        description: '处理失败的数量',
        example: 5,
        minimum: 0
    )]
    protected int $failedCount = 0;

    /**
     * 跳过数量
     */
    #[OA\Property(
        description: '跳过的数量',
        example: 0,
        minimum: 0
    )]
    protected int $skippedCount = 0;

    /**
     * 开始时间
     */
    #[OA\Property(
        description: '任务开始时间',
        example: '2024-01-15 10:30:00',
        format: 'date-time'
    )]
    protected ?string $startedAt = null;

    /**
     * 完成时间
     */
    #[OA\Property(
        description: '任务完成时间',
        example: '2024-01-15 10:35:00',
        format: 'date-time'
    )]
    protected ?string $completedAt = null;

    /**
     * 预计完成时间
     */
    #[OA\Property(
        description: '预计完成时间',
        example: '2024-01-15 10:37:00',
        format: 'date-time'
    )]
    protected ?string $estimatedCompletion = null;

    /**
     * 执行时长（秒）
     */
    #[OA\Property(
        description: '任务执行时长（秒）',
        example: 300,
        minimum: 0
    )]
    protected ?int $durationSeconds = null;

    /**
     * 错误信息
     */
    #[OA\Property(
        description: '错误信息',
        example: '网络连接超时'
    )]
    protected ?string $errorMessage = null;

    /**
     * 错误详情
     */
    #[OA\Property(
        description: '详细错误信息列表',
        type: 'array',
        items: new OA\Items(
            properties: [
                'step' => new OA\Property(
                    description: '出错步骤',
                    type: 'string',
                    example: '下载媒体资源'
                ),
                'message' => new OA\Property(
                    description: '错误消息',
                    type: 'string',
                    example: '图片下载失败'
                ),
                'code' => new OA\Property(
                    description: '错误代码',
                    type: 'string',
                    example: 'DOWNLOAD_FAILED'
                ),
                'count' => new OA\Property(
                    description: '错误次数',
                    type: 'integer',
                    example: 3
                )
            ],
            type: 'object'
        )
    )]
    protected array $errorDetails = [];

    /**
     * 统计信息
     */
    #[OA\Property(
        description: '同步统计信息',
        properties: [
            'articlesProcessed' => new OA\Property(
                description: '已处理文章数',
                type: 'integer',
                example: 65
            ),
            'mediaDownloaded' => new OA\Property(
                description: '已下载媒体数',
                type: 'integer',
                example: 120
            ),
            'databaseOperations' => new OA\Property(
                description: '数据库操作数',
                type: 'integer',
                example: 85
            ),
            'averageProcessTime' => new OA\Property(
                description: '平均处理时间（毫秒）',
                type: 'integer',
                example: 1500
            )
        ],
        type: 'object'
    )]
    protected array $statistics = [];

    /**
     * 性能指标
     */
    #[OA\Property(
        description: '性能指标',
        properties: [
            'memoryUsage' => new OA\Property(
                description: '内存使用量（MB）',
                type: 'number',
                example: 128.5
            ),
            'cpuUsage' => new OA\Property(
                description: 'CPU使用率（%）',
                type: 'number',
                example: 45.2
            ),
            'networkRequests' => new OA\Property(
                description: '网络请求数',
                type: 'integer',
                example: 200
            ),
            'cacheHitRate' => new OA\Property(
                description: '缓存命中率（%）',
                type: 'number',
                example: 85.5
            )
        ],
        type: 'object'
    )]
    protected array $performanceMetrics = [];

    /**
     * 重试次数
     */
    #[OA\Property(
        description: '重试次数',
        example: 2,
        minimum: 0
    )]
    protected int $retryCount = 0;

    /**
     * 最大重试次数
     */
    #[OA\Property(
        description: '最大重试次数',
        example: 3,
        minimum: 0
    )]
    protected int $maxRetries = 3;

    /**
     * 是否可重试
     */
    #[OA\Property(
        description: '是否可以重试',
        example: true
    )]
    protected bool $canRetry = false;

    /**
     * 任务优先级
     */
    #[OA\Property(
        description: '任务优先级（1-10）',
        example: 5,
        minimum: 1,
        maximum: 10
    )]
    protected int $priority = 5;

    /**
     * 队列名称
     */
    #[OA\Property(
        description: '任务队列名称',
        example: 'wechat_sync'
    )]
    protected ?string $queueName = null;

    /**
     * 创建者
     */
    #[OA\Property(
        description: '任务创建者',
        example: 'api_user'
    )]
    protected ?string $createdBy = null;

    /**
     * 构造函数
     */
    public function __construct(array $data = [])
    {
        parent::__construct();
        if (!empty($data)) {
            $this->fromArray($data);
        }
    }

    /**
     * 获取任务ID
     */
    public function getTaskId(): string
    {
        return $this->taskId;
    }

    /**
     * 设置任务ID
     */
    public function setTaskId(string $taskId): self
    {
        $this->taskId = $this->cleanString($taskId);
        return $this;
    }

    /**
     * 获取公众号ID
     */
    public function getAccountId(): string
    {
        return $this->accountId;
    }

    /**
     * 设置公众号ID
     */
    public function setAccountId(string $accountId): self
    {
        $this->accountId = $this->cleanString($accountId);
        return $this;
    }

    /**
     * 获取任务状态
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * 设置任务状态
     */
    public function setStatus(string $status): self
    {
        $this->status = $this->cleanString($status);
        return $this;
    }

    /**
     * 获取同步类型
     */
    public function getSyncType(): string
    {
        return $this->syncType;
    }

    /**
     * 设置同步类型
     */
    public function setSyncType(string $syncType): self
    {
        $this->syncType = $this->cleanString($syncType);
        return $this;
    }

    /**
     * 获取同步范围
     */
    public function getSyncScope(): string
    {
        return $this->syncScope;
    }

    /**
     * 设置同步范围
     */
    public function setSyncScope(string $syncScope): self
    {
        $this->syncScope = $this->cleanString($syncScope);
        return $this;
    }

    /**
     * 获取进度百分比
     */
    public function getProgressPercentage(): int
    {
        return $this->progressPercentage;
    }

    /**
     * 设置进度百分比
     */
    public function setProgressPercentage(int $progressPercentage): self
    {
        $this->progressPercentage = max(0, min(100, $progressPercentage));
        return $this;
    }

    /**
     * 获取当前步骤
     */
    public function getCurrentStep(): string
    {
        return $this->currentStep;
    }

    /**
     * 设置当前步骤
     */
    public function setCurrentStep(string $currentStep): self
    {
        $this->currentStep = $this->cleanString($currentStep);
        return $this;
    }

    /**
     * 获取已处理数量
     */
    public function getProcessedCount(): int
    {
        return $this->processedCount;
    }

    /**
     * 设置已处理数量
     */
    public function setProcessedCount(int $processedCount): self
    {
        $this->processedCount = max(0, $processedCount);
        return $this;
    }

    /**
     * 获取总数量
     */
    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    /**
     * 设置总数量
     */
    public function setTotalCount(int $totalCount): self
    {
        $this->totalCount = max(0, $totalCount);
        return $this;
    }

    /**
     * 获取成功数量
     */
    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    /**
     * 设置成功数量
     */
    public function setSuccessCount(int $successCount): self
    {
        $this->successCount = max(0, $successCount);
        return $this;
    }

    /**
     * 获取失败数量
     */
    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    /**
     * 设置失败数量
     */
    public function setFailedCount(int $failedCount): self
    {
        $this->failedCount = max(0, $failedCount);
        return $this;
    }

    /**
     * 获取跳过数量
     */
    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    /**
     * 设置跳过数量
     */
    public function setSkippedCount(int $skippedCount): self
    {
        $this->skippedCount = max(0, $skippedCount);
        return $this;
    }

    /**
     * 获取开始时间
     */
    public function getStartedAt(): ?string
    {
        return $this->startedAt;
    }

    /**
     * 设置开始时间
     */
    public function setStartedAt(?string $startedAt): self
    {
        $this->startedAt = $this->cleanString($startedAt);
        return $this;
    }

    /**
     * 获取完成时间
     */
    public function getCompletedAt(): ?string
    {
        return $this->completedAt;
    }

    /**
     * 设置完成时间
     */
    public function setCompletedAt(?string $completedAt): self
    {
        $this->completedAt = $this->cleanString($completedAt);
        return $this;
    }

    /**
     * 获取预计完成时间
     */
    public function getEstimatedCompletion(): ?string
    {
        return $this->estimatedCompletion;
    }

    /**
     * 设置预计完成时间
     */
    public function setEstimatedCompletion(?string $estimatedCompletion): self
    {
        $this->estimatedCompletion = $this->cleanString($estimatedCompletion);
        return $this;
    }

    /**
     * 获取执行时长
     */
    public function getDurationSeconds(): ?int
    {
        return $this->durationSeconds;
    }

    /**
     * 设置执行时长
     */
    public function setDurationSeconds(?int $durationSeconds): self
    {
        $this->durationSeconds = $durationSeconds && $durationSeconds > 0 ? $durationSeconds : null;
        return $this;
    }

    /**
     * 获取错误信息
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * 设置错误信息
     */
    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $this->cleanString($errorMessage);
        return $this;
    }

    /**
     * 获取错误详情
     */
    public function getErrorDetails(): array
    {
        return $this->errorDetails;
    }

    /**
     * 设置错误详情
     */
    public function setErrorDetails(array $errorDetails): self
    {
        $this->errorDetails = $errorDetails;
        return $this;
    }

    /**
     * 添加错误详情
     */
    public function addErrorDetail(string $step, string $message, ?string $code = null, ?int $count = 1): self
    {
        $this->errorDetails[] = [
            'step' => $step,
            'message' => $message,
            'code' => $code,
            'count' => $count ?? 1
        ];
        return $this;
    }

    /**
     * 获取统计信息
     */
    public function getStatistics(): array
    {
        return $this->statistics;
    }

    /**
     * 设置统计信息
     */
    public function setStatistics(array $statistics): self
    {
        $this->statistics = $statistics;
        return $this;
    }

    /**
     * 获取性能指标
     */
    public function getPerformanceMetrics(): array
    {
        return $this->performanceMetrics;
    }

    /**
     * 设置性能指标
     */
    public function setPerformanceMetrics(array $performanceMetrics): self
    {
        $this->performanceMetrics = $performanceMetrics;
        return $this;
    }

    /**
     * 获取重试次数
     */
    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    /**
     * 设置重试次数
     */
    public function setRetryCount(int $retryCount): self
    {
        $this->retryCount = max(0, $retryCount);
        return $this;
    }

    /**
     * 获取最大重试次数
     */
    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    /**
     * 设置最大重试次数
     */
    public function setMaxRetries(int $maxRetries): self
    {
        $this->maxRetries = max(0, $maxRetries);
        return $this;
    }

    /**
     * 是否可重试
     */
    public function isCanRetry(): bool
    {
        return $this->canRetry;
    }

    /**
     * 设置可重试
     */
    public function setCanRetry(bool $canRetry): self
    {
        $this->canRetry = $canRetry;
        return $this;
    }

    /**
     * 获取优先级
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * 设置优先级
     */
    public function setPriority(int $priority): self
    {
        $this->priority = max(1, min(10, $priority));
        return $this;
    }

    /**
     * 获取队列名称
     */
    public function getQueueName(): ?string
    {
        return $this->queueName;
    }

    /**
     * 设置队列名称
     */
    public function setQueueName(?string $queueName): self
    {
        $this->queueName = $this->cleanString($queueName);
        return $this;
    }

    /**
     * 获取创建者
     */
    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    /**
     * 设置创建者
     */
    public function setCreatedBy(?string $createdBy): self
    {
        $this->createdBy = $this->cleanString($createdBy);
        return $this;
    }

    /**
     * 计算进度百分比
     */
    public function calculateProgress(): int
    {
        if ($this->totalCount <= 0) {
            return 0;
        }

        $progress = (int)(($this->processedCount / $this->totalCount) * 100);
        $this->progressPercentage = min(100, max(0, $progress));

        return $this->progressPercentage;
    }

    /**
     * 检查任务是否完成
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled']);
    }

    /**
     * 检查任务是否运行中
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * 检查是否有错误
     */
    public function hasErrors(): bool
    {
        return !empty($this->errorMessage) || !empty($this->errorDetails);
    }

    /**
     * 获取格式化的执行时长
     */
    public function getFormattedDuration(): string
    {
        if (!$this->durationSeconds) {
            return '0秒';
        }

        $hours = (int)($this->durationSeconds / 3600);
        $minutes = (int)(($this->durationSeconds % 3600) / 60);
        $seconds = $this->durationSeconds % 60;

        if ($hours > 0) {
            return sprintf('%d小时%d分%d秒', $hours, $minutes, $seconds);
        } elseif ($minutes > 0) {
            return sprintf('%d分%d秒', $minutes, $seconds);
        } else {
            return sprintf('%d秒', $seconds);
        }
    }

    /**
     * 获取状态描述
     */
    public function getStatusDescription(): string
    {
        return match ($this->status) {
            'pending' => '等待执行',
            'running' => '正在同步',
            'completed' => '同步完成',
            'failed' => '同步失败',
            'cancelled' => '已取消',
            'retrying' => '重试中',
            default => '未知状态'
        };
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'taskId' => $this->taskId,
            'accountId' => $this->accountId,
            'status' => $this->status,
            'statusDescription' => $this->getStatusDescription(),
            'syncType' => $this->syncType,
            'syncScope' => $this->syncScope,
            'progressPercentage' => $this->progressPercentage,
            'currentStep' => $this->currentStep,
            'processedCount' => $this->processedCount,
            'totalCount' => $this->totalCount,
            'successCount' => $this->successCount,
            'failedCount' => $this->failedCount,
            'skippedCount' => $this->skippedCount,
            'startedAt' => $this->startedAt,
            'completedAt' => $this->completedAt,
            'estimatedCompletion' => $this->estimatedCompletion,
            'durationSeconds' => $this->durationSeconds,
            'formattedDuration' => $this->getFormattedDuration(),
            'errorMessage' => $this->errorMessage,
            'errorDetails' => $this->errorDetails,
            'statistics' => $this->statistics,
            'performanceMetrics' => $this->performanceMetrics,
            'retryCount' => $this->retryCount,
            'maxRetries' => $this->maxRetries,
            'canRetry' => $this->canRetry,
            'priority' => $this->priority,
            'queueName' => $this->queueName,
            'createdBy' => $this->createdBy,
            'isCompleted' => $this->isCompleted(),
            'isRunning' => $this->isRunning(),
            'hasErrors' => $this->hasErrors()
        ]);
    }
}
