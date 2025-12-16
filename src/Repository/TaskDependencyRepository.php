<?php

namespace App\Repository;

use App\Entity\AsyncTask;
use App\Entity\TaskDependency;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskDependency>
 */
class TaskDependencyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskDependency::class);
    }

    /**
     * 根据任务ID查找依赖关系
     */
    public function findByTaskId(string $taskId): array
    {
        return $this->createQueryBuilder('dep')
            ->where('dep.taskId = :taskId')
            ->setParameter('taskId', $taskId)
            ->orderBy('dep.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找依赖于指定任务的所有任务
     */
    public function findByDependsOnTaskId(string $dependsOnTaskId): array
    {
        return $this->createQueryBuilder('dep')
            ->where('dep.dependsOnTaskId = :dependsOnTaskId')
            ->setParameter('dependsOnTaskId', $dependsOnTaskId)
            ->orderBy('dep.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 根据任务ID和依赖类型查找依赖关系
     */
    public function findByTaskIdAndType(string $taskId, string $dependencyType): array
    {
        return $this->createQueryBuilder('dep')
            ->where('dep.taskId = :taskId')
            ->andWhere('dep.dependencyType = :dependencyType')
            ->setParameter('taskId', $taskId)
            ->setParameter('dependencyType', $dependencyType)
            ->orderBy('dep.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 检查是否存在特定的依赖关系
     */
    public function existsDependency(string $taskId, string $dependsOnTaskId, string $dependencyType): bool
    {
        $count = $this->createQueryBuilder('dep')
            ->select('COUNT(dep.id)')
            ->where('dep.taskId = :taskId')
            ->andWhere('dep.dependsOnTaskId = :dependsOnTaskId')
            ->andWhere('dep.dependencyType = :dependencyType')
            ->setParameter('taskId', $taskId)
            ->setParameter('dependsOnTaskId', $dependsOnTaskId)
            ->setParameter('dependencyType', $dependencyType)
            ->getQuery()
            ->getSingleScalarResult();

        return (int)$count > 0;
    }

    /**
     * 检查任务是否存在依赖关系
     */
    public function hasDependencies(string $taskId): bool
    {
        $count = $this->createQueryBuilder('dep')
            ->select('COUNT(dep.id)')
            ->where('dep.taskId = :taskId')
            ->setParameter('taskId', $taskId)
            ->getQuery()
            ->getSingleScalarResult();

        return (int)$count > 0;
    }

    /**
     * 检查任务是否被其他任务依赖
     */
    public function isDependedBy(string $taskId): bool
    {
        $count = $this->createQueryBuilder('dep')
            ->select('COUNT(dep.id)')
            ->where('dep.dependsOnTaskId = :taskId')
            ->setParameter('taskId', $taskId)
            ->getQuery()
            ->getSingleScalarResult();

        return (int)$count > 0;
    }

    /**
     * 获取任务的所有依赖任务ID
     */
    public function getDependencyTaskIds(string $taskId): array
    {
        $result = $this->createQueryBuilder('dep')
            ->select('dep.dependsOnTaskId')
            ->where('dep.taskId = :taskId')
            ->setParameter('taskId', $taskId)
            ->getQuery()
            ->getResult();

        return array_column($result, 'dependsOnTaskId');
    }

    /**
     * 获取依赖于指定任务的所有任务ID
     */
    public function getDependentTaskIds(string $dependsOnTaskId): array
    {
        $result = $this->createQueryBuilder('dep')
            ->select('dep.taskId')
            ->where('dep.dependsOnTaskId = :dependsOnTaskId')
            ->setParameter('dependsOnTaskId', $dependsOnTaskId)
            ->getQuery()
            ->getResult();

        return array_column($result, 'taskId');
    }

    /**
     * 检查是否存在循环依赖
     */
    public function hasCircularDependency(string $taskId, string $dependsOnTaskId): bool
    {
        // 简单检查：如果任务A依赖任务B，同时任务B也依赖任务A，则存在循环依赖
        return $this->existsDependency($dependsOnTaskId, $taskId, TaskDependency::DEPENDENCY_TYPE_FINISH) ||
               $this->existsDependency($dependsOnTaskId, $taskId, TaskDependency::DEPENDENCY_TYPE_SUCCESS);
    }

    /**
     * 获取任务的依赖链（递归查找所有依赖）
     */
    public function getDependencyChain(string $taskId, array $visited = []): array
    {
        if (in_array($taskId, $visited)) {
            return []; // 避免循环依赖
        }

        $visited[] = $taskId;
        $dependencies = $this->getDependencyTaskIds($taskId);
        $chain = [];

        foreach ($dependencies as $dependencyId) {
            $chain[] = $dependencyId;
            $chain = array_merge($chain, $this->getDependencyChain($dependencyId, $visited));
        }

        return array_unique($chain);
    }

    /**
     * 获取依赖于指定任务的任务链（递归查找所有依赖者）
     */
    public function getDependentChain(string $taskId, array $visited = []): array
    {
        if (in_array($taskId, $visited)) {
            return []; // 避免循环依赖
        }

        $visited[] = $taskId;
        $dependents = $this->getDependentTaskIds($taskId);
        $chain = [];

        foreach ($dependents as $dependentId) {
            $chain[] = $dependentId;
            $chain = array_merge($chain, $this->getDependentChain($dependentId, $visited));
        }

        return array_unique($chain);
    }

    /**
     * 根据任务状态获取满足依赖条件的任务
     */
    public function findSatisfiedDependencies(string $dependsOnTaskId, string $taskStatus): array
    {
        $qb = $this->createQueryBuilder('dep')
            ->where('dep.dependsOnTaskId = :dependsOnTaskId')
            ->setParameter('dependsOnTaskId', $dependsOnTaskId);

        // 根据不同的依赖类型检查条件
        $orConditions = $qb->expr()->orX();

        // 完成依赖
        $orConditions->add($qb->expr()->andX(
            $qb->expr()->eq('dep.dependencyType', ':finishType'),
            $qb->expr()->in(':taskStatus', ':finishStatuses')
        ));

        // 成功依赖
        $orConditions->add($qb->expr()->andX(
            $qb->expr()->eq('dep.dependencyType', ':successType'),
            $qb->expr()->eq(':taskStatus', ':successStatus')
        ));

        // 失败依赖
        $orConditions->add($qb->expr()->andX(
            $qb->expr()->eq('dep.dependencyType', ':failureType'),
            $qb->expr()->eq(':taskStatus', ':failureStatus')
        ));

        // 取消依赖
        $orConditions->add($qb->expr()->andX(
            $qb->expr()->eq('dep.dependencyType', ':cancelType'),
            $qb->expr()->eq(':taskStatus', ':cancelStatus')
        ));

        $qb->andWhere($orConditions)
           ->setParameter('finishType', TaskDependency::DEPENDENCY_TYPE_FINISH)
           ->setParameter('successType', TaskDependency::DEPENDENCY_TYPE_SUCCESS)
           ->setParameter('failureType', TaskDependency::DEPENDENCY_TYPE_FAILURE)
           ->setParameter('cancelType', TaskDependency::DEPENDENCY_TYPE_CANCEL)
           ->setParameter('taskStatus', $taskStatus)
           ->setParameter('finishStatuses', [
               AsyncTask::STATUS_COMPLETED,
               AsyncTask::STATUS_FAILED,
               AsyncTask::STATUS_CANCELLED
           ])
           ->setParameter('successStatus', AsyncTask::STATUS_COMPLETED)
           ->setParameter('failureStatus', AsyncTask::STATUS_FAILED)
           ->setParameter('cancelStatus', AsyncTask::STATUS_CANCELLED);

        return $qb->getQuery()->getResult();
    }

    /**
     * 批量删除任务的依赖关系
     */
    public function deleteByTaskId(string $taskId): int
    {
        return $this->createQueryBuilder('dep')
            ->delete()
            ->where('dep.taskId = :taskId')
            ->setParameter('taskId', $taskId)
            ->getQuery()
            ->execute();
    }

    /**
     * 批量删除依赖于指定任务的依赖关系
     */
    public function deleteByDependsOnTaskId(string $dependsOnTaskId): int
    {
        return $this->createQueryBuilder('dep')
            ->delete()
            ->where('dep.dependsOnTaskId = :dependsOnTaskId')
            ->setParameter('dependsOnTaskId', $dependsOnTaskId)
            ->getQuery()
            ->execute();
    }

    /**
     * 获取依赖统计信息
     */
    public function getDependencyStatistics(): array
    {
        $qb = $this->createQueryBuilder('dep')
            ->select('dep.dependencyType', 'COUNT(dep.id) as count')
            ->groupBy('dep.dependencyType')
            ->orderBy('count', 'DESC');

        $results = $qb->getQuery()->getResult();

        $statistics = [
            'total_dependencies' => 0,
            'by_type' => [],
        ];

        foreach ($results as $result) {
            $type = $result['dependencyType'];
            $count = (int)$result['count'];

            $statistics['by_type'][$type] = [
                'count' => $count,
                'description' => $this->getDependencyTypeDescription($type),
            ];

            $statistics['total_dependencies'] += $count;
        }

        return $statistics;
    }

    /**
     * 获取依赖类型描述
     */
    private function getDependencyTypeDescription(string $type): string
    {
        return match ($type) {
            TaskDependency::DEPENDENCY_TYPE_FINISH => '任务完成时',
            TaskDependency::DEPENDENCY_TYPE_SUCCESS => '任务成功时',
            TaskDependency::DEPENDENCY_TYPE_FAILURE => '任务失败时',
            TaskDependency::DEPENDENCY_TYPE_CANCEL => '任务取消时',
            default => '未知类型',
        };
    }

    /**
     * 创建依赖关系
     */
    public function createDependency(string $taskId, string $dependsOnTaskId, string $dependencyType): TaskDependency
    {
        // 检查是否已存在相同的依赖关系
        if ($this->existsDependency($taskId, $dependsOnTaskId, $dependencyType)) {
            throw new \InvalidArgumentException('Dependency already exists');
        }

        // 检查循环依赖
        if ($this->hasCircularDependency($taskId, $dependsOnTaskId)) {
            throw new \InvalidArgumentException('Circular dependency detected');
        }

        $dependency = match ($dependencyType) {
            TaskDependency::DEPENDENCY_TYPE_FINISH => TaskDependency::createFinishDependency($taskId, $dependsOnTaskId),
            TaskDependency::DEPENDENCY_TYPE_SUCCESS => TaskDependency::createSuccessDependency($taskId, $dependsOnTaskId),
            TaskDependency::DEPENDENCY_TYPE_FAILURE => TaskDependency::createFailureDependency($taskId, $dependsOnTaskId),
            TaskDependency::DEPENDENCY_TYPE_CANCEL => TaskDependency::createCancelDependency($taskId, $dependsOnTaskId),
            default => throw new \InvalidArgumentException("Invalid dependency type: {$dependencyType}"),
        };

        $this->save($dependency);
        return $dependency;
    }

    /**
     * 保存依赖关系
     */
    public function save(TaskDependency $dependency, bool $flush = true): void
    {
        $this->getEntityManager()->persist($dependency);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除依赖关系
     */
    public function remove(TaskDependency $dependency, bool $flush = true): void
    {
        $this->getEntityManager()->remove($dependency);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
