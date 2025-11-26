<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * 注意：此Repository只提供只读操作
 * 所有用户数据的修改都应该通过专门的用户管理系统进行
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[] findAll()
 * @method User[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class, 'user');
    }

    /**
     * 根据用户ID数组查找用户
     *
     * @param array $userIds
     * @return User[]
     */
    public function findByIds(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        return $this->createQueryBuilder('u')
            ->where('u.id IN (:userIds)')
            ->setParameter('userIds', $userIds)
            ->getQuery()
            ->getResult();
    }

    /**
     * 根据用户名查找用户
     *
     * @param string $username
     * @return User|null
     */
    public function findByUsername(string $username): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.username = :username')
            ->setParameter('username', $username)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 根据邮箱查找用户
     *
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 查找活跃用户
     *
     * @param int $limit
     * @param int $offset
     * @return User[]
     */
    public function findActiveUsers(int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.status = :status')
            ->setParameter('status', 1)
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * 根据关键词搜索用户
     *
     * @param string $keyword
     * @param int $limit
     * @return User[]
     */
    public function searchByKeyword(string $keyword, int $limit = 20): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.username LIKE :keyword OR u.nickname LIKE :keyword')
            ->setParameter('keyword', '%' . $keyword . '%')
            ->andWhere('u.status = :status')
            ->setParameter('status', 1)
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取用户总数
     *
     * @return int
     */
    public function countActiveUsers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.status = :status')
            ->setParameter('status', 1)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // UserProviderInterface 接口要求的方法

    /**
     * 根据用户标识符加载用户（用于认证）
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->findByUsername($identifier);

        if (!$user) {
            // 如果用户名没找到，尝试用邮箱查找
            $user = $this->findByEmail($identifier);
        }

        if (!$user) {
            throw new UserNotFoundException(sprintf('用户 "%s" 不存在', $identifier));
        }

        if (!$user->isActive()) {
            throw new UserNotFoundException('用户账户已被禁用');
        }

        return $user;
    }

    /**
     * 支持旧版本的Symfony（向后兼容）
     */
    public function loadUserByUsername(string $username): UserInterface
    {
        return $this->loadUserByIdentifier($username);
    }

    /**
     * 刷新用户（重新从数据库加载用户数据）
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('不支持的用户类型: %s', get_class($user)));
        }

        $refreshedUser = $this->find($user->getId());

        if (null === $refreshedUser) {
            throw new UserNotFoundException(sprintf('用户ID为 "%s" 的用户不存在', $user->getId()));
        }

        if (!$refreshedUser->isActive()) {
            throw new UserNotFoundException('用户账户已被禁用');
        }

        return $refreshedUser;
    }

    /**
     * 检查是否支持给定的用户类
     */
    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    /**
     * 升级用户密码（PasswordUpgraderInterface接口要求的方法）
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('不支持的用户类型: %s', get_class($user)));
        }

        // 注意：由于用户数据库是只读的，这里不实际更新密码
        // 在实际应用中，如果需要更新密码，应该通过专门的用户管理系统进行
        // $user->setPassword($newHashedPassword);
        // $this->getEntityManager()->persist($user);
        // $this->getEntityManager()->flush();
    }
}
