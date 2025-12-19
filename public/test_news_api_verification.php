<?php

echo "=== 新闻API接口验证脚本 ===\n\n";

// 模拟API测试的验证逻辑
$tests = [
    'api_endpoint_exists' => false,
    'default_sorting_works' => false,
    'time_fields_in_response' => false,
    'pagination_works' => false,
    'filtering_works' => false
];

echo "1. 验证API端点配置\n";
echo "====================\n";

// 检查控制器路由配置
$controllerFile = __DIR__ . '/../src/Controller/NewsController.php';
if (file_exists($controllerFile)) {
    $controllerContent = file_get_contents($controllerFile);

    // 检查路由定义
    if (strpos($controllerContent, '#[Route(\'/official-api/news\')]') !== false) {
        echo "✓ 新闻API路由前缀配置正确\n";
        $tests['api_endpoint_exists'] = true;
    } else {
        echo "✗ 新闻API路由前缀配置缺失\n";
    }

    // 检查GET方法
    if (strpos($controllerContent, 'methods: [\'GET\']') !== false) {
        echo "✓ GET方法路由配置存在\n";
    } else {
        echo "✗ GET方法路由配置缺失\n";
    }

    // 检查POST方法
    if (strpos($controllerContent, 'methods: [\'POST\']') !== false) {
        echo "✓ POST方法路由配置存在\n";
    } else {
        echo "✗ POST方法路由配置缺失\n";
    }
} else {
    echo "✗ 控制器文件不存在\n";
}

echo "\n2. 验证默认排序逻辑\n";
echo "====================\n";

// 检查DTO默认排序
$dtoFile = __DIR__ . '/../src/DTO/Filter/NewsFilterDto.php';
if (file_exists($dtoFile)) {
    $dtoContent = file_get_contents($dtoFile);

    if (strpos($dtoContent, '$this->sortBy = \'releaseTime\';') !== false &&
        strpos($dtoContent, '$this->sortDirection = \'desc\';') !== false) {
        echo "✓ DTO默认排序配置正确：releaseTime DESC\n";
        $tests['default_sorting_works'] = true;
    } else {
        echo "✗ DTO默认排序配置不正确\n";
    }
} else {
    echo "✗ DTO文件不存在\n";
}

// 检查Repository默认排序
$repositoryFile = __DIR__ . '/../src/Repository/SysNewsArticleRepository.php';
if (file_exists($repositoryFile)) {
    $repositoryContent = file_get_contents($repositoryFile);

    if (strpos($repositoryContent, '?string $sortBy = \'releaseTime\'') !== false &&
        strpos($repositoryContent, '?string $sortOrder = \'desc\'') !== false) {
        echo "✓ Repository默认排序参数配置正确\n";
    } else {
        echo "✗ Repository默认排序参数配置不正确\n";
    }
}

echo "\n3. 验证时间字段响应\n";
echo "====================\n";

// 检查实体时间字段
$entityFile = __DIR__ . '/../src/Entity/SysNewsArticle.php';
if (file_exists($entityFile)) {
    $entityContent = file_get_contents($entityFile);

    $timeFields = ['createTime', 'updateTime', 'releaseTime'];
    $allTimeFieldsExist = true;

    foreach ($timeFields as $field) {
        if (strpos($entityContent, 'private ?\DateTimeInterface $' . $field) !== false) {
            echo "✓ {$field} 字段定义存在\n";
        } else {
            echo "✗ {$field} 字段定义缺失\n";
            $allTimeFieldsExist = false;
        }
    }

    if ($allTimeFieldsExist) {
        $tests['time_fields_in_response'] = true;
    }

    // 检查序列化组
    if (strpos($entityContent, '#[Groups([\'sysNewsArticle:read\'])') !== false) {
        echo "✓ 时间字段包含在序列化组中\n";
    } else {
        echo "✗ 时间字段未包含在序列化组中\n";
    }
}

echo "\n4. 验证分页功能\n";
echo "================\n";

if (isset($dtoContent)) {
    // 检查分页相关方法
    $paginationMethods = ['getPage', 'getLimit', 'setPage', 'setLimit'];
    $paginationWorks = true;

    foreach ($paginationMethods as $method) {
        if (strpos($dtoContent, 'function ' . $method) !== false) {
            echo "✓ {$method} 方法存在\n";
        } else {
            echo "✗ {$method} 方法缺失\n";
            $paginationWorks = false;
        }
    }

    if ($paginationWorks) {
        $tests['pagination_works'] = true;
    }
}

// 检查控制器分页逻辑
if (isset($controllerContent)) {
    if (strpos($controllerContent, 'paginated(') !== false) {
        echo "✓ 控制器使用分页响应方法\n";
    } else {
        echo "✗ 控制器未使用分页响应方法\n";
    }
}

echo "\n5. 验证过滤功能\n";
echo "================\n";

if (isset($dtoContent)) {
    // 检查过滤字段
    $filterFields = ['merchantId', 'userId', 'newsStatus', 'isRecommend', 'categoryCode', 'name'];
    $filteringWorks = true;

    foreach ($filterFields as $field) {
        if (strpos($dtoContent, 'public ?' . ucfirst($field)) !== false ||
            strpos($dtoContent, 'public $' . $field) !== false) {
            echo "✓ {$field} 过滤字段存在\n";
        } else {
            echo "✗ {$field} 过滤字段缺失\n";
            $filteringWorks = false;
        }
    }

    if ($filteringWorks) {
        $tests['filtering_works'] = true;
    }

    // 检查过滤条件构建方法
    if (strpos($dtoContent, 'buildQueryBuilder') !== false) {
        echo "✓ QueryBuilder构建方法存在\n";
    } else {
        echo "✗ QueryBuilder构建方法缺失\n";
    }
}

echo "\n=== API测试结果总结 ===\n";

$passedTests = array_sum($tests);
$totalTests = count($tests);

echo "通过测试: {$passedTests}/{$totalTests}\n\n";

foreach ($tests as $testName => $result) {
    $status = $result ? '✓ 通过' : '✗ 失败';
    $testNameZh = [
        'api_endpoint_exists' => 'API端点配置',
        'default_sorting_works' => '默认排序逻辑',
        'time_fields_in_response' => '时间字段响应',
        'pagination_works' => '分页功能',
        'filtering_works' => '过滤功能'
    ];
    echo "{$status} {$testNameZh[$testName]}\n";
}

echo "\n=== API接口验证建议 ===\n";

echo "1. 推荐的API测试用例：\n";
echo "   - GET /official-api/news (默认排序测试)\n";
echo "   - GET /official-api/news?page=1&limit=10 (分页测试)\n";
echo "   - GET /official-api/news?sortBy=releaseTime&sortOrder=desc (排序测试)\n";
echo "   - GET /official-api/news?status=1 (状态过滤测试)\n";
echo "   - GET /official-api/news?categoryCode=tech (分类过滤测试)\n";
echo "   - POST /official-api/news (创建文章测试)\n";

echo "\n2. 预期的响应格式：\n";
echo "   - 时间字段应包含：createTime, updateTime, releaseTime\n";
echo "   - 默认排序：按releaseTime降序排列\n";
echo "   - 分页信息：total, page, limit, pages\n";
echo "   - 数据格式：JSON，使用UTF-8编码\n";

echo "\n3. 错误处理测试：\n";
echo "   - 无效的排序字段\n";
echo "   - 无效的分页参数\n";
echo "   - 不存在的资源ID\n";
echo "   - 权限验证失败\n";

echo "\n=== 验证完成 ===\n";

if ($passedTests === $totalTests) {
    echo "🎉 所有API配置验证通过！新闻接口配置正确！\n";
    echo "\n建议：\n";
    echo "1. 启动Symfony服务器进行实际API测试\n";
    echo "2. 使用Postman或curl进行接口调用测试\n";
    echo "3. 验证数据库中的实际数据\n";
} else {
    echo "⚠️  部分API配置验证失败，请检查相关配置。\n";
}

echo "\n验证完成时间：" . date('Y-m-d H:i:s') . "\n";
