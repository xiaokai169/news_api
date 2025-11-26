<?php

namespace App\DTO\Shared;

use App\DTO\Base\AbstractDto;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

use OpenApi\Attributes as OA;

/**
 * 分页数据传输对象
 * 用于处理分页相关的数据
 */
#[OA\Schema(
    schema: 'PaginationDto',
    title: '分页数据传输对象',
    description: '用于处理分页相关的数据结构'
)]
class PaginationDto extends AbstractDto
{
    /**
     * 当前页码
     */
    #[Assert\Type(type: 'integer', message: '当前页码必须是整数')]
    #[Assert\Positive(message: '当前页码必须大于0')]
    #[Groups(['pagination:read', 'pagination:write'])]
    protected int $currentPage;

    /**
     * 每页记录数
     */
    #[Assert\Type(type: 'integer', message: '每页记录数必须是整数')]
    #[Assert\Positive(message: '每页记录数必须大于0')]
    #[Assert\LessThanOrEqual(value: 100, message: '每页记录数不能超过100')]
    #[Groups(['pagination:read', 'pagination:write'])]
    protected int $perPage;

    /**
     * 总记录数
     */
    #[Assert\Type(type: 'integer', message: '总记录数必须是整数')]
    #[Assert\PositiveOrZero(message: '总记录数不能为负数')]
    #[Groups(['pagination:read'])]
    protected int $totalItems;

    /**
     * 总页数
     */
    #[Assert\Type(type: 'integer', message: '总页数必须是整数')]
    #[Assert\PositiveOrZero(message: '总页数不能为负数')]
    #[Groups(['pagination:read'])]
    protected int $totalPages;

    /**
     * 是否有上一页
     */
    #[Assert\Type(type: 'bool', message: '是否有上一页必须是布尔值')]
    #[Groups(['pagination:read'])]
    protected bool $hasPreviousPage;

    /**
     * 是否有下一页
     */
    #[Assert\Type(type: 'bool', message: '是否有下一页必须是布尔值')]
    #[Groups(['pagination:read'])]
    protected bool $hasNextPage;

    /**
     * 上一页页码
     */
    #[Assert\Type(type: 'integer', message: '上一页页码必须是整数')]
    #[Assert\PositiveOrZero(message: '上一页页码不能为负数')]
    #[Groups(['pagination:read'])]
    protected ?int $previousPage;

    /**
     * 下一页页码
     */
    #[Assert\Type(type: 'integer', message: '下一页页码必须是整数')]
    #[Assert\Positive(message: '下一页页码必须大于0')]
    #[Groups(['pagination:read'])]
    protected ?int $nextPage;

    /**
     * 当前页起始记录数
     */
    #[Assert\Type(type: 'integer', message: '起始记录数必须是整数')]
    #[Assert\PositiveOrZero(message: '起始记录数不能为负数')]
    #[Groups(['pagination:read'])]
    protected int $from;

    /**
     * 当前页结束记录数
     */
    #[Assert\Type(type: 'integer', message: '结束记录数必须是整数')]
    #[Assert\PositiveOrZero(message: '结束记录数不能为负数')]
    #[Groups(['pagination:read'])]
    protected int $to;

    /**
     * 偏移量
     */
    #[Assert\Type(type: 'integer', message: '偏移量必须是整数')]
    #[Assert\PositiveOrZero(message: '偏移量不能为负数')]
    #[Groups(['pagination:read', 'pagination:write'])]
    protected int $offset;

    /**
     * 分页链接
     */
    #[Assert\Type(type: 'array', message: '分页链接必须是数组')]
    #[Groups(['pagination:read'])]
    protected array $links = [];

    /**
     * 分页信息摘要
     */
    #[Assert\Type(type: 'array', message: '分页摘要必须是数组')]
    #[Groups(['pagination:read'])]
    protected array $summary = [];

    /**
     * 构造函数
     *
     * @param int $currentPage 当前页码
     * @param int $perPage 每页记录数
     * @param int $totalItems 总记录数
     */
    public function __construct(int $currentPage = 1, int $perPage = 20, int $totalItems = 0)
    {
        $this->currentPage = max(1, $currentPage);
        $this->perPage = max(1, min(100, $perPage));
        $this->totalItems = max(0, $totalItems);

        $this->calculatePagination();
    }

    /**
     * 计算分页相关数据
     */
    protected function calculatePagination(): void
    {
        // 计算总页数
        $this->totalPages = $this->perPage > 0 ? (int) ceil($this->totalItems / $this->perPage) : 0;

        // 计算偏移量
        $this->offset = ($this->currentPage - 1) * $this->perPage;

        // 计算是否有上一页和下一页
        $this->hasPreviousPage = $this->currentPage > 1;
        $this->hasNextPage = $this->currentPage < $this->totalPages;

        // 计算上一页和下一页页码
        $this->previousPage = $this->hasPreviousPage ? $this->currentPage - 1 : null;
        $this->nextPage = $this->hasNextPage ? $this->currentPage + 1 : null;

        // 计算当前页的起始和结束记录数
        if ($this->totalItems > 0) {
            $this->from = $this->offset + 1;
            $this->to = min($this->offset + $this->perPage, $this->totalItems);
        } else {
            $this->from = 0;
            $this->to = 0;
        }

        // 生成分页摘要
        $this->generateSummary();
    }

    /**
     * 生成分页摘要
     */
    protected function generateSummary(): void
    {
        $this->summary = [
            'showing' => $this->totalItems > 0 ? sprintf('显示第 %d - %d 项，共 %d 项', $this->from, $this->to, $this->totalItems) : '暂无数据',
            'current_page_info' => sprintf('第 %d 页，共 %d 页', $this->currentPage, $this->totalPages),
            'items_per_page' => sprintf('每页显示 %d 项', $this->perPage),
            'total_items' => sprintf('总共 %d 项', $this->totalItems),
        ];
    }

    /**
     * 获取当前页码
     *
     * @return int
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * 设置当前页码
     *
     * @param int $currentPage
     * @return self
     */
    public function setCurrentPage(int $currentPage): self
    {
        $this->currentPage = max(1, $currentPage);
        $this->calculatePagination();
        return $this;
    }

    /**
     * 获取每页记录数
     *
     * @return int
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * 设置每页记录数
     *
     * @param int $perPage
     * @return self
     */
    public function setPerPage(int $perPage): self
    {
        $this->perPage = max(1, min(100, $perPage));
        $this->calculatePagination();
        return $this;
    }

    /**
     * 获取总记录数
     *
     * @return int
     */
    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    /**
     * 设置总记录数
     *
     * @param int $totalItems
     * @return self
     */
    public function setTotalItems(int $totalItems): self
    {
        $this->totalItems = max(0, $totalItems);
        $this->calculatePagination();
        return $this;
    }

    /**
     * 获取总页数
     *
     * @return int
     */
    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    /**
     * 是否有上一页
     *
     * @return bool
     */
    public function hasPreviousPage(): bool
    {
        return $this->hasPreviousPage;
    }

    /**
     * 是否有下一页
     *
     * @return bool
     */
    public function hasNextPage(): bool
    {
        return $this->hasNextPage;
    }

    /**
     * 获取上一页页码
     *
     * @return int|null
     */
    public function getPreviousPage(): ?int
    {
        return $this->previousPage;
    }

    /**
     * 获取下一页页码
     *
     * @return int|null
     */
    public function getNextPage(): ?int
    {
        return $this->nextPage;
    }

    /**
     * 获取起始记录数
     *
     * @return int
     */
    public function getFrom(): int
    {
        return $this->from;
    }

    /**
     * 获取结束记录数
     *
     * @return int
     */
    public function getTo(): int
    {
        return $this->to;
    }

    /**
     * 获取偏移量
     *
     * @return int
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * 获取分页链接
     *
     * @return array
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * 设置分页链接
     *
     * @param array $links
     * @return self
     */
    public function setLinks(array $links): self
    {
        $this->links = $links;
        return $this;
    }

    /**
     * 添加分页链接
     *
     * @param string $rel
     * @param string $url
     * @return self
     */
    public function addLink(string $rel, string $url): self
    {
        $this->links[$rel] = $url;
        return $this;
    }

    /**
     * 获取分页摘要
     *
     * @return array
     */
    public function getSummary(): array
    {
        return $this->summary;
    }

    /**
     * 检查是否为有效页码
     *
     * @param int $page
     * @return bool
     */
    public function isValidPage(int $page): bool
    {
        return $page >= 1 && $page <= $this->totalPages;
    }

    /**
     * 检查是否有数据
     *
     * @return bool
     */
    public function hasItems(): bool
    {
        return $this->totalItems > 0;
    }

    /**
     * 检查是否为第一页
     *
     * @return bool
     */
    public function isFirstPage(): bool
    {
        return $this->currentPage === 1;
    }

    /**
     * 检查是否为最后一页
     *
     * @return bool
     */
    public function isLastPage(): bool
    {
        return $this->currentPage === $this->totalPages || $this->totalPages === 0;
    }

    /**
     * 获取页码范围（用于显示页码按钮）
     *
     * @param int $range 显示范围
     * @return array
     */
    public function getPageRange(int $range = 5): array
    {
        $pages = [];

        if ($this->totalPages === 0) {
            return $pages;
        }

        $start = max(1, $this->currentPage - $range);
        $end = min($this->totalPages, $this->currentPage + $range);

        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }

        return $pages;
    }

    /**
     * 从总数和当前参数创建分页对象
     *
     * @param int $totalItems 总记录数
     * @param int $currentPage 当前页码
     * @param int $perPage 每页记录数
     * @return static
     */
    public static function fromTotal(int $totalItems, int $currentPage = 1, int $perPage = 20): static
    {
        return new static($currentPage, $perPage, $totalItems);
    }

    /**
     * 创建空分页对象
     *
     * @param int $currentPage 当前页码
     * @param int $perPage 每页记录数
     * @return static
     */
    public static function empty(int $currentPage = 1, int $perPage = 20): static
    {
        return new static($currentPage, $perPage, 0);
    }

    /**
     * 转换为数组
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'currentPage' => $this->currentPage,
            'perPage' => $this->perPage,
            'totalItems' => $this->totalItems,
            'totalPages' => $this->totalPages,
            'hasPreviousPage' => $this->hasPreviousPage,
            'hasNextPage' => $this->hasNextPage,
            'previousPage' => $this->previousPage,
            'nextPage' => $this->nextPage,
            'from' => $this->from,
            'to' => $this->to,
            'offset' => $this->offset,
            'links' => $this->links,
            'summary' => $this->summary,
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
        $currentPage = $data['currentPage'] ?? 1;
        $perPage = $data['perPage'] ?? 20;
        $totalItems = $data['totalItems'] ?? 0;

        $instance = new static($currentPage, $perPage, $totalItems);

        if (isset($data['links'])) {
            $instance->setLinks($data['links']);
        }

        return $instance;
    }

    /**
     * 获取简化分页信息（用于API响应）
     *
     * @return array
     */
    public function toSimpleArray(): array
    {
        return [
            'current_page' => $this->currentPage,
            'per_page' => $this->perPage,
            'total' => $this->totalItems,
            'last_page' => $this->totalPages,
            'from' => $this->from,
            'to' => $this->to,
        ];
    }

    /**
     * 获取Laravel风格分页数据
     *
     * @return array
     */
    public function toLaravelStyle(): array
    {
        return [
            'current_page' => $this->currentPage,
            'data' => [], // 需要外部填充数据
            'first_page_url' => $this->links['first'] ?? null,
            'from' => $this->from,
            'last_page' => $this->totalPages,
            'last_page_url' => $this->links['last'] ?? null,
            'next_page_url' => $this->hasNextPage ? ($this->links['next'] ?? null) : null,
            'path' => $this->links['self'] ?? null,
            'per_page' => $this->perPage,
            'prev_page_url' => $this->hasPreviousPage ? ($this->links['prev'] ?? null) : null,
            'to' => $this->to,
            'total' => $this->totalItems,
        ];
    }

    /**
     * 克隆分页对象并修改页码
     *
     * @param int $newPage 新页码
     * @return static
     */
    public function withPage(int $newPage): static
    {
        $clone = clone $this;
        $clone->setCurrentPage($newPage);
        return $clone;
    }

    /**
     * 克隆分页对象并修改每页数量
     *
     * @param int $newPerPage 新的每页数量
     * @return static
     */
    public function withPerPage(int $newPerPage): static
    {
        $clone = clone $this;
        $clone->setPerPage($newPerPage);
        return $clone;
    }
}
