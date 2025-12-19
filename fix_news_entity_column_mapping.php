<?php

echo "=== 修复 SysNewsArticle Entity 列名映射问题 ===\n\n";

$entityFile = __DIR__ . '/src/Entity/SysNewsArticle.php';

if (!file_exists($entityFile)) {
    echo "❌ 找不到 Entity 文件: $entityFile\n";
    exit;
}

echo "📁 正在读取 Entity 文件...\n";
$content = file_get_contents($entityFile);

echo "🔍 当前 Entity 中的时间字段映射:\n";
if (preg_match_all('/#\[\s*ORM\Column\s*\([^)]*name:\s*[\'"]([^\'"]*[Tt]ime[^\'"]*)[\'"][^)]*\)\s*\]/', $content, $matches)) {
    foreach ($matches[1] as $columnName) {
        echo "  - $columnName\n";
    }
}

echo "\n🛠️  修复方案:\n";
echo "方案1: 修改 Entity 映射以匹配数据库 (推荐)\n";
echo "方案2: 修改数据库表结构以匹配 Entity\n\n";

echo "📋 选择推荐方案: 修改 Entity 映射\n\n";

// 修复 updated_at -> update_at
$updatedPattern = '/#\[\s*ORM\Column\s*\(\s*name:\s*[\'"]updated_at[\'"],\s*type:\s*Types::DATETIME_MUTABLE,\s*nullable:\s*true\s*\)\s*\]/';
$updatedReplacement = "#[ORM\Column(name: 'update_at', type: Types::DATETIME_MUTABLE, nullable: true)]";

if (preg_match($updatedPattern, $content)) {
    echo "✓ 找到 updated_at 映射，正在修复为 update_at...\n";
    $content = preg_replace($updatedPattern, $updatedReplacement, $content);
} else {
    echo "⚠️  未找到预期的 updated_at 映射模式\n";
}

// 修复 create_at -> create_at (保持一致，但检查是否需要修改)
$createPattern = '/#\[\s*ORM\Column\s*\(\s*name:\s*[\'"]create_at[\'"],\s*type:\s*Types::DATETIME_MUTABLE,\s*nullable:\s*true\s*\)\s*\]/';

if (preg_match($createPattern, $content)) {
    echo "✓ create_at 映射已正确\n";
} else {
    echo "⚠️  需要检查 create_at 映射\n";
}

echo "\n📝 修复后的时间字段映射:\n";
if (preg_match_all('/#\[\s*ORM\Column\s*\([^)]*name:\s*[\'"]([^\'"]*[Tt]ime[^\'"]*)[\'"][^)]*\)\s*\]/', $content, $matches)) {
    foreach ($matches[1] as $columnName) {
        echo "  - $columnName\n";
    }
}

// 备份原文件
$backupFile = $entityFile . '.backup.' . date('Y-m-d_H-i-s');
if (copy($entityFile, $backupFile)) {
    echo "\n💾 已创建备份: " . basename($backupFile) . "\n";
} else {
    echo "\n❌ 备份失败\n";
}

// 写入修复后的内容
if (file_put_contents($entityFile, $content)) {
    echo "✅ Entity 文件修复成功\n";
} else {
    echo "❌ Entity 文件修复失败\n";
}

echo "\n🔄 下一步操作建议:\n";
echo "1. 清理 Doctrine 缓存: php bin/console doctrine:cache:clear-metadata\n";
echo "2. 验证数据库架构: php bin/console doctrine:schema:validate\n";
echo "3. 如果需要，生成迁移: php bin/console doctrine:migrations:diff\n";
echo "4. 测试 NewsController 的 list 方法\n";

echo "\n⚠️  注意事项:\n";
echo "- 如果其他代码依赖 updatedTime 属性，可能需要相应调整\n";
echo "- 确保数据库表中的列名确实是 'update_at' 而不是 'updated_at'\n";
echo "- 建议在应用修复前先在测试环境验证\n";

echo "\n=== 修复完成 ===\n";
