<?php

namespace App\Repository;

use App\Entity\SysNewsArticleCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SysNewsArticleCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SysNewsArticleCategory::class);
    }

    /**
     * 根据分类编码查找分类
     */
    public function findByCode(string $code): ?SysNewsArticleCategory
    {
        return $this->createQueryBuilder('c')
            ->where('c.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 获取分类列表（支持多种查询条件）
     */
    public function findByPage(array $criteria = [], ?int $limit = null, ?int $offset = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.id', 'DESC');

        // 动态添加查询条件
        if (isset($criteria['code']) && $criteria['code'] !== '') {
            $qb->andWhere('c.code = :code')
                ->setParameter('code', $criteria['code']);
        }

        if (isset($criteria['name']) && $criteria['name'] !== '') {
            $qb->andWhere('c.name LIKE :name')
                ->setParameter('name', '%' . $criteria['name'] . '%');
        }

        if (isset($criteria['status'])) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $criteria['status']);
        }

        // 分页设置
        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }
        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 检查分类编码是否已存在
     */
    public function existsByCode(string $code, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.code = :code')
            ->setParameter('code', $code);

        if ($excludeId !== null) {
            $qb->andWhere('c.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * 获取所有分类（用于下拉选择等）
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 根据创建时间范围查找分类
     */
    public function findByIdRange(int $startId, int $endId): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.id BETWEEN :startId AND :endId')
            ->setParameter('startId', $startId)
            ->setParameter('endId', $endId)
            ->orderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 根据添加人查找分类
     */
    public function findByCreator(string $creator): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.creator = :creator')
            ->setParameter('creator', $creator)
            ->orderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
