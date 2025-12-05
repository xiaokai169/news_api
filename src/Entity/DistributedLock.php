<?php

namespace App\Entity;

use App\Repository\DistributedLockRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DistributedLockRepository::class)]
#[ORM\Table(name: 'distributed_locks')]
#[ORM\Index(name: 'idx_expire_time', columns: ['expire_time'])]
class DistributedLock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true, name: 'lockKey')]
    private ?string $lockKey = null;

    #[ORM\Column(type: 'string', length: 255, name: 'lockId')]
    private ?string $lockId = null;

    #[ORM\Column(type: 'datetime', name: 'expire_time')]
    private ?\DateTimeInterface $expireTime = null;

    #[ORM\Column(type: 'datetime', name: 'created_at')]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLockKey(): ?string
    {
        return $this->lockKey;
    }

    public function setLockKey(string $lockKey): self
    {
        $this->lockKey = $lockKey;

        return $this;
    }

    public function getLockId(): ?string
    {
        return $this->lockId;
    }

    public function setLockId(string $lockId): self
    {
        $this->lockId = $lockId;

        return $this;
    }

    public function getExpireTime(): ?\DateTimeInterface
    {
        return $this->expireTime;
    }

    public function setExpireTime(\DateTimeInterface $expireTime): self
    {
        $this->expireTime = $expireTime;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * 检查锁是否已过期
     */
    public function isExpired(): bool
    {
        return $this->expireTime < new \DateTime();
    }

    /**
     * 检查锁是否有效（未过期）
     */
    public function isValid(): bool
    {
        return !$this->isExpired();
    }
}
