<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;

/**
 * 用户只读服务
 *
 * 此服务专门用于提供用户数据的只读访问
 * 不提供任何修改用户数据的方法
 * 所有用户数据的修改都应该通过专门的用户管理系统进行
 */
class UserReadOnlyService
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * 根据ID获取用户信息
     */
    public function getUserById(int $userId): ?User
    {
        return $this->userRepository->find($userId);
    }

    /**
     * 根据用户名获取用户信息
     */
    public function getUserByUsername(string $username): ?User
    {
        return $this->userRepository->findByUsername($username);
    }

    /**
     * 根据邮箱获取用户信息
     */
    public function getUserByEmail(string $email): ?User
    {
        return $this->userRepository->findByEmail($email);
    }

    /**
     * 根据ID数组批量获取用户信息
     *
     * @param int[] $userIds
     * @return User[]
     */
    public function getUsersByIds(array $userIds): array
    {
        return $this->userRepository->findByIds($userIds);
    }

    /**
     * 获取活跃用户列表
     *
     * @param int $limit
     * @param int $offset
     * @return User[]
     */
    public function getActiveUsers(int $limit = 20, int $offset = 0): array
    {
        return $this->userRepository->findActiveUsers($limit, $offset);
    }

    /**
     * 根据关键词搜索用户
     *
     * @param string $keyword
     * @param int $limit
     * @return User[]
     */
    public function searchUsers(string $keyword, int $limit = 20): array
    {
        return $this->userRepository->searchByKeyword($keyword, $limit);
    }

    /**
     * 获取用户的显示名称
     * 优先返回nickname，如果为空则返回username
     */
    public function getUserDisplayName(?User $user): string
    {
        if (!$user) {
            return '未知用户';
        }

        return $user->getDisplayName();
    }

    /**
     * 获取用户头像URL
     * 如果用户没有设置头像，返回默认头像
     */
    public function getUserAvatar(?User $user): string
    {
        if (!$user) {
            return '/assets/default-avatar.png';
        }

        $avatar = $user->getAvatar();
        return $avatar ?: '/assets/default-avatar.png';
    }

    /**
     * 检查用户是否处于活跃状态
     */
    public function isUserActive(?User $user): bool
    {
        return $user && $user->isActive();
    }

    /**
     * 获取活跃用户总数
     */
    public function getActiveUserCount(): int
    {
        return $this->userRepository->countActiveUsers();
    }

    /**
     * 验证用户是否存在
     */
    public function userExists(int $userId): bool
    {
        return $this->userRepository->find($userId) !== null;
    }

    /**
     * 格式化用户信息用于API响应
     *
     * @param User|null $user
     * @return array|null
     */
    public function formatUserForApi(?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'nickname' => $user->getNickname(),
            'displayName' => $user->getDisplayName(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhone(),
            'avatar' => $this->getUserAvatar($user),
            'status' => $user->getStatus(),
            'isActive' => $user->isActive(),
            'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $user->getUpdatedAt()?->format('Y-m-d H:i:s')
        ];
    }

    /**
     * 批量格式化用户信息用于API响应
     *
     * @param User[] $users
     * @return array
     */
    public function formatUsersForApi(array $users): array
    {
        return array_map([$this, 'formatUserForApi'], $users);
    }
}
