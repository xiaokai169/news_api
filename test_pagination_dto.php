<?php

require_once 'src/DTO/Base/AbstractFilterDto.php';
require_once 'src/DTO/Shared/PaginationDto.php';

use App\DTO\Base\AbstractFilterDto;
use App\DTO\Shared\PaginationDto;

echo "=== 测试 AbstractFilterDto 分页参数修改 ===\n";

// 测试 AbstractFilterDto
$filter = new class extends AbstractFilterDto {};

// 测试新字段名
echo "1. 测试新字段名:\n";
echo "   current: " . $filter->getCurrent() . "\n";
echo "   pageSize: " . $filter->getPageSize() . "\n";

// 测试新方法名
echo "\n2. 测试新方法名:\n";
$filter->setCurrent(2);
$filter->setPageSize(50);
echo "   setCurrent(2) -> getCurrent(): " . $filter->getCurrent() . "\n";
echo "   setPageSize(50) -> getPageSize(): " . $filter->getPageSize() . "\n";

// 测试 getOffset() 方法
echo "\n3. 测试 getOffset() 方法:\n";
echo "   current=2, pageSize=50, offset=" . $filter->getOffset() . "\n";

// 测试向后兼容方法
echo "\n4. 测试向后兼容方法:\n";
echo "   getPage(): " . $filter->getPage() . "\n";
echo "   getLimit(): " . $filter->getLimit() . "\n";

// 测试 toArray() 方法
echo "\n5. 测试 toArray() 方法:\n";
$array = $filter->toArray();
echo "   toArray() 结果: " . json_encode($array, JSON_PRETTY_PRINT) . "\n";

// 测试 fromArray() 方法
echo "\n6. 测试 fromArray() 方法 (向后兼容):\n";
$newFilter = new class extends AbstractFilterDto {};
$newFilter->fromArray(['page' => 3, 'limit' => 25]);
echo "   fromArray(['page' => 3, 'limit' -> 25]) 结果:\n";
echo "   current: " . $newFilter->getCurrent() . "\n";
echo "   pageSize: " . $newFilter->getPageSize() . "\n";

$newFilter2 = new class extends AbstractFilterDto {};
$newFilter2->fromArray(['current' => 4, 'pageSize' => 30]);
echo "   fromArray(['current' => 4, 'pageSize' -> 30]) 结果:\n";
echo "   current: " . $newFilter2->getCurrent() . "\n";
echo "   pageSize: " . $newFilter2->getPageSize() . "\n";

echo "\n=== 测试 PaginationDto 分页参数修改 ===\n";

// 测试 PaginationDto
$pagination = new PaginationDto(1, 20, 100);

echo "1. 测试新字段名:\n";
echo "   current: " . $pagination->getCurrent() . "\n";
echo "   pageSize: " . $pagination->getPageSize() . "\n";
echo "   totalItems: " . $pagination->getTotalItems() . "\n";
echo "   totalPages: " . $pagination->getTotalPages() . "\n";

// 测试向后兼容方法
echo "\n2. 测试向后兼容方法:\n";
echo "   getCurrentPage(): " . $pagination->getCurrentPage() . "\n";
echo "   getPerPage(): " . $pagination->getPerPage() . "\n";

// 测试 toArray() 方法
echo "\n3. 测试 toArray() 方法:\n";
$array = $pagination->toArray();
echo "   主要字段:\n";
echo "   current: " . $array['current'] . "\n";
echo "   pageSize: " . $array['pageSize'] . "\n";
echo "   向后兼容字段:\n";
echo "   currentPage: " . $array['currentPage'] . "\n";
echo "   perPage: " . $array['perPage'] . "\n";

// 测试 fromArray() 方法
echo "\n4. 测试 fromArray() 方法 (向后兼容):\n";
$newPagination = PaginationDto::fromArray(['currentPage' => 2, 'perPage' => 50, 'totalItems' => 200]);
echo "   fromArray(['currentPage' => 2, 'perPage' -> 50, 'totalItems' -> 200]) 结果:\n";
echo "   current: " . $newPagination->getCurrent() . "\n";
echo "   pageSize: " . $newPagination->getPageSize() . "\n";

$newPagination2 = PaginationDto::fromArray(['current' => 3, 'pageSize' => 25, 'totalItems' => 75]);
echo "   fromArray(['current' => 3, 'pageSize' -> 25, 'totalItems' -> 75]) 结果:\n";
echo "   current: " . $newPagination2->getCurrent() . "\n";
echo "   pageSize: " . $newPagination2->getPageSize() . "\n";

// 测试静态方法
echo "\n5. 测试静态方法:\n";
$fromTotal = PaginationDto::fromTotal(150, 2, 30);
echo "   fromTotal(150, 2, 30) -> current: " . $fromTotal->getCurrent() . ", pageSize: " . $fromTotal->getPageSize() . "\n";

$empty = PaginationDto::empty(1, 10);
echo "   empty(1, 10) -> current: " . $empty->getCurrent() . ", pageSize: " . $empty->getPageSize() . "\n";

echo "\n=== 所有测试完成 ===\n";
