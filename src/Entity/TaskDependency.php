<?php

namespace App\Entity;

use App\Repository\TaskDependencyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TaskDependencyRepository::class)]
#[ORM\Table(name: 'task_dependencies')]
#[ORM\UniqueConstraint(name: 'uk_task_dependency', columns: ['task_id', 'depends_on_task_id'])]
#[ORM\Index(name: 'idx_task_id', columns: ['task_id'])]
#[ORM\Index(name: 'idx_depends_on', columns: ['depends_on_task_id'])]
#[ORM\HasLifecycleCallbacks]
class TaskDependency
{
    public const DEPENDENCY_TYPE_FINISH = 'finish';
    public const DEPENDENCY_TYPE_SUCCESS = 'success';
    public const DEPENDENCY_TYPE_FAILURE = 'failure';
    public const DEPENDENCY_TYPE_CANCEL = 'cancel';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    #[Groups(['task_dependency:read'])]
    private ?int $id = null;

    #[ORM\Column(name: 'task_id', type: Types::STRING, length: 36)]
    #[Groups(['task_dependency:read', 'task_dependency:write'])]
    private string $taskId;

    #[ORM\Column(name: 'depends_on_task_id', type: Types::STRING, length: 36)]
    #[Groups(['task_dependency:read', 'task_dependency:write'])]
    private string $dependsOnTaskId;

    #[ORM\Column(name: 'dependency_type', type: Types::STRING, length: 20)]
    #[Groups(['task_dependency:read', 'task_dependency:write'])]
    private string $dependencyType = self::DEPENDENCY_TYPE_FINISH;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    #[Groups(['task_dependency:read'])]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTaskId(): string
    {
        return $this->taskId;
    }

    public function setTaskId(string $taskId): self
    {
        $this->taskId = $taskId;
        return $this;
    }

    public function getDependsOnTaskId(): string
    {
        return $this->dependsOnTaskId;
    }

    public function setDependsOnTaskId(string $dependsOnTaskId): self
    {
        $this->dependsOnTaskId = $dependsOnTaskId;
        return $this;
    }

    public function getDependencyType(): string
    {
        return $this->dependencyType;
    }

    public function setDependencyType(string $dependencyType): self
    {
        $this->dependencyType = $dependencyType;
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
     * 检查依赖是否满足（基于给定的任务状态）
     */
    public function isSatisfiedBy(string $taskStatus): bool
    {
        return match ($this->dependencyType) {
            self::DEPENDENCY_TYPE_FINISH => in_array($taskStatus, [
                AsyncTask::STATUS_COMPLETED,
                AsyncTask::STATUS_FAILED,
                AsyncTask::STATUS_CANCELLED
            ]),
            self::DEPENDENCY_TYPE_SUCCESS => $taskStatus === AsyncTask::STATUS_COMPLETED,
            self::DEPENDENCY_TYPE_FAILURE => $taskStatus === AsyncTask::STATUS_FAILED,
            self::DEPENDENCY_TYPE_CANCEL => $taskStatus === AsyncTask::STATUS_CANCELLED,
            default => false,
        };
    }

    /**
     * 检查是否为完成依赖
     */
    public function isFinishDependency(): bool
    {
        return $this->dependencyType === self::DEPENDENCY_TYPE_FINISH;
    }

    /**
     * 检查是否为成功依赖
     */
    public function isSuccessDependency(): bool
    {
        return $this->dependencyType === self::DEPENDENCY_TYPE_SUCCESS;
    }

    /**
     * 检查是否为失败依赖
     */
    public function isFailureDependency(): bool
    {
        return $this->dependencyType === self::DEPENDENCY_TYPE_FAILURE;
    }

    /**
     * 检查是否为取消依赖
     */
    public function isCancelDependency(): bool
    {
        return $this->dependencyType === self::DEPENDENCY_TYPE_CANCEL;
    }

    /**
     * 获取依赖类型的描述
     */
    public function getDependencyTypeDescription(): string
    {
        return match ($this->dependencyType) {
            self::DEPENDENCY_TYPE_FINISH => '任务完成时',
            self::DEPENDENCY_TYPE_SUCCESS => '任务成功时',
            self::DEPENDENCY_TYPE_FAILURE => '任务失败时',
            self::DEPENDENCY_TYPE_CANCEL => '任务取消时',
            default => '未知类型',
        };
    }

    /**
     * 创建完成依赖
     */
    public static function createFinishDependency(string $taskId, string $dependsOnTaskId): self
    {
        return (new self())
            ->setTaskId($taskId)
            ->setDependsOnTaskId($dependsOnTaskId)
            ->setDependencyType(self::DEPENDENCY_TYPE_FINISH);
    }

    /**
     * 创建成功依赖
     */
    public static function createSuccessDependency(string $taskId, string $dependsOnTaskId): self
    {
        return (new self())
            ->setTaskId($taskId)
            ->setDependsOnTaskId($dependsOnTaskId)
            ->setDependencyType(self::DEPENDENCY_TYPE_SUCCESS);
    }

    /**
     * 创建失败依赖
     */
    public static function createFailureDependency(string $taskId, string $dependsOnTaskId): self
    {
        return (new self())
            ->setTaskId($taskId)
            ->setDependsOnTaskId($dependsOnTaskId)
            ->setDependencyType(self::DEPENDENCY_TYPE_FAILURE);
    }

    /**
     * 创建取消依赖
     */
    public static function createCancelDependency(string $taskId, string $dependsOnTaskId): self
    {
        return (new self())
            ->setTaskId($taskId)
            ->setDependsOnTaskId($dependsOnTaskId)
            ->setDependencyType(self::DEPENDENCY_TYPE_CANCEL);
    }

    /**
     * 获取所有可用的依赖类型
     */
    public static function getAvailableDependencyTypes(): array
    {
        return [
            self::DEPENDENCY_TYPE_FINISH,
            self::DEPENDENCY_TYPE_SUCCESS,
            self::DEPENDENCY_TYPE_FAILURE,
            self::DEPENDENCY_TYPE_CANCEL,
        ];
    }

    /**
     * 获取依赖类型映射（类型 => 描述）
     */
    public static function getDependencyTypeMap(): array
    {
        return [
            self::DEPENDENCY_TYPE_FINISH => '任务完成时',
            self::DEPENDENCY_TYPE_SUCCESS => '任务成功时',
            self::DEPENDENCY_TYPE_FAILURE => '任务失败时',
            self::DEPENDENCY_TYPE_CANCEL => '任务取消时',
        ];
    }

    /**
     * 验证依赖类型是否有效
     */
    public static function isValidDependencyType(string $type): bool
    {
        return in_array($type, self::getAvailableDependencyTypes());
    }

    /**
     * 检查是否为自依赖（循环依赖）
     */
    public function isSelfDependency(): bool
    {
        return $this->taskId === $this->dependsOnTaskId;
    }

    /**
     * 获取依赖关系的字符串表示
     */
    public function __toString(): string
    {
        return sprintf(
            'Task %s depends on Task %s (%s)',
            $this->taskId,
            $this->dependsOnTaskId,
            $this->getDependencyTypeDescription()
        );
    }
}
