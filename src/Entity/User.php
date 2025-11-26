<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $username;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $email;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $nickname = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: 'smallint')]
    private int $status = 1; // 1: active, 0: inactive

    #[ORM\Column(type: 'string', length: 255)]
    private string $password;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === 1;
    }

    /**
     * 获取显示名称（优先使用nickname，其次使用username）
     */
    public function getDisplayName(): string
    {
        return $this->nickname ?: $this->username;
    }

    // UserInterface 接口要求的方法

    /**
     * 获取用户标识符（用于认证）
     */
    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    /**
     * 获取用户角色
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // 确保每个用户至少有一个 ROLE_USER 角色
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * 获取密码（用于认证）
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * 获取盐值（现代密码哈希通常不需要盐值）
     */
    public function getSalt(): ?string
    {
        return null;
    }

    /**
     * 擦除敏感信息
     */
    public function eraseCredentials(): void
    {
        // 如果需要，可以在这里清除任何临时敏感数据
        // 但密码字段不应该被清除，因为它是持久化的
    }

    // 注意：User实体为只读，不提供任何修改方法
    // 所有用户数据的修改都应该通过专门的用户管理系统进行
}
