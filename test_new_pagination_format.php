<?php

require_once 'src/DTO/Base/AbstractFilterDto.php';
require_once 'src/DTO/Shared/PaginationDto.php';
require_once 'src/DTO/Filter/WechatAccountFilterDto.php';
require_once 'src/DTO/Filter/ArticleReadFilterDto.php';
require_once 'src/DTO/Filter/NewsFilterDto.php';

use App\DTO\Base\AbstractFilterDto;
use App\DTO\Shared\PaginationDto;
use App\DTO\Filter\WechatAccountFilterDto;
use App\DTO\Filter\ArticleReadFilterDto;
use App\DTO\Filter\NewsFilterDto;

echo "=== 测试新的分页参数格式 (page/size) ===\n\n";

// 测试1: AbstractFilterDto 新字段名
echo "1. 测试 AbstractFilterDto 新字段名:\n";
$filter = new class extends AbstractFilterDto {};

// 测试新方法
$filter->setPage(2);
$filter->setSize(50);
echo "   setPage(2) -> getPage(): " . $filter->getPage() . "\n";
echo "   setSize(50) -> getSize(): " . $filter->getSize() . "\n";

// 测试向后兼容方法
echo "   向后兼容 - getCurrent(): " . $filter->getCurrent() . "\n";
echo "   向后兼容 - getPageSize(): " . $filter->getPageSize() . "\n";

// 测试 getOffset() 方法
echo "   getOffset(): " . $filter->getOffset() . "\n\n";

// 测试2: PaginationDto 新字段名
echo "2. 测试 PaginationDto 新字段名:\n";
$pagination = new PaginationDto(1, 20, 100);
echo "   getPage(): " . $pagination->getPage() . "\n";
echo "   getSize(): " . $pagination->getSize() . "\n";
echo "   getTotalItems(): " . $pagination->getTotalItems() . "\n";
echo "   getTotalPages(): " . $pagination->getTotalPages() . "\n";

// 测试向后兼容方法
echo "   向后兼容 - getCurrent(): " . $pagination->getCurrent() . "\n";
echo "   向后兼容 - getPageSize(): " . $pagination->getPageSize() . "\n\n";

// 测试3: 从数组创建（新参数格式）
echo "3. 测试从数组创建（新参数格式）:\n";
$newFilter = new class extends AbstractFilterDto {};
$newFilter->fromArray(['page' => 3, 'size' => 25]);
echo "   fromArray(['page' => 3, 'size' => 25]) 结果:\n";
echo "   page: " . $newFilter->getPage() . "\n";
echo "   size: " . $newFilter->getSize() . "\n\n";

// 测试4: 从数组创建（旧参数格式 - 向后兼容）
echo "4. 测试从数组创建（旧参数格式 - 向后兼容）:\n";
$oldFilter = new class extends AbstractFilterDto {};
$oldFilter->fromArray(['current' => 4, 'pageSize' => 30]);
echo "   fromArray(['current' => 4, 'pageSize' => 30]) 结果:\n";
echo "   page: " . $oldFilter->getPage() . "\n";
echo "   size: " . $oldFilter->getSize() . "\n\n";

// 测试5: WechatAccountFilterDto 测试
echo "5. 测试 WechatAccountFilterDto:\n";
$wechatFilter = new WechatAccountFilterDto(['page' => 2, 'size' => 15, 'name' => 'test']);
echo "   构造函数参数 ['page' => 2, 'size' => 15, 'name' => 'test']:\n";
echo "   page: " . $wechatFilter->getPage() . "\n";
echo "   size: " . $wechatFilter->getSize() . "\n";
echo "   name: " . $wechatFilter->name . "\n\n";

// 测试6: ArticleReadFilterDto 测试
echo "6. 测试 ArticleReadFilterDto:\n";
$readFilter = new ArticleReadFilterDto();
$readFilter->fromArray(['page' => 3, 'size' => 20, 'articleId' => 123]);
echo "   fromArray(['page' => 3, 'size' => 20, 'articleId' => 123]) 结果:\n";
echo "   page: " . $readFilter->getPage() . "\n";
echo "   size: " . $readFilter->getSize() . "\n";
echo "   articleId: " . $readFilter->articleId . "\n\n";

// 测试7: NewsFilterDto 测试
echo "7. 测试 NewsFilterDto:\n";
$newsFilter = new NewsFilterDto();
$newsFilter->fromArray(['page' => 1, 'size' => 10, 'name' => '新闻']);
echo "   fromArray(['page' => 1, 'size' => 10, 'name' => '新闻']) 结果:\n";
echo "   page: " . $newsFilter->getPage() . "\n";
echo "   size: " . $newsFilter->getSize() . "\n";
echo "   name: " . $newsFilter->name . "\n\n";

// 测试8: toArray() 方法输出格式
echo "8. 测试 toArray() 方法输出格式:\n";
$filterArray = $filter->toArray();
echo "   AbstractFilterDto::toArray() 主要字段:\n";
echo "   page: " . $filterArray['page'] . "\n";
echo "   size: " . $filterArray['size'] . "\n";
echo "   向后兼容字段:\n";
echo "   current: " . $filterArray['current'] . "\n";
echo "   pageSize: " . $filterArray['pageSize'] . "\n\n";

$paginationArray = $pagination->toArray();
echo "   PaginationDto::toArray() 主要字段:\n";
echo "   page: " . $paginationArray['page'] . "\n";
echo "   size: " . $paginationArray['size'] . "\n";
echo "   向后兼容字段:\n";
echo "   current: " . $paginationArray['current'] . "\n";
echo "   pageSize: " . $paginationArray['pageSize'] . "\n\n";

// 测试9: 静态方法测试
echo "9. 测试 PaginationDto 静态方法:\n";
$fromTotal = PaginationDto::fromTotal(150, 2, 30);
echo "   fromTotal(150, 2, 30) -> page: " . $fromTotal->getPage() . ", size: " . $fromTotal->getSize() . "\n";

$empty = PaginationDto::empty(1, 10);
echo "   empty(1, 10) -> page: " . $empty->getPage() . ", size: " . $empty->getSize() . "\n\n";

echo "=== 所有测试完成 ===\n";
