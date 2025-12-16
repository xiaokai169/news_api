<?php
// src/Entity/Official.php

namespace App\Entity;

use App\Repository\OfficialRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OfficialRepository::class)]
#[ORM\Table(name: 'official')]
#[ORM\HasLifecycleCallbacks]
class Official
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['official:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100, options: ['default' => ''])]
    #[Assert\Length(max: 100)]
    #[Groups(['official:read', 'official:write'])]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Groups(['official:read', 'official:write'])]
    private string $content;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 2])]
    #[Assert\Choice(choices: [1, 2])]
    #[Groups(['official:read', 'official:write'])]
    private int $status = 2;

    #[ORM\Column(name: 'category_id', type: Types::INTEGER, options: ['default' => 1])]
    #[Groups(['official:read', 'official:write'])]
    private int $categoryId = 1;

    #[ORM\Column(name: 'is_deleted', type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['official:read', 'official:write'])]
    private bool $isDeleted = false;

    #[ORM\Column(name: 'create_at', type: Types::DATETIME_MUTABLE)]
    #[Groups(['official:read'])]
    private \DateTimeInterface $createAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE)]
    #[Groups(['official:read'])]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(name: 'release_time', type: Types::STRING, length: 255, options: ['default' => ''])]
    #[Groups(['official:read', 'official:write'])]
    private string $releaseTime = '';

    #[ORM\Column(name: 'original_url', type: Types::STRING, length: 255, options: ['default' => ''])]
    #[Groups(['official:read', 'official:write'])]
    private string $originalUrl = '';

    #[ORM\Column(name: 'article_id', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['official:read', 'official:write'])]
    private ?string $articleId = null;

    #[ORM\Column(name: 'wechat_account_id', type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['official:read', 'official:write'])]
    private ?string $wechatAccountId = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['official:read', 'official:write'])]
    private ?string $author = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['official:read', 'official:write'])]
    private ?string $digest = null;

    #[ORM\Column(name: 'thumb_media_id', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['official:read', 'official:write'])]
    private ?string $thumbMediaId = null;

    #[ORM\Column(name: 'thumb_url', type: Types::STRING, length: 500, nullable: true)]
    #[Groups(['official:read', 'official:write'])]
    private ?string $thumbUrl = null;

    #[ORM\Column(name: 'show_cover_pic', type: Types::SMALLINT, options: ['default' => 0])]
    #[Groups(['official:read', 'official:write'])]
    private int $showCoverPic = 0;

    #[ORM\Column(name: 'need_open_comment', type: Types::SMALLINT, options: ['default' => 0])]
    #[Groups(['official:read', 'official:write'])]
    private int $needOpenComment = 0;

    #[ORM\Column(name: 'only_fans_can_comment', type: Types::SMALLINT, options: ['default' => 0])]
    #[Groups(['official:read', 'official:write'])]
    private int $onlyFansCanComment = 0;

    // 添加生命周期回调方法
    #[ORM\PrePersist]
    public function setTimestampsOnCreate(): void
    {
        $this->createAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setTimestampsOnUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }

    public function setCategoryId(int $categoryId): self
    {
        $this->categoryId = $categoryId;

        return $this;
    }

    public function getIsDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): self
    {
        $this->isDeleted = $isDeleted;

        return $this;
    }

    public function getCreateAt(): \DateTimeInterface
    {
        return $this->createAt;
    }

    public function setCreateAt(\DateTimeInterface $createAt): self
    {
        $this->createAt = $createAt;

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

    public function getReleaseTime(): string
    {
        return $this->releaseTime;
    }

    public function setReleaseTime(string $releaseTime): self
    {
        $this->releaseTime = $releaseTime;

        return $this;
    }

    public function getOriginalUrl(): string
    {
        return $this->originalUrl;
    }

    public function setOriginalUrl(string $originalUrl): self
    {
        $this->originalUrl = $originalUrl;

        return $this;
    }

    public function getArticleId(): ?string
    {
        return $this->articleId;
    }

    public function setArticleId(?string $articleId): self
    {
        $this->articleId = $articleId;

        return $this;
    }

    public function getWechatAccountId(): ?string
    {
        return $this->wechatAccountId;
    }

    public function setWechatAccountId(?string $wechatAccountId): self
    {
        $this->wechatAccountId = $wechatAccountId;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getDigest(): ?string
    {
        return $this->digest;
    }

    public function setDigest(?string $digest): self
    {
        $this->digest = $digest;

        return $this;
    }

    public function getThumbMediaId(): ?string
    {
        return $this->thumbMediaId;
    }

    public function setThumbMediaId(?string $thumbMediaId): self
    {
        $this->thumbMediaId = $thumbMediaId;

        return $this;
    }

    public function getThumbUrl(): ?string
    {
        return $this->thumbUrl;
    }

    public function setThumbUrl(?string $thumbUrl): self
    {
        $this->thumbUrl = $thumbUrl;

        return $this;
    }

    public function getShowCoverPic(): int
    {
        return $this->showCoverPic;
    }

    public function setShowCoverPic(int $showCoverPic): self
    {
        $this->showCoverPic = $showCoverPic;

        return $this;
    }

    public function getNeedOpenComment(): int
    {
        return $this->needOpenComment;
    }

    public function setNeedOpenComment(int $needOpenComment): self
    {
        $this->needOpenComment = $needOpenComment;

        return $this;
    }

    public function getOnlyFansCanComment(): int
    {
        return $this->onlyFansCanComment;
    }

    public function setOnlyFansCanComment(int $onlyFansCanComment): self
    {
        $this->onlyFansCanComment = $onlyFansCanComment;
        return $this;
    }
}
// 添加缺失的类结束大括号
