<?php

namespace App\Repository;

use App\Entity\SysNewsArticle;
use App\Entity\User;
use App\DTO\Filter\NewsFilterDto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SysNewsArticle>
 */
class SysNewsArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SysNewsArticle::class);
    }

    /**
     * 根据查询条件获取文章列表（支持分页和排序）
     */
    public function findByCriteria(array $criteria = [], ?int $limit = null, ?int $offset = null, ?string $sortBy = 'createTime', ?string $sortOrder = 'desc'): array
    {

        $qb = $this->createQueryBuilder('article')
            ->leftJoin('article.category', 'category')
            ->addSelect('category');

        // 基础条件：按ID、商户ID、用户ID精确查询
        if (!empty($criteria['id'])) {
            $qb->andWhere('article.id = :id')
                ->setParameter('id', $criteria['id']);
        }

        if (!empty($criteria['merchantId'])) {
            $qb->andWhere('article.merchantId = :merchantId')
                ->setParameter('merchantId', $criteria['merchantId']);
        }

        if (!empty($criteria['userId'])) {
            $qb->andWhere('article.userId = :userId')
                ->setParameter('userId', $criteria['userId']);
        }

        // 状态过滤：支持按状态（激活/非激活）筛选
        if (isset($criteria['status'])) {
            $qb->andWhere('article.status = :status')
                ->setParameter('status', $criteria['status']);
        }

        // 推荐过滤：支持按推荐标识筛选
        if (isset($criteria['isRecommend'])) {
            $qb->andWhere('article.isRecommend = :isRecommend')
                ->setParameter('isRecommend', $criteria['isRecommend']);
        }

        // 时间范围：支持按创建时间、更新时间、发布时间范围查询
        if (!empty($criteria['createTimeFrom'])) {
            $qb->andWhere('article.createTime >= :createTimeFrom')
                ->setParameter('createTimeFrom', $criteria['createTimeFrom']);
        }

        if (!empty($criteria['createTimeTo'])) {
            $qb->andWhere('article.createTime <= :createTimeTo')
                ->setParameter('createTimeTo', $criteria['createTimeTo']);
        }

        if (!empty($criteria['updateTimeFrom'])) {
            $qb->andWhere('article.updateTime >= :updateTimeFrom')
                ->setParameter('updateTimeFrom', $criteria['updateTimeFrom']);
        }

        if (!empty($criteria['updateTimeTo'])) {
            $qb->andWhere('article.updateTime <= :updateTimeTo')
                ->setParameter('updateTimeTo', $criteria['updateTimeTo']);
        }

        if (!empty($criteria['releaseTimeFrom'])) {
            $qb->andWhere('article.releaseTime >= :releaseTimeFrom')
                ->setParameter('releaseTimeFrom', $criteria['releaseTimeFrom']);
        }

        if (!empty($criteria['releaseTimeTo'])) {
            $qb->andWhere('article.releaseTime <= :releaseTimeTo')
                ->setParameter('releaseTimeTo', $criteria['releaseTimeTo']);
        }

        // 分类筛选：支持按文章分类查询（通过分类编码）
        if (!empty($criteria['categoryCode'])) {
            $qb->andWhere('category.code = :categoryCode')
                ->setParameter('categoryCode', $criteria['categoryCode']);
        }

        // 文章名称搜索：支持按文章名称进行模糊搜索
        if (!empty($criteria['name'])) {
            if (is_array($criteria['name']) && isset($criteria['name']['like'])) {
                $qb->andWhere('article.name LIKE :name')
                    ->setParameter('name', $criteria['name']['like']);
            } else {
                $qb->andWhere('article.name LIKE :name')
                    ->setParameter('name', '%' . $criteria['name'] . '%');
            }
        }

        // 发布状态：支持按预约发布状态查询（已发布/待发布）
        if (!empty($criteria['publishStatus'])) {
            if ($criteria['publishStatus'] === 'published') {
                $qb->andWhere('article.status = :publishedStatus')
                    ->setParameter('publishedStatus', SysNewsArticle::STATUS_ACTIVE);
            } elseif ($criteria['publishStatus'] === 'scheduled') {
                $qb->andWhere('article.status = :scheduledStatus')
                    ->andWhere('article.releaseTime IS NOT NULL')
                    ->setParameter('scheduledStatus', SysNewsArticle::STATUS_INACTIVE);
            }
        }

        // 排除已删除的文章
        $qb->andWhere('article.status != :deletedStatus')
            ->setParameter('deletedStatus', SysNewsArticle::STATUS_DELETED);

        // 排序设置
        $validSortFields = ['id', 'createTime', 'updateTime', 'releaseTime', 'name'];
        $validSortOrders = ['asc', 'desc'];

        $sortBy = in_array($sortBy, $validSortFields) ? $sortBy : 'createTime';
        $sortOrder = in_array(strtolower($sortOrder), $validSortOrders) ? strtolower($sortOrder) : 'desc';

        $qb->orderBy('article.' . $sortBy, $sortOrder);

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
     * 根据查询条件统计文章数量
     */
    public function countByCriteria(array $criteria = []): int
    {

        $qb = $this->createQueryBuilder('article')
            ->select('COUNT(article.id)')
            ->leftJoin('article.category', 'category');

        // 应用相同的查询条件
        if (!empty($criteria['id'])) {
            $qb->andWhere('article.id = :id')
                ->setParameter('id', $criteria['id']);
        }

        if (!empty($criteria['merchantId'])) {
            $qb->andWhere('article.merchantId = :merchantId')
                ->setParameter('merchantId', $criteria['merchantId']);
        }

        if (!empty($criteria['userId'])) {
            $qb->andWhere('article.userId = :userId')
                ->setParameter('userId', $criteria['userId']);
        }

        if (isset($criteria['status'])) {
            $qb->andWhere('article.status = :status')
                ->setParameter('status', $criteria['status']);
        }

        if (isset($criteria['isRecommend'])) {
            $qb->andWhere('article.isRecommend = :isRecommend')
                ->setParameter('isRecommend', $criteria['isRecommend']);
        }

        if (!empty($criteria['categoryCode'])) {
            $qb->andWhere('category.code = :categoryCode')
                ->setParameter('categoryCode', $criteria['categoryCode']);
        }

        if (!empty($criteria['name'])) {
            if (is_array($criteria['name']) && isset($criteria['name']['like'])) {
                $qb->andWhere('article.name LIKE :name')
                    ->setParameter('name', $criteria['name']['like']);
            } else {
                $qb->andWhere('article.name LIKE :name')
                    ->setParameter('name', '%' . $criteria['name'] . '%');
            }
        }

        if (!empty($criteria['publishStatus'])) {
            if ($criteria['publishStatus'] === 'published') {
                $qb->andWhere('article.status = :publishedStatus')
                    ->setParameter('publishedStatus', SysNewsArticle::STATUS_ACTIVE);
            } elseif ($criteria['publishStatus'] === 'scheduled') {
                $qb->andWhere('article.status = :scheduledStatus')
                    ->andWhere('article.releaseTime IS NOT NULL')
                    ->setParameter('scheduledStatus', SysNewsArticle::STATUS_INACTIVE);
            }
        }

        // 排除已删除的文章
        $qb->andWhere('article.status != :deletedStatus')
            ->setParameter('deletedStatus', SysNewsArticle::STATUS_DELETED);


        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * 获取需要定时发布的文章列表
     */
    public function findArticlesToPublish(): array
    {
        $currentTime = new \DateTime();

        return $this->createQueryBuilder('article')
            ->where('article.status = :inactiveStatus')
            ->andWhere('article.releaseTime <= :currentTime')
            ->andWhere('article.releaseTime IS NOT NULL')
            ->setParameter('inactiveStatus', SysNewsArticle::STATUS_INACTIVE)
            ->setParameter('currentTime', $currentTime)
            ->getQuery()
            ->getResult();
    }

    /**
     * 批量更新文章状态
     */
    public function batchUpdateStatus(array $ids, int $status): int
    {
        $currentTime = new \DateTime();

        $qb = $this->createQueryBuilder('article')
            ->update()
            ->set('article.status', ':status')
            ->set('article.updateTime', ':updateTime')
            ->where('article.id IN (:ids)')
            ->setParameter('status', $status)
            ->setParameter('updateTime', $currentTime)
            ->setParameter('ids', $ids);

        return $qb->getQuery()->execute();
    }

    /**
     * 根据分类ID获取文章列表
     */
    public function findByCategoryId(int $categoryId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->createQueryBuilder('article')
            ->leftJoin('article.category', 'category')
            ->addSelect('category')
            ->where('category.id = :categoryId')
            ->andWhere('article.status != :deletedStatus')
            ->setParameter('categoryId', $categoryId)
            ->setParameter('deletedStatus', SysNewsArticle::STATUS_DELETED)
            ->orderBy('article.createTime', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * 统计某个分类下的文章数量
     */
    public function countByCategoryId(int $categoryId): int
    {
        return (int) $this->createQueryBuilder('article')
            ->select('COUNT(article.id)')
            ->leftJoin('article.category', 'category')
            ->where('category.id = :categoryId')
            ->andWhere('article.status != :deletedStatus')
            ->setParameter('categoryId', $categoryId)
            ->setParameter('deletedStatus', SysNewsArticle::STATUS_DELETED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * 获取推荐文章列表
     */
    public function findRecommendedArticles(?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('article')
            ->leftJoin('article.category', 'category')
            ->addSelect('category')
            ->where('article.isRecommend = :isRecommend')
            ->andWhere('article.status = :activeStatus')
            ->setParameter('isRecommend', true)
            ->setParameter('activeStatus', SysNewsArticle::STATUS_ACTIVE)
            ->orderBy('article.createTime', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 获取待发布文章列表
     */
    public function findScheduledArticles(?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('article')
            ->leftJoin('article.category', 'category')
            ->addSelect('category')
            ->where('article.status = :inactiveStatus')
            ->andWhere('article.releaseTime IS NOT NULL')
            ->setParameter('inactiveStatus', SysNewsArticle::STATUS_INACTIVE)
            ->orderBy('article.releaseTime', 'ASC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 检查分类是否被文章使用
     */
    public function isCategoryUsed(int $categoryId): bool
    {
        return (int) $this->createQueryBuilder('article')
            ->select('COUNT(article.id)')
            ->where('article.category = :categoryId')
            ->andWhere('article.status != :deletedStatus')
            ->setParameter('categoryId', $categoryId)
            ->setParameter('deletedStatus', SysNewsArticle::STATUS_DELETED)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * 获取商户的文章统计信息
     */
    public function getMerchantStats(int $merchantId): array
    {
        $qb = $this->createQueryBuilder('article')
            ->select([
                'COUNT(article.id) as total',
                'SUM(CASE WHEN article.status = :activeStatus THEN 1 ELSE 0 END) as published',
                'SUM(CASE WHEN article.status = :inactiveStatus THEN 1 ELSE 0 END) as scheduled',
                'SUM(CASE WHEN article.isRecommend = true THEN 1 ELSE 0 END) as recommended'
            ])
            ->where('article.merchantId = :merchantId')
            ->andWhere('article.status != :deletedStatus')
            ->setParameter('merchantId', $merchantId)
            ->setParameter('activeStatus', SysNewsArticle::STATUS_ACTIVE)
            ->setParameter('inactiveStatus', SysNewsArticle::STATUS_INACTIVE)
            ->setParameter('deletedStatus', SysNewsArticle::STATUS_DELETED);

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * 根据查询条件获取文章列表（包含用户信息）
     */
    public function findByCriteriaWithUser(array $criteria = [], ?int $limit = null, ?int $offset = null, ?string $sortBy = 'createTime', ?string $sortOrder = 'desc'): array
    {
        $qb = $this->createQueryBuilder('article')
            ->leftJoin('article.category', 'category')
            ->leftJoin(User::class, 'user', 'WITH', 'user.id = article.userId')
            ->addSelect('category')
            ->addSelect('user');

        // 基础条件：按ID、商户ID、用户ID精确查询
        if (!empty($criteria['id'])) {
            $qb->andWhere('article.id = :id')
                ->setParameter('id', $criteria['id']);
        }

        if (!empty($criteria['merchantId'])) {
            $qb->andWhere('article.merchantId = :merchantId')
                ->setParameter('merchantId', $criteria['merchantId']);
        }

        if (!empty($criteria['userId'])) {
            $qb->andWhere('article.userId = :userId')
                ->setParameter('userId', $criteria['userId']);
        }

        // 状态过滤：支持按状态（激活/非激活）筛选
        if (isset($criteria['status'])) {
            $qb->andWhere('article.status = :status')
                ->setParameter('status', $criteria['status']);
        }

        // 推荐过滤：支持按推荐标识筛选
        if (isset($criteria['isRecommend'])) {
            $qb->andWhere('article.isRecommend = :isRecommend')
                ->setParameter('isRecommend', $criteria['isRecommend']);
        }

        // 时间范围：支持按创建时间、更新时间、发布时间范围查询
        if (!empty($criteria['createTimeFrom'])) {
            $qb->andWhere('article.createTime >= :createTimeFrom')
                ->setParameter('createTimeFrom', $criteria['createTimeFrom']);
        }

        if (!empty($criteria['createTimeTo'])) {
            $qb->andWhere('article.createTime <= :createTimeTo')
                ->setParameter('createTimeTo', $criteria['createTimeTo']);
        }

        if (!empty($criteria['updateTimeFrom'])) {
            $qb->andWhere('article.updateTime >= :updateTimeFrom')
                ->setParameter('updateTimeFrom', $criteria['updateTimeFrom']);
        }

        if (!empty($criteria['updateTimeTo'])) {
            $qb->andWhere('article.updateTime <= :updateTimeTo')
                ->setParameter('updateTimeTo', $criteria['updateTimeTo']);
        }

        if (!empty($criteria['releaseTimeFrom'])) {
            $qb->andWhere('article.releaseTime >= :releaseTimeFrom')
                ->setParameter('releaseTimeFrom', $criteria['releaseTimeFrom']);
        }

        if (!empty($criteria['releaseTimeTo'])) {
            $qb->andWhere('article.releaseTime <= :releaseTimeTo')
                ->setParameter('releaseTimeTo', $criteria['releaseTimeTo']);
        }

        // 分类筛选：支持按文章分类查询（通过分类编码）
        if (!empty($criteria['categoryCode'])) {
            $qb->andWhere('category.code = :categoryCode')
                ->setParameter('categoryCode', $criteria['categoryCode']);
        }

        // 文章名称搜索：支持按文章名称进行模糊搜索
        if (!empty($criteria['name'])) {
            $qb->andWhere('article.name LIKE :name')
                ->setParameter('name', '%' . $criteria['name'] . '%');
        }

        // 用户名搜索：支持按用户名或昵称进行模糊搜索
        if (!empty($criteria['userName'])) {
            $qb->andWhere('(user.username LIKE :userName OR user.nickname LIKE :userName)')
                ->setParameter('userName', '%' . $criteria['userName'] . '%');
        }

        // 发布状态：支持按预约发布状态查询（已发布/待发布）
        if (!empty($criteria['publishStatus'])) {
            if ($criteria['publishStatus'] === 'published') {
                $qb->andWhere('article.status = :publishedStatus')
                    ->setParameter('publishedStatus', SysNewsArticle::STATUS_ACTIVE);
            } elseif ($criteria['publishStatus'] === 'scheduled') {
                $qb->andWhere('article.status = :scheduledStatus')
                    ->andWhere('article.releaseTime IS NOT NULL')
                    ->setParameter('scheduledStatus', SysNewsArticle::STATUS_INACTIVE);
            }
        }

        // 排除已删除的文章
        $qb->andWhere('article.status != :deletedStatus')
            ->setParameter('deletedStatus', SysNewsArticle::STATUS_DELETED);

        // 排序设置
        $validSortFields = ['id', 'createTime', 'updateTime', 'releaseTime', 'name'];
        $validSortOrders = ['asc', 'desc'];

        $sortBy = in_array($sortBy, $validSortFields) ? $sortBy : 'createTime';
        $sortOrder = in_array(strtolower($sortOrder), $validSortOrders) ? strtolower($sortOrder) : 'desc';

        $qb->orderBy('article.' . $sortBy, $sortOrder);

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
     * 获取单个文章详情（包含用户信息）
     */
    public function findWithUser(int $id): ?SysNewsArticle
    {
        return $this->createQueryBuilder('article')
            ->leftJoin('article.category', 'category')
            ->leftJoin(User::class, 'user', 'WITH', 'user.id = article.userId')
            ->addSelect('category')
            ->addSelect('user')
            ->where('article.id = :id')
            ->andWhere('article.status != :deletedStatus')
            ->setParameter('id', $id)
            ->setParameter('deletedStatus', SysNewsArticle::STATUS_DELETED)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 使用 DTO 进行查询 - 推荐方式
     * 使用 DTO 的 buildQueryBuilder 方法构建查询条件
     */
    public function findByFilterDto(NewsFilterDto $filterDto): array
    {
        $qb = $this->createQueryBuilder('article');

        // 让 DTO 构建 QueryBuilder 条件
        $qb = $filterDto->buildQueryBuilder($qb);

        // 排除已删除的文章（这个条件在 DTO 中不包含，因为这是业务规则）
        $qb->andWhere('article.status != :deletedStatus')
           ->setParameter('deletedStatus', SysNewsArticle::STATUS_DELETED);

        return $qb->getQuery()->getResult();
    }

    /**
     * 使用 DTO 进行带用户信息的查询
     */
    public function findByFilterDtoWithUser(NewsFilterDto $filterDto): array
    {
        $qb = $this->createQueryBuilder('article')
            ->leftJoin('article.category', 'category')
            ->leftJoin(User::class, 'user', 'WITH', 'user.id = article.userId')
            ->addSelect('category')
            ->addSelect('user');

        // 让 DTO 构建 QueryBuilder 条件
        $qb = $filterDto->buildQueryBuilder($qb);

        // 排除已删除的文章
        $qb->andWhere('article.status != :deletedStatus')
           ->setParameter('deletedStatus', SysNewsArticle::STATUS_DELETED);

        return $qb->getQuery()->getResult();
    }

    /**
     * 使用 Doctrine Criteria API 进行查询
     * 适用于简单的查询，不支持复杂的关联查询
     */
    public function findByDtoCriteria(NewsFilterDto $filterDto): array
    {
        // 对于复杂查询，我们使用 QueryBuilder 方式
        $qb = $this->createQueryBuilder('article');
        $qb = $filterDto->buildQueryBuilder($qb);

        // 排除已删除的文章
        $qb->andWhere('article.status != :deletedStatus')
           ->setParameter('deletedStatus', SysNewsArticle::STATUS_DELETED);

        return $qb->getQuery()->getResult();
    }

    /**
     * 使用 DTO 统计数量
     */
    public function countByFilterDto(NewsFilterDto $filterDto): int
    {
        $qb = $this->createQueryBuilder('article')
            ->select('COUNT(article.id)');

        // 使用 QueryBuilder 方式构建条件，避免 Criteria API 的复杂性
        if ($filterDto->merchantId !== null) {
            $qb->andWhere('article.merchantId = :merchantId')
               ->setParameter('merchantId', $filterDto->merchantId);
        }

        if ($filterDto->userId !== null) {
            $qb->andWhere('article.userId = :userId')
               ->setParameter('userId', $filterDto->userId);
        }

        if ($filterDto->newsStatus !== null) {
            $qb->andWhere('article.status = :status')
               ->setParameter('status', $filterDto->newsStatus);
        }

        if ($filterDto->isRecommend !== null) {
            $qb->andWhere('article.isRecommend = :isRecommend')
               ->setParameter('isRecommend', $filterDto->isRecommend);
        }

        if ($filterDto->name !== null) {
            $qb->andWhere('article.name LIKE :name')
               ->setParameter('name', '%' . $filterDto->name . '%');
        }

        if ($filterDto->categoryCode !== null) {
            $qb->leftJoin('article.category', 'category')
               ->andWhere('category.code = :categoryCode')
               ->setParameter('categoryCode', $filterDto->categoryCode);
        }

        // 排除已删除的文章
        $qb->andWhere('article.status != :deletedStatus')
           ->setParameter('deletedStatus', SysNewsArticle::STATUS_DELETED);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * 使用 DTO 进行分页查询
     * 返回包含分页信息的数组
     */
    public function findByFilterDtoWithPagination(NewsFilterDto $filterDto): array
    {
        $qb = $this->createQueryBuilder('article');

        // 构建查询条件
        $qb = $filterDto->buildQueryBuilder($qb);

        // 排除已删除的文章
        $qb->andWhere('article.status != :deletedStatus')
           ->setParameter('deletedStatus', SysNewsArticle::STATUS_DELETED);

        // 获取总数（用于分页）
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(article.id)')
                            ->getQuery()
                            ->getSingleScalarResult();

        // 获取当前页数据
        $articles = $qb->getQuery()->getResult();

        return [
            'items' => $articles,
            'total' => $total,
            'page' => $filterDto->getPage(),
            'limit' => $filterDto->getLimit(),
            'pages' => ceil($total / $filterDto->getLimit())
        ];
    }

    /**
     * 获取公共访问的新闻文章列表（只返回已发布且未删除的文章）
     */
    public function findActivePublicArticles(?int $limit = null, ?int $offset = null): array
    {
        $qb = $this->createQueryBuilder('article')
            ->leftJoin('article.category', 'category')
            ->addSelect('category')
            ->where('article.status = :activeStatus')
            ->andWhere('article.status != :deletedStatus')
            ->setParameter('activeStatus', SysNewsArticle::STATUS_ACTIVE)
            ->setParameter('deletedStatus', SysNewsArticle::STATUS_DELETED)
            ->orderBy('article.createTime', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }
        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 统计公共访问的新闻文章数量
     */
    public function countActivePublicArticles(): int
    {
        return (int) $this->createQueryBuilder('article')
            ->select('COUNT(article.id)')
            ->where('article.status = :activeStatus')
            ->andWhere('article.status != :deletedStatus')
            ->setParameter('activeStatus', SysNewsArticle::STATUS_ACTIVE)
            ->setParameter('deletedStatus', SysNewsArticle::STATUS_DELETED)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
