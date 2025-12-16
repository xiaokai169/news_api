<?php
// src/Repository/OfficialRepository.php

namespace App\Repository;

use App\Entity\Official;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OfficialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Official::class);
    }
    /**
     * 获取文章列表（支持多种查询条件）
     */
    public function findByPage(array $criteria = [], ?int $limit = null, ?int $offset = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->orderBy('o.createAt', 'DESC');

        if (isset($criteria['title']) && $criteria['title'] !== '') {
            $qb->andWhere('o.title LIKE :title')
                ->setParameter('title', '%' . $criteria['title'] . '%');
        }

        if (isset($criteria['status'])) {
            $qb->andWhere('o.status = :status')
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
    public function findActiveArticles(?int $limit = null, ?int $offset = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->orderBy('o.createAt', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }
        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 获取活跃文章总数
     */
    public function countActiveArticles(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByArticleId(string $articleId): ?Official
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.articleId = :articleId')
            ->setParameter('articleId', $articleId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 检查 articleId 是否存在（用于唯一性验证，包括已删除的记录）
     */
    public function existsByArticleId(string $articleId): bool
    {
        return $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.articleId = :articleId')
            ->setParameter('articleId', $articleId)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * 根据条件查询文章列表
     */
    public function findByCriteria(array $criteria = [], ?int $limit = null, ?int $offset = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->orderBy('o.createAt', 'DESC');

        // 标题关键词查询
        if (isset($criteria['title']) && $criteria['title'] !== '') {
            $qb->andWhere('o.title LIKE :title')
                ->setParameter('title', '%' . $criteria['title'] . '%');
        }

        // 分页设置
        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }
        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }

        // 🔍 调试：输出SQL查询
        $sql = $qb->getQuery()->getSQL();
        error_log('[DEBUG] findByCriteria SQL: ' . $sql);
        error_log('[DEBUG] findByCriteria Parameters: ' . json_encode($qb->getQuery()->getParameters()));

        return $qb->getQuery()->getResult();
    }

    /**
     * 根据条件统计文章数量
     */
    public function countByCriteria(array $criteria = []): int
    {
        $qb = $this->createQueryBuilder('o')
            ->select('COUNT(o.id)');

        // 标题关键词查询
        if (isset($criteria['title']) && $criteria['title'] !== '') {
            $qb->andWhere('o.title LIKE :title')
                ->setParameter('title', '%' . $criteria['title'] . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * 获取公共访问的公众号文章列表（只返回状态为1的文章）
     */
    public function findActivePublicArticles(?int $limit = null, ?int $offset = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.category', 'category')
            ->addSelect('category')
            ->where('o.status = :activeStatus')
            ->setParameter('activeStatus', 1)
            ->orderBy('o.createAt', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }
        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 统计公共访问的公众号文章数量
     */
    public function countActivePublicArticles(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.status = :activeStatus')
            ->setParameter('activeStatus', 1)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * 根据公众号ID统计文章总数
     */
    public function countByAccountId(string $accountId): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.wechatAccountId = :accountId')
            ->setParameter('accountId', $accountId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * 根据公众号ID统计活跃文章数
     */
    public function countActiveByAccountId(string $accountId): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.wechatAccountId = :accountId')
            ->andWhere('o.status = :activeStatus')
            ->setParameter('accountId', $accountId)
            ->setParameter('activeStatus', 1)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * 获取公众号最后同步时间
     */
    public function getLastSyncTime(string $accountId): ?\DateTime
    {
        $result = $this->createQueryBuilder('o')
            ->select('MAX(o.updatedAt)')
            ->where('o.wechatAccountId = :accountId')
            ->setParameter('accountId', $accountId)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? new \DateTime($result) : null;
    }

    /**
     * 获取公众号最近的文章
     */
    public function findRecentByAccountId(string $accountId, int $limit = 5): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.wechatAccountId = :accountId')
            ->setParameter('accountId', $accountId)
            ->orderBy('o.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
