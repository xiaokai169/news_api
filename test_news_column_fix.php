<?php

echo "=== 测试 NewsColumn 修复结果 ===\n\n";

// 1. 验证 Entity 映射
echo "1. 验证 Entity 映射:\n";
echo str_repeat("-", 40) . "\n";

$entityFile = __DIR__ . '/src/Entity/SysNewsArticle.php';
if (file_exists($entityFile)) {
    $content = file_get_contents($entityFile);

    // 检查 update_at 映射
    if (preg_match('/#\[\s*ORM\Column\s*\([^)]*name:\s*[\'"]update_at[\'"][^)]*\)\s*\]/', $content)) {
        echo "✓ Entity 中正确映射了 'update_at' 列\n";
    } else {
        echo "✗ Entity 中未找到正确的 'update_at' 映射\n";
    }

    // 检查是否还有 updated_at 映射
    if (preg_match('/#\[\s*ORM\Column\s*\([^)]*name:\s*[\'"]updated_at[\'"][^)]*\)\s*\]/', $content)) {
        echo "⚠️  Entity 中仍存在 'updated_at' 映射，可能导致冲突\n";
    } else {
        echo "✓ Entity 中已清除 'updated_at' 映射\n";
    }
} else {
    echo "✗ 找不到 Entity 文件\n";
}

echo "\n2. 模拟查询测试:\n";
echo str_repeat("-", 40) . "\n";

// 模拟 NewsFilterDto 的查询构建
echo "模拟 NewsFilterDto->buildQueryBuilder() 过程:\n";

// 检查 NewsFilterDto 的默认排序
$filterDtoFile = __DIR__ . '/src/DTO/Filter/NewsFilterDto.php';
if (file_exists($filterDtoFile)) {
    $filterContent = file_get_contents($filterDtoFile);

    if (preg_match('/protected\s+\$sortBy\s*=\s*[\'"]([^\'"]+)[\'"]/', $filterContent, $matches)) {
        echo "默认排序字段: " . $matches[1] . "\n";

        // 检查是否可能触发 update_at 引用
        if (in_array($matches[1], ['updateTime', 'updatedTime', 'updated_at', 'update_at'])) {
            echo "⚠️  默认排序可能引用时间字段\n";
        } else {
            echo "✓ 默认排序字段不会触发时间字段查询\n";
        }
    }
}

echo "\n3. Repository 方法检查:\n";
echo str_repeat("-", 40) . "\n";

$repositoryFile = __DIR__ . '/src/Repository/SysNewsArticleRepository.php';
if (file_exists($repositoryFile)) {
    $repoContent = file_get_contents($repositoryFile);

    // 检查 findByFilterDto 方法
    if (preg_match('/public\s+function\s+findByFilterDto\s*\([^)]*\)\s*{([^}]*)}/', $repoContent, $matches)) {
        $methodBody = $matches[1];
        if (strpos($methodBody, 'buildQueryBuilder') !== false) {
            echo "✓ findByFilterDto 使用 buildQueryBuilder\n";
        } else {
            echo "⚠️  findByFilterDto 可能有自己的查询逻辑\n";
        }
    }

    // 检查是否有硬编码的列名
    if (strpos($repoContent, 'updated_at') !== false) {
        echo "⚠️  Repository 中发现 'updated_at' 引用\n";
    } else {
        echo "✓ Repository 中没有硬编码的 'updated_at' 引用\n";
    }
}

echo "\n4. 修复建议:\n";
echo str_repeat("-", 40) . "\n";

echo "✅ 已完成的修复:\n";
echo "  - 将 Entity 中的 'updated_at' 映射改为 'update_at'\n";
echo "  - 保持属性名 'updatedTime' 不变，只修改列名映射\n\n";

echo "🔄 后续步骤:\n";
echo "  1. 清理 Doctrine 缓存:\n";
echo "     php bin/console doctrine:cache:clear-metadata\n";
echo "     php bin/console doctrine:cache:clear-query\n";
echo "  2. 验证架构:\n";
echo "     php bin/console doctrine:schema:validate\n";
echo "  3. 测试 API:\n";
echo "     调用 GET /api/news/list 端点\n\n";

echo "⚠️  注意事项:\n";
echo "  - 如果应用已部署，需要重启应用服务器\n";
echo "  - 如果有其他 Entity 也有类似问题，需要同样修复\n";
echo "  - 建议在测试环境先验证修复效果\n";

echo "\n=== 测试完成 ===\n";
