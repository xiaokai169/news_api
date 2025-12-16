<?php

namespace App\Entity;

use App\Repository\QueueStatisticsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: QueueStatisticsRepository::class)]
#[ORM\Table(name: 'queue_statistics')]
#[ORM\UniqueConstraint(name: 'uk_queue_date_hour', columns: ['queue_name', 'stat_date', 'stat_hour'])]
#[ORM\Index(name: 'idx_stat_date', columns: ['stat_date'])]
#[ORM\HasLifecycleCallbacks]
class QueueStatistics
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    #[Groups(['queue_stats:read'])]
    private ?int $id = null;

    #[ORM\Column(name: 'queue_name', type: Types::STRING, length: 50)]
    #[Groups(['queue_stats:read'])]
    private string $queueName;

    #[ORM\Column(name: 'stat_date', type: Types::DATE_MUTABLE)]
    #[Groups(['queue_stats:read'])]
    private \DateTimeInterface $statDate;

    #[ORM\Column(name: 'stat_hour', type: Types::SMALLINT)]
    #[Groups(['queue_stats:read'])]
    private int $statHour;

    #[ORM\Column(name: 'enqueued_count', type: Types::INTEGER)]
    #[Groups(['queue_stats:read'])]
    private int $enqueuedCount = 0;

    #[ORM\Column(name: 'dequeued_count', type: Types::INTEGER)]
    #[Groups(['queue_stats:read'])]
    private int $dequeuedCount = 0;

    #[ORM\Column(name: 'completed_count', type: Types::INTEGER)]
    #[Groups(['queue_stats:read'])]
    private int $completedCount = 0;

    #[ORM\Column(name: 'failed_count', type: Types::INTEGER)]
    #[Groups(['queue_stats:read'])]
    private int $failedCount = 0;

    #[ORM\Column(name: 'avg_duration_ms', type: Types::INTEGER, nullable: true)]
    #[Groups(['queue_stats:read'])]
    private ?int $avgDurationMs = null;

    #[ORM\Column(name: 'max_duration_ms', type: Types::INTEGER, nullable: true)]
    #[Groups(['queue_stats:read'])]
    private ?int $maxDurationMs = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    #[Groups(['queue_stats:read'])]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQueueName(): string
    {
        return $this->queueName;
    }

    public function setQueueName(string $queueName): self
    {
        $this->queueName = $queueName;
        return $this;
    }

    public function getStatDate(): \DateTimeInterface
    {
        return $this->statDate;
    }

    public function setStatDate(\DateTimeInterface $statDate): self
    {
        $this->statDate = $statDate;
        return $this;
    }

    public function getStatHour(): int
    {
        return $this->statHour;
    }

    public function setStatHour(int $statHour): self
    {
        $this->statHour = $statHour;
        return $this;
    }

    public function getEnqueuedCount(): int
    {
        return $this->enqueuedCount;
    }

    public function setEnqueuedCount(int $enqueuedCount): self
    {
        $this->enqueuedCount = $enqueuedCount;
        return $this;
    }

    public function getDequeuedCount(): int
    {
        return $this->dequeuedCount;
    }

    public function setDequeuedCount(int $dequeuedCount): self
    {
        $this->dequeuedCount = $dequeuedCount;
        return $this;
    }

    public function getCompletedCount(): int
    {
        return $this->completedCount;
    }

    public function setCompletedCount(int $completedCount): self
    {
        $this->completedCount = $completedCount;
        return $this;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    public function setFailedCount(int $failedCount): self
    {
        $this->failedCount = $failedCount;
        return $this;
    }

    public function getAvgDurationMs(): ?int
    {
        return $this->avgDurationMs;
    }

    public function setAvgDurationMs(?int $avgDurationMs): self
    {
        $this->avgDurationMs = $avgDurationMs;
        return $this;
    }

    public function getMaxDurationMs(): ?int
    {
        return $this->maxDurationMs;
    }

    public function setMaxDurationMs(?int $maxDurationMs): self
    {
        $this->maxDurationMs = $maxDurationMs;
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

    /**
     * 增加入队数量
     */
    public function incrementEnqueuedCount(int $count = 1): self
    {
        $this->enqueuedCount += $count;
        return $this;
    }

    /**
     * 增加出队数量
     */
    public function incrementDequeuedCount(int $count = 1): self
    {
        $this->dequeuedCount += $count;
        return $this;
    }

    /**
     * 增加完成数量
     */
    public function incrementCompletedCount(int $count = 1): self
    {
        $this->completedCount += $count;
        return $this;
    }

    /**
     * 增加失败数量
     */
    public function incrementFailedCount(int $count = 1): self
    {
        $this->failedCount += $count;
        return $this;
    }

    /**
     * 更新执行时长统计
     */
    public function updateDurationStats(int $durationMs): self
    {
        if ($this->avgDurationMs === null) {
            $this->avgDurationMs = $durationMs;
            $this->maxDurationMs = $durationMs;
        } else {
            // 计算新的平均时长
            $totalCompleted = $this->completedCount;
            if ($totalCompleted > 0) {
                $this->avgDurationMs = (int)(($this->avgDurationMs * ($totalCompleted - 1) + $durationMs) / $totalCompleted);
            }

            // 更新最大时长
            if ($this->maxDurationMs === null || $durationMs > $this->maxDurationMs) {
                $this->maxDurationMs = $durationMs;
            }
        }

        return $this;
    }

    /**
     * 获取总处理数量
     */
    public function getTotalProcessedCount(): int
    {
        return $this->completedCount + $this->failedCount;
    }

    /**
     * 获取成功率（百分比）
     */
    public function getSuccessRate(): ?float
    {
        $totalProcessed = $this->getTotalProcessedCount();
        if ($totalProcessed === 0) {
            return null;
        }

        return round(($this->completedCount / $totalProcessed) * 100, 2);
    }

    /**
     * 获取失败率（百分比）
     */
    public function getFailureRate(): ?float
    {
        $totalProcessed = $this->getTotalProcessedCount();
        if ($totalProcessed === 0) {
            return null;
        }

        return round(($this->failedCount / $totalProcessed) * 100, 2);
    }

    /**
     * 获取平均执行时长（秒）
     */
    public function getAvgDurationSeconds(): ?float
    {
        if ($this->avgDurationMs === null) {
            return null;
        }

        return round($this->avgDurationMs / 1000, 2);
    }

    /**
     * 获取最大执行时长（秒）
     */
    public function getMaxDurationSeconds(): ?float
    {
        if ($this->maxDurationMs === null) {
            return null;
        }

        return round($this->maxDurationMs / 1000, 2);
    }

    /**
     * 创建当前小时的统计记录
     */
    public static function createForCurrentHour(string $queueName): self
    {
        $now = new \DateTime();
        $stat = new self();
        $stat->setQueueName($queueName);
        $stat->setStatDate($now);
        $stat->setStatHour((int)$now->format('H'));

        return $stat;
    }

    /**
     * 获取统计时间段的开始时间
     */
    public function getPeriodStart(): \DateTimeInterface
    {
        $dateTime = \DateTime::createFromFormat('Y-m-d H:i:s',
            $this->statDate->format('Y-m-d') . ' ' . sprintf('%02d:00:00', $this->statHour)
        );

        return $dateTime ?: new \DateTime();
    }

    /**
     * 获取统计时间段的结束时间
     */
    public function getPeriodEnd(): \DateTimeInterface
    {
        $start = $this->getPeriodStart();
        return (clone $start)->modify('+1 hour - 1 second');
    }

    /**
     * 获取格式化的统计时间段
     */
    public function getFormattedPeriod(): string
    {
        return $this->statDate->format('Y-m-d') . ' ' . sprintf('%02d:00-%02d:59', $this->statHour, $this->statHour);
    }
}
