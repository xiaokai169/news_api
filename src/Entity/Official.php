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

    #[ORM\ManyToOne(inversedBy: 'officials')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['official:read', 'official:write'])]
    private ?SysNewsArticleCategory $category = null;

    public function __construct()
    {
        $this->createAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // Getters and Setters
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

    public function getCategory(): ?SysNewsArticleCategory
    {
        return $this->category;
    }

    public function setCategory(?SysNewsArticleCategory $category): static
    {
        $this->category = $category;

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
}
