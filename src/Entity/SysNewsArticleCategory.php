<?php

namespace App\Entity;

use App\Repository\SysNewsArticleCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SysNewsArticleCategoryRepository::class)]
#[ORM\Table(name: 'sys_news_article_category')]
#[ORM\HasLifecycleCallbacks]
class SysNewsArticleCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    #[Groups(['SysNewsArticleCategory:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['default' => ''])]
    #[Assert\NotBlank(message: '分类编码不能为空')]
    #[Assert\Length(max: 255)]
    #[Groups(['SysNewsArticleCategory:read', 'SysNewsArticleCategory:write', 'sysNewsArticle:read'])]
    private string $code = '';

    #[ORM\Column(type: Types::STRING, length: 255, options: ['default' => ''])]
    #[Assert\NotBlank(message: '分类名称不能为空')]
    #[Assert\Length(max: 255)]
    #[Groups(['SysNewsArticleCategory:read', 'SysNewsArticleCategory:write', 'sysNewsArticle:read'])]
    private string $name = '';

    #[ORM\Column(type: Types::STRING, length: 255, options: ['default' => ''])]
    #[Assert\Length(max: 255)]
    #[Groups(['SysNewsArticleCategory:read', 'SysNewsArticleCategory:write'])]
    private string $creator = '';

    /**
     * @var Collection<int, SysNewsArticle>
     */
    #[ORM\OneToMany(
        targetEntity: SysNewsArticle::class,
        mappedBy: 'category',
        cascade: ['persist'],
        orphanRemoval: true,
        fetch: 'EXTRA_LAZY'
    )]
    #[ORM\JsonIgnore]
    private Collection $articles;

    /**
     * @var Collection<int, Official>
     */
    #[ORM\OneToMany(targetEntity: Official::class, mappedBy: 'category')]
    #[ORM\JsonIgnore]
    private Collection $officials;

    // #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    // #[Groups(['SysNewsArticleCategory:read'])]
    // private ?\DateTimeInterface $createTime = null;

    // #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    // #[Groups(['SysNewsArticleCategory:read'])]
    // private ?\DateTimeInterface $updateTime = null;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
        $this->officials = new ArrayCollection();
    }

    // 临时注释掉生命周期回调，因为字段已被移除
    // #[ORM\PrePersist]
    // /** @return void */
    // private function setCreateTimeValue(): void
    // {
    //     if ($this->createTime === null) {
    //         $this->createTime = new \DateTime();
    //     }
    //     if ($this->updateTime === null) {
    //         $this->updateTime = new \DateTime();
    //     }
    // }

    // #[ORM\PreUpdate]
    // /** @return void */
    // private function setUpdateTimeValue(): void
    // {
    //     $this->updateTime = new \DateTime();
    // }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    // 临时注释掉时间相关方法，因为字段已被移除
    // public function getCreateTime(): ?\DateTimeInterface
    // {
    //     return $this->createTime;
    // }

    // // 移除setCreateTime方法，防止外部修改创建时间
    //  public function setCreateTime(\DateTimeInterface $createTime): self
    //  {
    //      $this->createTime = $createTime;
    //      return $this;
    //  }

    // public function getUpdateTime(): ?\DateTimeInterface
    // {
    //     return $this->updateTime;
    // }

    // public function setUpdateTime(?\DateTimeInterface $updateTime): self
    // {
    //     $this->updateTime = $updateTime;
    //     return $this;
    // }

    public function getCreator(): string
    {
        return $this->creator;
    }

    public function setCreator(string $creator): self
    {
        $this->creator = $creator;
        return $this;
    }

    // /**
    //  * 添加格式化时间的方法，方便前端显示
    //  */
    // public function getCreateTimeFormatted(): string
    // {
    //     return $this->createTime ? $this->createTime->format('Y-m-d H:i:s') : '';
    // }

    // public function getUpdateTimeFormatted(): string
    // {
    //     return $this->updateTime ? $this->updateTime->format('Y-m-d H:i:s') : '';
    // }

    /**
     * @return Collection<int, SysNewsArticle>
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(SysNewsArticle $article): static
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
            $article->setCategory($this);
        }

        return $this;
    }

    public function removeArticle(SysNewsArticle $article): static
    {
        if ($this->articles->removeElement($article)) {
            // set the owning side to null (unless already changed)
            if ($article->getCategory() === $this) {
                $article->setCategory(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Official>
     */
    public function getOfficials(): Collection
    {
        return $this->officials;
    }

    public function addOfficial(Official $official): static
    {
        if (!$this->officials->contains($official)) {
            $this->officials->add($official);
            $official->setCategory($this);
        }

        return $this;
    }

    public function removeOfficial(Official $official): static
    {
        if ($this->officials->removeElement($official)) {
            // set the owning side to null (unless already changed)
            if ($official->getCategory() === $this) {
                $official->setCategory(null);
            }
        }

        return $this;
    }
}
