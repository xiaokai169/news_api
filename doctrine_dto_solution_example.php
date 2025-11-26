<?php

echo "=== Doctrine + DTO 正确使用方式解决方案 ===\n\n";

echo "问题诊断:\n";
echo "❌ 当前 Repository 使用原生 addWhere 方式，没有利用 Doctrine 优势\n";
echo "❌ DTO 只作为数据容器，没有查询构建能力\n";
echo "❌ 重复的条件构建逻辑分散在 Repository 中\n\n";

echo "=== 解决方案 1: DTO 中构建 Doctrine Criteria ===\n";

// 示例：改进的 NewsFilterDto
$improvedDtoExample = '
/**
 * 改进的 NewsFilterDto - 包含 buildCriteria 方法
 */
class NewsFilterDto extends AbstractFilterDto
{
    // ... 现有属性 ...

    /**
     * 构建 Doctrine Criteria 对象
     */
    public function buildCriteria(): Criteria
    {
        $criteria = Criteria::create();

        // 基础条件
        if ($this->merchantId !== null) {
            $criteria->andWhere(Criteria::expr()->eq(\'merchantId\', $this->merchantId));
        }

        if ($this->userId !== null) {
            $criteria->andWhere(Criteria::expr()->eq(\'userId\', $this->userId));
        }

        if ($this->newsStatus !== null) {
            $criteria->andWhere(Criteria::expr()->eq(\'status\', $this->newsStatus));
        }

        if ($this->isRecommend !== null) {
            $criteria->andWhere(Criteria::expr()->eq(\'isRecommend\', $this->isRecommend));
        }

        // 模糊搜索
        if ($this->name !== null) {
            $criteria->andWhere(Criteria::expr()->contains(\'name\', $this->name));
        }

        if ($this->categoryCode !== null) {
            // 关联查询条件
            $criteria->andWhere(Criteria::expr()->eq(\'category.code\', $this->categoryCode));
        }

        // 排序
        if ($this->sortBy) {
            $order = $this->sortDirection === \'desc\' ? Criteria::DESC : Criteria::ASC;
            $criteria->orderBy([$this->sortBy => $order]);
        }

        return $criteria;
    }

    /**
     * 构建 QueryBuilder 条件
     */
    public function buildQueryBuilder(QueryBuilder $qb, string $alias = \'article\'): QueryBuilder
    {
        // 基础条件
        if ($this->merchantId !== null) {
            $qb->andWhere("{$alias}.merchantId = :merchantId")
               ->setParameter(\'merchantId\', $this->merchantId);
        }

        if ($this->userId !== null) {
            $qb->andWhere("{$alias}.userId = :userId")
               ->setParameter(\'userId\', $this->userId);
        }

        if ($this->newsStatus !== null) {
            $qb->andWhere("{$alias}.status = :status")
               ->setParameter(\'status\', $this->newsStatus);
        }

        if ($this->isRecommend !== null) {
            $qb->andWhere("{$alias}.isRecommend = :isRecommend")
               ->setParameter(\'isRecommend\', $this->isRecommend);
        }

        // 模糊搜索
        if ($this->name !== null) {
            $qb->andWhere("{$alias}.name LIKE :name")
               ->setParameter(\'name\', \'%\' . $this->name . \'%\');
        }

        // 分类关联
        if ($this->categoryCode !== null) {
            $qb->leftJoin("{$alias}.category", \'category\')
               ->andWhere("category.code = :categoryCode")
               ->setParameter(\'categoryCode\', $this->categoryCode);
        }

        // 排序
        if ($this->sortBy) {
            $order = $this->sortDirection === \'desc\' ? \'DESC\' : \'ASC\';
            $qb->orderBy("{$alias}.{$this->sortBy}", $order);
        }

        return $qb;
    }
}
';

echo $improvedDtoExample;
echo "\n=== 解决方案 2: 改进的 Repository ===\n";

$improvedRepositoryExample = '
/**
 * 改进的 SysNewsArticleRepository
 */
class SysNewsArticleRepository extends ServiceEntityRepository
{
    /**
     * 使用 DTO 进行查询 - 推荐方式
     */
    public function findByFilterDto(NewsFilterDto $filterDto): array
    {
        $qb = $this->createQueryBuilder(\'article\');

        // 让 DTO 构建 QueryBuilder 条件
        $qb = $filterDto->buildQueryBuilder($qb);

        // 分页
        if ($filterDto->getLimit() !== null) {
            $qb->setMaxResults($filterDto->getLimit());
            $qb->setFirstResult(($filterDto->getPage() - 1) * $filterDto->getLimit());
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 使用 Doctrine Criteria API - 更简洁
     */
    public function findByCriteria(NewsFilterDto $filterDto): array
    {
        // 使用 Collection 的 matching 方法
        $criteria = $filterDto->buildCriteria();

        // 如果需要分页
        if ($filterDto->getLimit() !== null) {
            $criteria->setMaxResults($filterDto->getLimit());
            $criteria->setFirstResult(($filterDto->getPage() - 1) * $filterDto->getLimit());
        }

        return $this->matching($criteria)->toArray();
    }

    /**
     * 统计数量 - 使用 Criteria
     */
    public function countByFilterDto(NewsFilterDto $filterDto): int
    {
        $criteria = $filterDto->buildCriteria();
        return count($this->matching($criteria));
    }
}
';

echo $improvedRepositoryExample;
echo "\n=== 解决方案 3: 控制器中的使用 ===\n";

$controllerExample = '
/**
 * 改进的控制器使用方式
 */
public function list(Request $request): JsonResponse
{
    try {
        // 创建并填充 DTO
        $filterDto = new NewsFilterDto();
        $filterDto->populateFromData($request->query->all());

        // 验证 DTO
        $errors = $this->validator->validate($filterDto);
        if (count($errors) > 0) {
            return $this->apiResponse->validationError($errors);
        }

        // 直接传递 DTO 给 Repository - 清晰简洁
        $articles = $this->sysNewsArticleRepository->findByFilterDto($filterDto);
        $total = $this->sysNewsArticleRepository->countByFilterDto($filterDto);

        return $this->apiResponse->paginated(
            items: $articles,
            total: $total,
            page: $filterDto->getPage(),
            limit: $filterDto->getLimit(),
            pages: ceil($total / $filterDto->getLimit()),
            request: $request
        );

    } catch (\Exception $e) {
        return $this->apiResponse->error(\'查询失败: \' . $e->getMessage());
    }
}
';

echo $controllerExample;
echo "\n=== 解决方案 4: 高级查询构建器 ===\n";

$advancedQueryBuilderExample = '
/**
 * 高级查询构建器 - 支持复杂条件
 */
class QueryBuilderHelper
{
    public static function buildComplexQuery(
        QueryBuilder $qb,
        NewsFilterDto $filterDto,
        string $alias = \'article\'
    ): QueryBuilder {

        // 基础条件
        $conditions = [];
        $parameters = [];

        if ($filterDto->merchantId !== null) {
            $conditions[] = "{$alias}.merchantId = :merchantId";
            $parameters[\'merchantId\'] = $filterDto->merchantId;
        }

        if ($filterDto->userId !== null) {
            $conditions[] = "{$alias}.userId = :userId";
            $parameters[\'userId\'] = $filterDto->userId;
        }

        // 时间范围查询
        if ($filterDto->releaseTimeFrom !== null) {
            $conditions[] = "{$alias}.releaseTime >= :releaseTimeFrom";
            $parameters[\'releaseTimeFrom\'] = $filterDto->getReleaseTimeFromDateTime();
        }

        if ($filterDto->releaseTimeTo !== null) {
            $conditions[] = "{$alias}.releaseTime <= :releaseTimeTo";
            $parameters[\'releaseTimeTo\'] = $filterDto->getReleaseTimeToDateTime();
        }

        // 复杂搜索条件
        if (!empty($filterDto->tags)) {
            $tagConditions = [];
            foreach ($filterDto->tags as $index => $tag) {
                $tagConditions[] = "JSON_CONTAINS({$alias}.tags, :tag{$index})";
                $parameters["tag{$index}"] = json_encode($tag);
            }
            $conditions[] = \'(\' . implode(\' OR \', $tagConditions) . \')\';
        }

        // 应用所有条件
        if (!empty($conditions)) {
            $qb->andWhere(implode(\' AND \', $conditions));
        }

        // 设置参数
        foreach ($parameters as $key => $value) {
            $qb->setParameter($key, $value);
        }

        return $qb;
    }
}
';

echo $advancedQueryBuilderExample;
echo "\n=== 总结 ===\n";
echo "✅ DTO 包含查询构建逻辑，职责清晰\n";
echo "✅ Repository 简化，只负责执行查询\n";
echo "✅ 使用 Doctrine Criteria API，代码更简洁\n";
echo "✅ 支持复杂查询条件和关联查询\n";
echo "✅ 查询逻辑复用，避免重复代码\n";
echo "✅ 类型安全，更好的 IDE 支持\n\n";

echo "=== 迁移建议 ===\n";
echo "1. 逐步在 DTO 中添加 buildCriteria() 方法\n";
echo "2. 在 Repository 中添加接受 DTO 的方法\n";
echo "3. 更新控制器使用新的 Repository 方法\n";
echo "4. 逐步移除旧的数组参数方法\n";
echo "5. 添加单元测试验证查询逻辑\n\n";

echo "=== 调试完成 ===\n";
