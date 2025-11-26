<?php

namespace App\DTO\Request\Wechat;

use App\DTO\Base\AbstractDto;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

/**
 * 微信文章数据DTO（嵌套类）
 */
#[OA\Schema(
    schema: 'ArticleDataDto',
    title: '微信文章数据',
    description: '微信同步时的单篇文章数据传输对象'
)]
class ArticleDataDto extends AbstractDto
{
    /**
     * 文章标题
     */
    #[Assert\NotBlank(message: '文章标题不能为空')]
    #[Assert\Length(max: 255, maxMessage: '文章标题不能超过255个字符')]
    #[OA\Property(
        description: '文章标题',
        example: '科技新闻：最新技术突破',
        maxLength: 255
    )]
    protected string $title = '';

    /**
     * 文章内容
     */
    #[Assert\NotBlank(message: '文章内容不能为空')]
    #[OA\Property(
        description: '文章内容，支持HTML格式',
        example: '<p>这是一篇关于最新技术突破的文章...</p>'
    )]
    protected string $content = '';

    /**
     * 文章封面图URL
     */
    #[Assert\NotBlank(message: '文章封面图不能为空')]
    #[Assert\Url(message: '封面图URL格式不正确')]
    #[OA\Property(
        description: '文章封面图片URL',
        example: 'https://example.com/image.jpg'
    )]
    protected string $coverUrl = '';

    /**
     * 文章摘要
     */
    #[Assert\Length(max: 500, maxMessage: '文章摘要不能超过500个字符')]
    #[OA\Property(
        description: '文章摘要或描述',
        example: '本文介绍了最新的技术突破和发展趋势...',
        maxLength: 500
    )]
    protected string $summary = '';

    /**
     * 文章原文链接
     */
    #[Assert\Url(message: '原文链接URL格式不正确')]
    #[OA\Property(
        description: '文章原文链接',
        example: 'https://mp.weixin.qq.com/s/xxxxx'
    )]
    protected string $originalUrl = '';

    /**
     * 文章作者
     */
    #[Assert\Length(max: 100, maxMessage: '作者名称不能超过100个字符')]
    #[OA\Property(
        description: '文章作者',
        example: '张三',
        maxLength: 100
    )]
    protected string $author = '';

    /**
     * 发布时间
     */
    #[Assert\DateTime(message: '发布时间格式不正确')]
    #[OA\Property(
        description: '文章发布时间',
        example: '2024-01-15 10:30:00',
        format: 'date-time'
    )]
    protected ?string $publishTime = null;

    /**
     * 文章分类
     */
    #[Assert\Length(max: 50, maxMessage: '文章分类不能超过50个字符')]
    #[OA\Property(
        description: '文章分类',
        example: '科技',
        maxLength: 50
    )]
    protected string $category = '';

    /**
     * 文章标签（数组）
     */
    #[Assert\Type(type: 'array', message: '文章标签必须是数组')]
    #[Assert\All([
        new Assert\Type(type: 'string', message: '标签必须是字符串'),
        new Assert\Length(max: 50, maxMessage: '单个标签不能超过50个字符')
    ])]
    #[OA\Property(
        description: '文章标签列表',
        type: 'array',
        items: new OA\Items(type: 'string', example: '技术')
    )]
    protected array $tags = [];

    /**
     * 文章状态
     */
    #[Assert\Choice(choices: ['draft', 'published', 'archived'], message: '文章状态必须是draft、published或archived')]
    #[OA\Property(
        description: '文章状态',
        enum: ['draft', 'published', 'archived'],
        example: 'published'
    )]
    protected string $status = 'published';

    /**
     * 文章来源
     */
    #[Assert\Length(max: 100, maxMessage: '文章来源不能超过100个字符')]
    #[OA\Property(
        description: '文章来源',
        example: '微信公众号',
        maxLength: 100
    )]
    protected string $source = '';

    /**
     * 阅读量
     */
    #[Assert\Type(type: 'integer', message: '阅读量必须是整数')]
    #[Assert\PositiveOrZero(message: '阅读量不能为负数')]
    #[OA\Property(
        description: '文章阅读量',
        example: 1000,
        minimum: 0
    )]
    protected int $readCount = 0;

    /**
     * 点赞数
     */
    #[Assert\Type(type: 'integer', message: '点赞数必须是整数')]
    #[Assert\PositiveOrZero(message: '点赞数不能为负数')]
    #[OA\Property(
        description: '文章点赞数',
        example: 50,
        minimum: 0
    )]
    protected int $likeCount = 0;

    /**
     * 构造函数
     *
     * @param array $data 初始化数据
     */
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $this->fromArray($data);
        }
    }

    /**
     * 获取文章标题
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * 设置文章标题
     *
     * @param string $title
     * @return self
     */
    public function setTitle(string $title): self
    {
        $this->title = $this->cleanString($title);
        return $this;
    }

    /**
     * 获取文章内容
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * 设置文章内容
     *
     * @param string $content
     * @return self
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * 获取封面图URL
     *
     * @return string
     */
    public function getCoverUrl(): string
    {
        return $this->coverUrl;
    }

    /**
     * 设置封面图URL
     *
     * @param string $coverUrl
     * @return self
     */
    public function setCoverUrl(string $coverUrl): self
    {
        $this->coverUrl = $this->cleanString($coverUrl);
        return $this;
    }

    /**
     * 获取文章摘要
     *
     * @return string
     */
    public function getSummary(): string
    {
        return $this->summary;
    }

    /**
     * 设置文章摘要
     *
     * @param string $summary
     * @return self
     */
    public function setSummary(string $summary): self
    {
        $this->summary = $this->cleanString($summary);
        return $this;
    }

    /**
     * 获取原文链接
     *
     * @return string
     */
    public function getOriginalUrl(): string
    {
        return $this->originalUrl;
    }

    /**
     * 设置原文链接
     *
     * @param string $originalUrl
     * @return self
     */
    public function setOriginalUrl(string $originalUrl): self
    {
        $this->originalUrl = $this->cleanString($originalUrl);
        return $this;
    }

    /**
     * 获取作者
     *
     * @return string
     */
    public function getAuthor(): string
    {
        return $this->author;
    }

    /**
     * 设置作者
     *
     * @param string $author
     * @return self
     */
    public function setAuthor(string $author): self
    {
        $this->author = $this->cleanString($author);
        return $this;
    }

    /**
     * 获取发布时间
     *
     * @return string|null
     */
    public function getPublishTime(): ?string
    {
        return $this->publishTime;
    }

    /**
     * 设置发布时间
     *
     * @param string|null $publishTime
     * @return self
     */
    public function setPublishTime(?string $publishTime): self
    {
        $this->publishTime = $this->cleanString($publishTime);
        return $this;
    }

    /**
     * 获取文章分类
     *
     * @return string
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * 设置文章分类
     *
     * @param string $category
     * @return self
     */
    public function setCategory(string $category): self
    {
        $this->category = $this->cleanString($category);
        return $this;
    }

    /**
     * 获取文章标签
     *
     * @return array
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * 设置文章标签
     *
     * @param array $tags
     * @return self
     */
    public function setTags(array $tags): self
    {
        $this->tags = array_filter($tags, 'is_string');
        return $this;
    }

    /**
     * 获取文章状态
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * 设置文章状态
     *
     * @param string $status
     * @return self
     */
    public function setStatus(string $status): self
    {
        $this->status = in_array($status, ['draft', 'published', 'archived']) ? $status : 'published';
        return $this;
    }

    /**
     * 获取文章来源
     *
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * 设置文章来源
     *
     * @param string $source
     * @return self
     */
    public function setSource(string $source): self
    {
        $this->source = $this->cleanString($source);
        return $this;
    }

    /**
     * 获取阅读量
     *
     * @return int
     */
    public function getReadCount(): int
    {
        return $this->readCount;
    }

    /**
     * 设置阅读量
     *
     * @param int $readCount
     * @return self
     */
    public function setReadCount(int $readCount): self
    {
        $this->readCount = max(0, $readCount);
        return $this;
    }

    /**
     * 获取点赞数
     *
     * @return int
     */
    public function getLikeCount(): int
    {
        return $this->likeCount;
    }

    /**
     * 设置点赞数
     *
     * @param int $likeCount
     * @return self
     */
    public function setLikeCount(int $likeCount): self
    {
        $this->likeCount = max(0, $likeCount);
        return $this;
    }

    /**
     * 转换为数组
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
            'coverUrl' => $this->coverUrl,
            'summary' => $this->summary,
            'originalUrl' => $this->originalUrl,
            'author' => $this->author,
            'publishTime' => $this->publishTime,
            'category' => $this->category,
            'tags' => $this->tags,
            'status' => $this->status,
            'source' => $this->source,
            'readCount' => $this->readCount,
            'likeCount' => $this->likeCount,
        ];
    }

    /**
     * 从数组创建实例
     *
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $instance = new static();

        if (isset($data['title'])) {
            $instance->setTitle($data['title']);
        }

        if (isset($data['content'])) {
            $instance->setContent($data['content']);
        }

        if (isset($data['coverUrl'])) {
            $instance->setCoverUrl($data['coverUrl']);
        }

        if (isset($data['summary'])) {
            $instance->setSummary($data['summary']);
        }

        if (isset($data['originalUrl'])) {
            $instance->setOriginalUrl($data['originalUrl']);
        }

        if (isset($data['author'])) {
            $instance->setAuthor($data['author']);
        }

        if (isset($data['publishTime'])) {
            $instance->setPublishTime($data['publishTime']);
        }

        if (isset($data['category'])) {
            $instance->setCategory($data['category']);
        }

        if (isset($data['tags']) && is_array($data['tags'])) {
            $instance->setTags($data['tags']);
        }

        if (isset($data['status'])) {
            $instance->setStatus($data['status']);
        }

        if (isset($data['source'])) {
            $instance->setSource($data['source']);
        }

        if (isset($data['readCount'])) {
            $instance->setReadCount((int)$data['readCount']);
        }

        if (isset($data['likeCount'])) {
            $instance->setLikeCount((int)$data['likeCount']);
        }

        return $instance;
    }

    /**
     * 验证文章数据
     *
     * @return array 验证错误数组
     */
    public function validateArticleData(): array
    {
        $errors = [];

        // 验证标题
        if (empty($this->title)) {
            $errors['title'] = '文章标题不能为空';
        } elseif (strlen($this->title) > 255) {
            $errors['title'] = '文章标题不能超过255个字符';
        }

        // 验证内容
        if (empty($this->content)) {
            $errors['content'] = '文章内容不能为空';
        }

        // 验证封面图URL
        if (empty($this->coverUrl)) {
            $errors['coverUrl'] = '文章封面图不能为空';
        } elseif (!filter_var($this->coverUrl, FILTER_VALIDATE_URL)) {
            $errors['coverUrl'] = '封面图URL格式不正确';
        }

        // 验证原文链接（如果提供）
        if (!empty($this->originalUrl) && !filter_var($this->originalUrl, FILTER_VALIDATE_URL)) {
            $errors['originalUrl'] = '原文链接URL格式不正确';
        }

        // 验证发布时间（如果提供）
        if (!empty($this->publishTime)) {
            $dateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $this->publishTime);
            if (!$dateTime) {
                $dateTime = \DateTime::createFromFormat('Y-m-d\TH:i:s', $this->publishTime);
            }
            if (!$dateTime) {
                $errors['publishTime'] = '发布时间格式不正确，请使用Y-m-d H:i:s或ISO 8601格式';
            }
        }

        return $errors;
    }

    /**
     * 检查是否有有效的文章数据
     *
     * @return bool
     */
    public function hasValidArticleData(): bool
    {
        return !empty($this->title) && !empty($this->content) && !empty($this->coverUrl);
    }

    /**
     * 获取文章摘要信息
     *
     * @return array
     */
    public function getArticleSummary(): array
    {
        return [
            'title' => $this->title,
            'author' => $this->author,
            'category' => $this->category,
            'status' => $this->status,
            'source' => $this->source,
            'hasValidData' => $this->hasValidArticleData(),
            'tagCount' => count($this->tags),
            'readCount' => $this->readCount,
            'likeCount' => $this->likeCount,
        ];
    }
}
