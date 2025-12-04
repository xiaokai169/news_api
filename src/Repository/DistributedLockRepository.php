<?php

namespace App\Repository;

use App\Entity\DistributedLock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DistributedLock>
 */
class DistributedLockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DistributedLock::class);
    }

    /**
     * 查找有效的锁（未过期的）
     */
    public function findValidLock(string $lockKey): ?DistributedLock
    {
        return $this->createQueryBuilder('dl')
            ->where('dl.lockKey = :lockKey')
            ->andWhere('dl.expireTime > :now')
            ->setParameter('lockKey', $lockKey)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 删除过期的锁
     */
    public function deleteExpiredLocks(): int
    {
        return $this->createQueryBuilder('dl')
            ->delete()
            ->where('dl.expireTime < :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }

    /**
     * 根据锁键删除锁
     */
    public function deleteByLockKey(string $lockKey): int
    {
        return $this->createQueryBuilder('dl')
            ->delete()
            ->where('dl.lockKey = :lockKey')
            ->setParameter('lockKey', $lockKey)
            ->getQuery()
            ->execute();
    }

    /**
     * 获取所有活跃的锁
     */
    public function findActiveLocks(): array
    {
        return $this->createQueryBuilder('dl')
            ->where('dl.expireTime > :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }
}
