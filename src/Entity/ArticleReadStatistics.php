<?php

namespace App\Entity;

use App\Repository\ArticleReadStatisticsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ArticleReadStatisticsRepository::class)]
#[ORM\Table(name: 'article_read_statistics')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['article_id'], name: 'idx_article_read_statistics_article_id')]
#[ORM\Index(columns: ['stat_date'], name: 'idx_article_read_statistics_stat_date')]
#[ORM\UniqueConstraint(name: 'unique_article_date', columns: ['article_id', 'stat_date'])]
class ArticleReadStatistics
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['articleReadStatistics:read'])]
    private ?int $id = null;

    #[ORM\Column(name: 'article_id', type: Types::INTEGER)]
    #[Groups(['articleReadStatistics:read', 'articleReadStatistics:write'])]
    private int $articleId = 0;

    #[ORM\Column(name: 'stat_date', type: Types::DATE_MUTABLE)]
    #[Groups(['articleReadStatistics:read', 'articleReadStatistics:write'])]
    private \DateTimeInterface $statDate;

    #[ORM\Column(name: 'total_reads', type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['articleReadStatistics:read', 'articleReadStatistics:write'])]
    private int $totalReads = 0;

    #[ORM\Column(name: 'unique_users', type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['articleReadStatistics:read', 'articleReadStatistics:write'])]
    private int $uniqueUsers = 0;

    #[ORM\Column(name: 'anonymous_reads', type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['articleReadStatistics:read', 'articleReadStatistics:write'])]
    private int $anonymousReads = 0;

    #[ORM\Column(name: 'registered_reads', type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['articleReadStatistics:read', 'articleReadStatistics:write'])]
    private int $registeredReads = 0;

    #[ORM\Column(name: 'avg_duration_seconds', type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['articleReadStatistics:read', 'articleReadStatistics:write'])]
    private string $avgDurationSeconds = '0.00';

    #[ORM\Column(name: 'completion_rate', type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['articleReadStatistics:read', 'articleReadStatistics:write'])]
    private string $completionRate = '0.00';

    #[ORM\Column(name: 'create_at', type: Types::DATETIME_MUTABLE)]
    #[Groups(['articleReadStatistics:read'])]
    private \DateTimeInterface $createAt;

    #[ORM\Column(name: 'update_at', type: Types::DATETIME_MUTABLE)]
    #[Groups(['articleReadStatistics:read'])]
    private \DateTimeInterface $updateAt;

    public function __construct()
    {
        $this->createAt = new \DateTime();
        $this->updateAt = new \DateTime();
    }

    #[ORM\PrePersist]
    private function setCreateAtValue(): void
    {
        if ($this->createAt === null) {
            $this->createAt = new \DateTime();
        }
        $this->updateAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    private function setUpdateAtValue(): void
    {
        $this->updateAt = new \DateTime();
    }

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArticleId(): int
    {
        return $this->articleId;
    }

    public function setArticleId(int $articleId): self
    {
        $this->articleId = $articleId;
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

    public function getTotalReads(): int
    {
        return $this->totalReads;
    }

    public function setTotalReads(int $totalReads): self
    {
        $this->totalReads = $totalReads;
        return $this;
    }

    public function getUniqueUsers(): int
    {
        return $this->uniqueUsers;
    }

    public function setUniqueUsers(int $uniqueUsers): self
    {
        $this->uniqueUsers = $uniqueUsers;
        return $this;
    }

    public function getAnonymousReads(): int
    {
        return $this->anonymousReads;
    }

    public function setAnonymousReads(int $anonymousReads): self
    {
        $this->anonymousReads = $anonymousReads;
        return $this;
    }

    public function getRegisteredReads(): int
    {
        return $this->registeredReads;
    }

    public function setRegisteredReads(int $registeredReads): self
    {
        $this->registeredReads = $registeredReads;
        return $this;
    }

    public function getAvgDurationSeconds(): string
    {
        return $this->avgDurationSeconds;
    }

    public function setAvgDurationSeconds(string $avgDurationSeconds): self
    {
        $this->avgDurationSeconds = $avgDurationSeconds;
        return $this;
    }

    public function getCompletionRate(): string
    {
        return $this->completionRate;
    }

    public function setCompletionRate(string $completionRate): self
    {
        $this->completionRate = $completionRate;
        return $this;
    }

    public function getCreateAt(): \DateTimeInterface
    {
        return $this->createAt;
    }

    public function setCreateAt(\DateTimeInterface $createAt): self
    {
        $this->createAt = $createAt;
        return $this;
    }

    public function getUpdateAt(): \DateTimeInterface
    {
        return $this->updateAt;
    }

    public function setUpdateAt(\DateTimeInterface $updateAt): self
    {
        $this->updateAt = $updateAt;
        return $this;
    }

    /**
     * 增加阅读次数
     */
    public function incrementTotalReads(int $increment = 1): self
    {
        $this->totalReads += $increment;
        return $this;
    }

    /**
     * 增加独立用户数
     */
    public function incrementUniqueUsers(int $increment = 1): self
    {
        $this->uniqueUsers += $increment;
        return $this;
    }

    /**
     * 增加匿名用户阅读次数
     */
    public function incrementAnonymousReads(int $increment = 1): self
    {
        $this->anonymousReads += $increment;
        return $this;
    }

    /**
     * 增加注册用户阅读次数
     */
    public function incrementRegisteredReads(int $increment = 1): self
    {
        $this->registeredReads += $increment;
        return $this;
    }

    /**
     * 更新平均阅读时长
     */
    public function updateAvgDuration(float $totalDuration): self
    {
        if ($this->totalReads > 0) {
            $this->avgDurationSeconds = number_format($totalDuration / $this->totalReads, 2, '.', '');
        }
        return $this;
    }

    /**
     * 更新完成率
     */
    public function updateCompletionRate(int $completedReads): self
    {
        if ($this->totalReads > 0) {
            $this->completionRate = number_format(($completedReads / $this->totalReads) * 100, 2, '.', '');
        }
        return $this;
    }

    /**
     * 获取格式化的平均阅读时长
     */
    public function getFormattedAvgDuration(): string
    {
        $avgSeconds = (float) $this->avgDurationSeconds;

        if ($avgSeconds < 60) {
            return number_format($avgSeconds, 1) . '秒';
        } elseif ($avgSeconds < 3600) {
            $minutes = floor($avgSeconds / 60);
            $seconds = $avgSeconds % 60;
            return $minutes . '分' . number_format($seconds, 1) . '秒';
        } else {
            $hours = floor($avgSeconds / 3600);
            $minutes = floor(($avgSeconds % 3600) / 60);
            return $hours . '小时' . $minutes . '分';
        }
    }

    /**
     * 获取完成率描述
     */
    public function getCompletionRateDescription(): string
    {
        $rate = (float) $this->completionRate;

        if ($rate >= 90) {
            return '优秀';
        } elseif ($rate >= 75) {
            return '良好';
        } elseif ($rate >= 50) {
            return '一般';
        } elseif ($rate >= 25) {
            return '较差';
        } else {
            return '很差';
        }
    }

    /**
     * 获取匿名用户占比
     */
    public function getAnonymousReadRate(): string
    {
        if ($this->totalReads === 0) {
            return '0.00';
        }

        return number_format(($this->anonymousReads / $this->totalReads) * 100, 2, '.', '');
    }

    /**
     * 获取注册用户占比
     */
    public function getRegisteredReadRate(): string
    {
        if ($this->totalReads === 0) {
            return '0.00';
        }

        return number_format(($this->registeredReads / $this->totalReads) * 100, 2, '.', '');
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'articleId' => $this->articleId,
            'statDate' => $this->statDate->format('Y-m-d'),
            'totalReads' => $this->totalReads,
            'uniqueUsers' => $this->uniqueUsers,
            'anonymousReads' => $this->anonymousReads,
            'registeredReads' => $this->registeredReads,
            'avgDurationSeconds' => $this->avgDurationSeconds,
            'formattedAvgDuration' => $this->getFormattedAvgDuration(),
            'completionRate' => $this->completionRate,
            'completionRateDescription' => $this->getCompletionRateDescription(),
            'anonymousReadRate' => $this->getAnonymousReadRate(),
            'registeredReadRate' => $this->getRegisteredReadRate(),
            'createAt' => $this->createAt->format('Y-m-d H:i:s'),
            'updateAt' => $this->updateAt->format('Y-m-d H:i:s'),
        ];
    }
}
