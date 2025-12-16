<?php

namespace App\DTO\Response;

use App\DTO\Base\AbstractResponseDto;
use OpenApi\Attributes as OA;

/**
 * 微信同步进度详情响应DTO
 */
#[OA\Schema(
    schema: 'WechatSyncProgressDto',
    title: '微信同步进度详情响应',
    description: '微信同步任务进度详情数据结构'
)]
class WechatSyncProgressDto extends AbstractResponseDto
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
     * 总体进度
     */
    #[OA\Property(
        description: '总体进度信息',
        properties: [
            'percentage' => new OA\Property(
                description: '总体进度百分比',
                type: 'integer',
                example: 65,
                minimum: 0,
                maximum: 100
            ),
            'processed' => new OA\Property(
                description: '已处理数量',
                type: 'integer',
                example: 65,
                minimum: 0
            ),
            'total' => new OA\Property(
                description: '总数量',
                type: 'integer',
                example: 100,
                minimum: 0
            ),
            'remaining' => new OA\Property(
                description: '剩余数量',
                type: 'integer',
                example: 35,
                minimum: 0
            )
        ],
        type: 'object'
    )]
    protected array $overallProgress = [];

    /**
     * 当前阶段
     */
    #[OA\Property(
        description: '当前执行阶段信息',
        properties: [
            'name' => new OA\Property(
                description: '阶段名称',
                type: 'string',
                example: '处理文章内容'
            ),
            'step' => new OA\Property(
                description: '当前步骤序号',
                type: 'integer',
                example: 3,
                minimum: 1
            ),
            'totalSteps' => new OA\Property(
                description: '总步骤数',
                type: 'integer',
                example: 5,
                minimum: 1
            ),
            'percentage' => new OA\Property(
                description: '当前阶段进度百分比',
                type: 'integer',
                example: 80,
                minimum: 0,
                maximum: 100
            ),
            'startedAt' => new OA\Property(
                description: '阶段开始时间',
                type: 'string',
                example: '2024-01-15 10:32:00',
                format: 'date-time'
            ),
            'estimatedCompletion' => new OA\Property(
                description: '阶段预计完成时间',
                type: 'string',
                example: '2024-01-15 10:35:00',
                format: 'date-time'
            )
        ],
        type: 'object'
    )]
    protected array $currentPhase = [];

    /**
     * 阶段列表
     */
    #[OA\Property(
        description: '所有执行阶段列表',
        type: 'array',
        items: new OA\Items(
            properties: [
                'name' => new OA\Property(
                    description: '阶段名称',
                    type: 'string',
                    example: '获取文章列表'
                ),
                'step' => new OA\Property(
                    description: '步骤序号',
                    type: 'integer',
                    example: 1
                ),
                'status' => new OA\Property(
                    description: '阶段状态',
                    type: 'string',
                    enum: ['pending', 'running', 'completed', 'failed', 'skipped'],
                    example: 'completed'
                ),
                'percentage' => new OA\Property(
                    description: '阶段进度百分比',
                    type: 'integer',
                    example: 100
                ),
                'duration' => new OA\Property(
                    description: '阶段执行时长（秒）',
                    type: 'integer',
                    example: 45
                ),
                'processed' => new OA\Property(
                    description: '阶段已处理数量',
                    type: 'integer',
                    example: 100
                ),
                'total' => new OA\Property(
                    description: '阶段总数量',
                    type: 'integer',
                    example: 100
                ),
                'errors' => new OA\Property(
                    description: '阶段错误数',
                    type: 'integer',
                    example: 0
                ),
                'startedAt' => new OA\Property(
                    description: '阶段开始时间',
                    type: 'string',
                    example: '2024-01-15 10:30:00'
                ),
                'completedAt' => new OA\Property(
                    description: '阶段完成时间',
                    type: 'string',
                    example: '2024-01-15 10:30:45'
                )
            ],
            type: 'object'
        )
    )]
    protected array $phases = [];

    /**
     * 实时指标
     */
    #[OA\Property(
        description: '实时性能指标',
        properties: [
            'processingSpeed' => new OA\Property(
                description: '处理速度（条/分钟）',
                type: 'number',
                example: 15.5
            ),
            'averageTimePerItem' => new OA\Property(
                description: '平均处理时间（毫秒）',
                type: 'integer',
                example: 3500
            ),
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
            'networkLatency' => new OA\Property(
                description: '网络延迟（毫秒）',
                type: 'integer',
                example: 150
            ),
            'cacheHitRate' => new OA\Property(
                description: '缓存命中率（%）',
                type: 'number',
                example: 85.5
            ),
            'queuePosition' => new OA\Property(
                description: '队列位置',
                type: 'integer',
                example: 2
            )
        ],
        type: 'object'
    )]
    protected array $realTimeMetrics = [];

    /**
     * 时间统计
     */
    #[OA\Property(
        description: '时间统计信息',
        properties: [
            'totalElapsed' => new OA\Property(
                description: '总已用时间（秒）',
                type: 'integer',
                example: 300
            ),
            'estimatedTotal' => new OA\Property(
                description: '预计总时间（秒）',
                type: 'integer',
                example: 462
            ),
            'remaining' => new OA\Property(
                description: '剩余时间（秒）',
                type: 'integer',
                example: 162
            ),
            'eta' => new OA\Property(
                description: '预计完成时间',
                type: 'string',
                example: '2024-01-15 10:37:42',
                format: 'date-time'
            ),
            'averagePhaseTime' => new OA\Property(
                description: '平均阶段时间（秒）',
                type: 'integer',
                example: 92
            )
        ],
        type: 'object'
    )]
    protected array $timeStatistics = [];

    /**
     * 错误统计
     */
    #[OA\Property(
        description: '错误统计信息',
        properties: [
            'totalErrors' => new OA\Property(
                description: '总错误数',
                type: 'integer',
                example: 5
            ),
            'criticalErrors' => new OA\Property(
                description: '严重错误数',
                type: 'integer',
                example: 1
            ),
            'warningErrors' => new OA\Property(
                description: '警告错误数',
                type: 'integer',
                example: 4
            ),
            'errorRate' => new OA\Property(
                description: '错误率（%）',
                type: 'number',
                example: 5.0
            ),
            'recentErrors' => new OA\Property(
                description: '最近错误列表',
                type: 'array',
                items: new OA\Items(
                    properties: [
                        'timestamp' => new OA\Property(
                            description: '错误时间',
                            type: 'string',
                            example: '2024-01-15 10:34:15'
                        ),
                        'phase' => new OA\Property(
                            description: '错误阶段',
                            type: 'string',
                            example: '处理文章内容'
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
                        'recoverable' => new OA\Property(
                            description: '是否可恢复',
                            type: 'boolean',
                            example: true
                        )
                    ],
                    type: 'object'
                )
            )
        ],
        type: 'object'
    )]
    protected array $errorStatistics = [];

    /**
     * 资源使用情况
     */
    #[OA\Property(
        description: '资源使用情况',
        properties: [
            'memory' => new OA\Property(
                description: '内存使用详情',
                properties: [
                    'used' => new OA\Property(
                        description: '已使用内存（MB）',
                        type: 'number',
                        example: 128.5
                    ),
                    'peak' => new OA\Property(
                        description: '峰值内存（MB）',
                        type: 'number',
                        example: 156.8
                    ),
                    'limit' => new OA\Property(
                        description: '内存限制（MB）',
                        type: 'integer',
                        example: 512
                    ),
                    'usagePercentage' => new OA\Property(
                        description: '内存使用率（%）',
                        type: 'number',
                        example: 25.1
                    )
                ],
                type: 'object'
            ),
            'network' => new OA\Property(
                description: '网络使用详情',
                properties: [
                    'requests' => new OA\Property(
                        description: '网络请求数',
                        type: 'integer',
                        example: 150
                    ),
                    'bytesTransferred' => new OA\Property(
                        description: '传输字节数',
                        type: 'integer',
                        example: 5242880
                    ),
                    'averageLatency' => new OA\Property(
                        description: '平均延迟（毫秒）',
                        type: 'integer',
                        example: 150
                    ),
                    'failures' => new OA\Property(
                        description: '网络失败数',
                        type: 'integer',
                        example: 3
                    )
                ],
                type: 'object'
            ),
            'storage' => new OA\Property(
                description: '存储使用详情',
                properties: [
                    'filesCreated' => new OA\Property(
                        description: '创建文件数',
                        type: 'integer',
                        example: 85
                    ),
                    'bytesWritten' => new OA\Property(
                        description: '写入字节数',
                        type: 'integer',
                        example: 10485760
                    ),
                    'tempFiles' => new OA\Property(
                        description: '临时文件数',
                        type: 'integer',
                        example: 12
                    )
                ],
                type: 'object'
            )
        ],
        type: 'object'
    )]
    protected array $resourceUsage = [];

    /**
     * 队列信息
     */
    #[OA\Property(
        description: '队列信息',
        properties: [
            'name' => new OA\Property(
                description: '队列名称',
                type: 'string',
                example: 'wechat_sync'
            ),
            'position' => new OA\Property(
                description: '队列位置',
                type: 'integer',
                example: 2
            ),
            'size' => new OA\Property(
                description: '队列大小',
                type: 'integer',
                example: 5
            ),
            'priority' => new OA\Property(
                description: '任务优先级',
                type: 'integer',
                example: 5
            ),
            'estimatedWaitTime' => new OA\Property(
                description: '预计等待时间（秒）',
                type: 'integer',
                example: 120
            )
        ],
        type: 'object'
    )]
    protected array $queueInfo = [];

    /**
     * 最后更新时间
     */
    #[OA\Property(
        description: '进度最后更新时间',
        example: '2024-01-15 10:35:30',
        format: 'date-time'
    )]
    protected string $lastUpdated;

    /**
     * 构造函数
     */
    public function __construct(array $data = [])
    {
        parent::__construct();
        $this->lastUpdated = date('Y-m-d H:i:s');
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
     * 获取总体进度
     */
    public function getOverallProgress(): array
    {
        return $this->overallProgress;
    }

    /**
     * 设置总体进度
     */
    public function setOverallProgress(array $overallProgress): self
    {
        $this->overallProgress = $overallProgress;
        return $this;
    }

    /**
     * 更新总体进度
     */
    public function updateOverallProgress(int $processed, int $total): self
    {
        $percentage = $total > 0 ? (int)(($processed / $total) * 100) : 0;
        $this->overallProgress = [
            'percentage' => min(100, max(0, $percentage)),
            'processed' => max(0, $processed),
            'total' => max(0, $total),
            'remaining' => max(0, $total - $processed)
        ];
        return $this;
    }

    /**
     * 获取当前阶段
     */
    public function getCurrentPhase(): array
    {
        return $this->currentPhase;
    }

    /**
     * 设置当前阶段
     */
    public function setCurrentPhase(array $currentPhase): self
    {
        $this->currentPhase = $currentPhase;
        return $this;
    }

    /**
     * 更新当前阶段
     */
    public function updateCurrentPhase(string $name, int $step, int $totalSteps, int $percentage, ?string $startedAt = null, ?string $estimatedCompletion = null): self
    {
        $this->currentPhase = [
            'name' => $name,
            'step' => max(1, $step),
            'totalSteps' => max(1, $totalSteps),
            'percentage' => min(100, max(0, $percentage)),
            'startedAt' => $startedAt,
            'estimatedCompletion' => $estimatedCompletion
        ];
        return $this;
    }

    /**
     * 获取阶段列表
     */
    public function getPhases(): array
    {
        return $this->phases;
    }

    /**
     * 设置阶段列表
     */
    public function setPhases(array $phases): self
    {
        $this->phases = $phases;
        return $this;
    }

    /**
     * 添加阶段
     */
    public function addPhase(array $phase): self
    {
        $this->phases[] = $phase;
        return $this;
    }

    /**
     * 更新阶段状态
     */
    public function updatePhaseStatus(int $step, string $status, ?int $percentage = null, ?int $duration = null): self
    {
        foreach ($this->phases as $index => $phase) {
            if ($phase['step'] === $step) {
                $this->phases[$index]['status'] = $status;
                if ($percentage !== null) {
                    $this->phases[$index]['percentage'] = $percentage;
                }
                if ($duration !== null) {
                    $this->phases[$index]['duration'] = $duration;
                }
                if ($status === 'completed') {
                    $this->phases[$index]['completedAt'] = date('Y-m-d H:i:s');
                }
                break;
            }
        }
        return $this;
    }

    /**
     * 获取实时指标
     */
    public function getRealTimeMetrics(): array
    {
        return $this->realTimeMetrics;
    }

    /**
     * 设置实时指标
     */
    public function setRealTimeMetrics(array $realTimeMetrics): self
    {
        $this->realTimeMetrics = $realTimeMetrics;
        return $this;
    }

    /**
     * 更新实时指标
     */
    public function updateRealTimeMetrics(float $processingSpeed, int $averageTimePerItem, float $memoryUsage, float $cpuUsage, ?int $networkLatency = null, ?float $cacheHitRate = null, ?int $queuePosition = null): self
    {
        $this->realTimeMetrics = [
            'processingSpeed' => $processingSpeed,
            'averageTimePerItem' => $averageTimePerItem,
            'memoryUsage' => $memoryUsage,
            'cpuUsage' => $cpuUsage,
            'networkLatency' => $networkLatency,
            'cacheHitRate' => $cacheHitRate,
            'queuePosition' => $queuePosition
        ];
        return $this;
    }

    /**
     * 获取时间统计
     */
    public function getTimeStatistics(): array
    {
        return $this->timeStatistics;
    }

    /**
     * 设置时间统计
     */
    public function setTimeStatistics(array $timeStatistics): self
    {
        $this->timeStatistics = $timeStatistics;
        return $this;
    }

    /**
     * 更新时间统计
     */
    public function updateTimeStatistics(int $totalElapsed, ?int $estimatedTotal = null, ?int $remaining = null, ?string $eta = null): self
    {
        $this->timeStatistics = [
            'totalElapsed' => $totalElapsed,
            'estimatedTotal' => $estimatedTotal,
            'remaining' => $remaining,
            'eta' => $eta,
            'averagePhaseTime' => $totalElapsed / max(1, count($this->phases))
        ];
        return $this;
    }

    /**
     * 获取错误统计
     */
    public function getErrorStatistics(): array
    {
        return $this->errorStatistics;
    }

    /**
     * 设置错误统计
     */
    public function setErrorStatistics(array $errorStatistics): self
    {
        $this->errorStatistics = $errorStatistics;
        return $this;
    }

    /**
     * 添加错误
     */
    public function addError(string $phase, string $message, ?string $code = null, bool $recoverable = true): self
    {
        if (!isset($this->errorStatistics['recentErrors'])) {
            $this->errorStatistics['recentErrors'] = [];
        }

        $error = [
            'timestamp' => date('Y-m-d H:i:s'),
            'phase' => $phase,
            'message' => $message,
            'code' => $code,
            'recoverable' => $recoverable
        ];

        array_unshift($this->errorStatistics['recentErrors'], $error);

        // 只保留最近10个错误
        $this->errorStatistics['recentErrors'] = array_slice($this->errorStatistics['recentErrors'], 0, 10);

        // 更新错误计数
        $this->errorStatistics['totalErrors'] = ($this->errorStatistics['totalErrors'] ?? 0) + 1;

        if (!$recoverable) {
            $this->errorStatistics['criticalErrors'] = ($this->errorStatistics['criticalErrors'] ?? 0) + 1;
        } else {
            $this->errorStatistics['warningErrors'] = ($this->errorStatistics['warningErrors'] ?? 0) + 1;
        }

        // 计算错误率
        $totalProcessed = $this->overallProgress['processed'] ?? 0;
        if ($totalProcessed > 0) {
            $this->errorStatistics['errorRate'] = round(($this->errorStatistics['totalErrors'] / $totalProcessed) * 100, 2);
        }

        return $this;
    }

    /**
     * 获取资源使用情况
     */
    public function getResourceUsage(): array
    {
        return $this->resourceUsage;
    }

    /**
     * 设置资源使用情况
     */
    public function setResourceUsage(array $resourceUsage): self
    {
        $this->resourceUsage = $resourceUsage;
        return $this;
    }

    /**
     * 获取队列信息
     */
    public function getQueueInfo(): array
    {
        return $this->queueInfo;
    }

    /**
     * 设置队列信息
     */
    public function setQueueInfo(array $queueInfo): self
    {
        $this->queueInfo = $queueInfo;
        return $this;
    }

    /**
     * 获取最后更新时间
     */
    public function getLastUpdated(): string
    {
        return $this->lastUpdated;
    }

    /**
     * 设置最后更新时间
     */
    public function setLastUpdated(string $lastUpdated): self
    {
        $this->lastUpdated = $this->cleanString($lastUpdated);
        return $this;
    }

    /**
     * 更新最后更新时间
     */
    public function updateLastUpdated(): self
    {
        $this->lastUpdated = date('Y-m-d H:i:s');
        return $this;
    }

    /**
     * 计算预计完成时间
     */
    public function calculateETA(): ?string
    {
        if (!isset($this->overallProgress['percentage']) || $this->overallProgress['percentage'] <= 0) {
            return null;
        }

        if (!isset($this->timeStatistics['totalElapsed'])) {
            return null;
        }

        $percentage = $this->overallProgress['percentage'];
        $elapsed = $this->timeStatistics['totalElapsed'];

        if ($percentage >= 100) {
            return null;
        }

        $estimatedTotal = ($elapsed / $percentage) * 100;
        $remaining = $estimatedTotal - $elapsed;
        $eta = time() + $remaining;

        $this->timeStatistics['estimatedTotal'] = (int)$estimatedTotal;
        $this->timeStatistics['remaining'] = (int)$remaining;
        $this->timeStatistics['eta'] = date('Y-m-d H:i:s', $eta);

        return $this->timeStatistics['eta'];
    }

    /**
     * 获取进度摘要
     */
    public function getProgressSummary(): array
    {
        return [
            'taskId' => $this->taskId,
            'accountId' => $this->accountId,
            'overallPercentage' => $this->overallProgress['percentage'] ?? 0,
            'currentPhase' => $this->currentPhase['name'] ?? '',
            'processingSpeed' => $this->realTimeMetrics['processingSpeed'] ?? 0,
            'eta' => $this->timeStatistics['eta'] ?? null,
            'errorCount' => $this->errorStatistics['totalErrors'] ?? 0,
            'lastUpdated' => $this->lastUpdated
        ];
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'taskId' => $this->taskId,
            'accountId' => $this->accountId,
            'overallProgress' => $this->overallProgress,
            'currentPhase' => $this->currentPhase,
            'phases' => $this->phases,
            'realTimeMetrics' => $this->realTimeMetrics,
            'timeStatistics' => $this->timeStatistics,
            'errorStatistics' => $this->errorStatistics,
            'resourceUsage' => $this->resourceUsage,
            'queueInfo' => $this->queueInfo,
            'lastUpdated' => $this->lastUpdated,
            'progressSummary' => $this->getProgressSummary()
        ]);
    }
}
