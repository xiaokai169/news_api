<?php

namespace App\Entity;

use App\Repository\WechatPublicAccountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: WechatPublicAccountRepository::class)]
#[UniqueEntity(fields: ['appId'], message: 'appId 已存在')]
#[UniqueEntity(fields: ['appSecret'], message: 'appSecret 已存在')]
#[ORM\Table(name: 'wechat_public_account')]
#[ORM\HasLifecycleCallbacks]
class WechatPublicAccount
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Groups(['wechat_account:read'])]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['wechat_account:read', 'wechat_account:write'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['wechat_account:read', 'wechat_account:write'])]
    private ?string $description = null;

    #[ORM\Column(name: 'avatar_url', type: Types::STRING, length: 500, nullable: true)]
    #[Groups(['wechat_account:read', 'wechat_account:write'])]
    private ?string $avatarUrl = null;

    #[ORM\Column(name: 'app_id', type: Types::STRING, length: 128, nullable: true, unique: true)]
    #[Groups(['wechat_account:read', 'wechat_account:write'])]
    private ?string $appId = null;

    #[ORM\Column(name: 'app_secret', type: Types::STRING, length: 128, nullable: true, unique: true)]
    #[Groups(['wechat_account:read','wechat_account:write'])]
    private ?string $appSecret = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    #[Groups(['wechat_account:read'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE)]
    #[Groups(['wechat_account:read'])]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['wechat_account:read', 'wechat_account:write'])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    #[Groups(['wechat_account:read'])]
    private ?string $token = null;

    #[ORM\Column(name: 'encoding_aeskey', type: Types::STRING, length: 128, nullable: true)]
    #[Groups(['wechat_account:read'])]
    private ?string $encodingAESKey = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->isActive = true;
    }

    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): self
    {
        $this->avatarUrl = $avatarUrl;
        return $this;
    }

    public function getAppId(): ?string
    {
        return $this->appId;
    }

    public function setAppId(?string $appId): self
    {
        $this->appId = $appId;
        return $this;
    }

    public function getAppSecret(): ?string
    {
        return $this->appSecret;
    }

    public function setAppSecret(?string $appSecret): self
    {
        $this->appSecret = $appSecret;
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

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): self
    {
        $this->token = $token;
        return $this;
    }

    public function getEncodingAESKey(): ?string
    {
        return $this->encodingAESKey;
    }

    public function setEncodingAESKey(?string $encodingAESKey): self
    {
        $this->encodingAESKey = $encodingAESKey;
        return $this;
    }
}
