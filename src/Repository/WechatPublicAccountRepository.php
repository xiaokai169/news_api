<?php

namespace App\Repository;

use App\Entity\WechatPublicAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WechatPublicAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WechatPublicAccount::class);
    }

    public function findOrCreate(string $accountId): WechatPublicAccount
    {
        $account = $this->find($accountId);

        if (!$account) {
            $account = new WechatPublicAccount();
            $account->setId($accountId);
            $this->getEntityManager()->persist($account);
            $this->getEntityManager()->flush();
        }

        return $account;
    }

    /**
     * 分页查询（支持按 name 关键字模糊搜索）
     */
    public function findPaginated(?string $keyword, ?int $limit, ?int $offset): array
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC');

        if ($keyword !== null && $keyword !== '') {
            $qb->andWhere('a.name LIKE :kw')
               ->setParameter('kw', '%'.$keyword.'%');
        }

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }
        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByKeyword(?string $keyword): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)');

        if ($keyword !== null && $keyword !== '') {
            $qb->andWhere('a.name LIKE :kw')
               ->setParameter('kw', '%'.$keyword.'%');
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }
}
