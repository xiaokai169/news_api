<?php

namespace App\EventListener;

use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * 用户数据库只读事件监听器
 *
 * 此监听器确保用户数据库只能进行读取操作
 * 任何尝试写入用户数据的行为都会被阻止
 */
class UserDatabaseReadonlyListener
{
    /**
     * 阻止用户实体的创建操作
     */
    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($this->isUserEntity($entity)) {
            throw new AccessDeniedHttpException(
                '用户数据库为只读模式，不允许创建用户数据。请通过专门的用户管理系统进行用户创建。'
            );
        }
    }

    /**
     * 阻止用户实体的更新操作
     */
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($this->isUserEntity($entity)) {
            throw new AccessDeniedHttpException(
                '用户数据库为只读模式，不允许修改用户数据。请通过专门的用户管理系统进行用户更新。'
            );
        }
    }

    /**
     * 阻止用户实体的删除操作
     */
    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($this->isUserEntity($entity)) {
            throw new AccessDeniedHttpException(
                '用户数据库为只读模式，不允许删除用户数据。请通过专门的用户管理系统进行用户删除。'
            );
        }
    }

    /**
     * 检查是否为用户实体
     */
    private function isUserEntity(object $entity): bool
    {
        $entityClass = get_class($entity);

        // 检查是否为User实体或其子类
        return $entityClass === 'App\\Entity\\User' ||
               is_subclass_of($entity, 'App\\Entity\\User');
    }
}
