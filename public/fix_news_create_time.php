<?php
// 修复新闻文章创建时间问题的脚本
header('Content-Type: text/plain; charset=utf-8');

$host = '127.0.0.1';
$dbname = 'official_website';
$username = 'root';
$password = 'qwe147258..';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== 新闻文章创建时间修复脚本 ===\n\n";

    // 1. 检查当前表结构
    echo "1. 检查当前表结构:\n";
    echo str_repeat("-", 50) . "\n";

    $stmt = $pdo->query("DESCRIBE sys_news_article");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hasCreateAt = false;
    $hasCreateTime = false;
    $hasUpdateAt = false;
    $hasUpdateTime = false;

    foreach ($columns as $column) {
        echo "字段: {$column['Field']}, 类型: {$column['Type']}, NULL: {$column['Null']}, 默认值: " . ($column['Default'] ?? 'NULL') . "\n";

        if ($column['Field'] === 'create_at') $hasCreateAt = true;
        if ($column['Field'] === 'create_time') $hasCreateTime = true;
        if ($column['Field'] === 'update_at') $hasUpdateAt = true;
        if ($column['Field'] === 'update_time') $hasUpdateTime = true;
    }

    echo "\n字段存在情况:\n";
    echo "create_at: " . ($hasCreateAt ? "✓" : "✗") . "\n";
    echo "create_time: " . ($hasCreateTime ? "✓" : "✗") . "\n";
    echo "update_at: " . ($hasUpdateAt ? "✓" : "✗") . "\n";
    echo "update_time: " . ($hasUpdateTime ? "✓" : "✗") . "\n";

    // 2. 分析数据问题
    echo "\n2. 分析数据问题:\n";
    echo str_repeat("-", 50) . "\n";

    $totalRecords = $pdo->query("SELECT COUNT(*) FROM sys_news_article")->fetchColumn();
    echo "总记录数: $totalRecords\n";

    // 检查各个时间字段的问题
    $timeFields = [];
    if ($hasCreateAt) $timeFields[] = 'create_at';
    if ($hasCreateTime) $timeFields[] = 'create_time';
    if ($hasUpdateAt) $timeFields[] = 'update_at';
    if ($hasUpdateTime) $timeFields[] = 'update_time';

    foreach ($timeFields as $field) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM sys_news_article WHERE `$field` IS NULL");
        $nullCount = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM sys_news_article WHERE `$field` = '0000-00-00 00:00:00'");
        $zeroCount = $stmt->fetchColumn();

        $totalProblem = $nullCount + $zeroCount;
        echo "$field: $totalProblem 条记录有问题 (NULL: $nullCount, 零值: $zeroCount)\n";
    }

    // 3. 执行修复
    echo "\n3. 执行修复:\n";
    echo str_repeat("-", 50) . "\n";

    try {
        // 开始事务
        $pdo->beginTransaction();
        echo "开始事务...\n";

        // 情况1: 有 create_at 和 create_time，需要同步数据
        if ($hasCreateAt && $hasCreateTime) {
            echo "检测到同时存在 create_at 和 create_time 字段\n";

            // 同步 create_at 到 create_time
            $stmt = $pdo->query("
                UPDATE sys_news_article
                SET create_time = create_at
                WHERE create_time IS NULL OR create_time = '0000-00-00 00:00:00'
            ");
            $syncCreateTime = $stmt->rowCount();
            echo "已同步 $syncCreateTime 条记录的 create_time\n";

            // 同步 update_at 到 update_time
            if ($hasUpdateAt && $hasUpdateTime) {
                $stmt = $pdo->query("
                    UPDATE sys_news_article
                    SET update_time = update_at
                    WHERE update_time IS NULL OR update_time = '0000-00-00 00:00:00'
                ");
                $syncUpdateTime = $stmt->rowCount();
                echo "已同步 $syncUpdateTime 条记录的 update_time\n";
            }
        }

        // 情况2: 只有 create_at，需要修复数据
        elseif ($hasCreateAt && !$hasCreateTime) {
            echo "检测到只有 create_at 字段\n";

            $stmt = $pdo->query("
                UPDATE sys_news_article
                SET create_at = NOW()
                WHERE create_at IS NULL OR create_at = '0000-00-00 00:00:00'
            ");
            $fixedCreateAt = $stmt->rowCount();
            echo "已修复 $fixedCreateAt 条记录的 create_at\n";

            if ($hasUpdateAt) {
                $stmt = $pdo->query("
                    UPDATE sys_news_article
                    SET update_at = NOW()
                    WHERE update_at IS NULL OR update_at = '0000-00-00 00:00:00'
                ");
                $fixedUpdateAt = $stmt->rowCount();
                echo "已修复 $fixedUpdateAt 条记录的 update_at\n";
            }
        }

        // 情况3: 只有 create_time，需要修复数据
        elseif (!$hasCreateAt && $hasCreateTime) {
            echo "检测到只有 create_time 字段\n";

            $stmt = $pdo->query("
                UPDATE sys_news_article
                SET create_time = NOW()
                WHERE create_time IS NULL OR create_time = '0000-00-00 00:00:00'
            ");
            $fixedCreateTime = $stmt->rowCount();
            echo "已修复 $fixedCreateTime 条记录的 create_time\n";

            if ($hasUpdateTime) {
                $stmt = $pdo->query("
                    UPDATE sys_news_article
                    SET update_time = NOW()
                    WHERE update_time IS NULL OR update_time = '0000-00-00 00:00:00'
                ");
                $fixedUpdateTime = $stmt->rowCount();
                echo "已修复 $fixedUpdateTime 条记录的 update_time\n";
            }
        }

        // 提交事务
        $pdo->commit();
        echo "✓ 事务提交成功\n";

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "✗ 修复失败，已回滚: " . $e->getMessage() . "\n";
        throw $e;
    }

    // 4. 验证修复结果
    echo "\n4. 验证修复结果:\n";
    echo str_repeat("-", 50) . "\n";

    foreach ($timeFields as $field) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM sys_news_article WHERE `$field` IS NULL");
        $nullCount = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM sys_news_article WHERE `$field` = '0000-00-00 00:00:00'");
        $zeroCount = $stmt->fetchColumn();

        $totalProblem = $nullCount + $zeroCount;
        echo "$field: $totalProblem 条记录仍有问题 (NULL: $nullCount, 零值: $zeroCount)\n";
    }

    // 5. 查看修复后的样本数据
    echo "\n5. 修复后的样本数据:\n";
    echo str_repeat("-", 50) . "\n";

    $sampleFields = ['id', 'name'];
    if ($hasCreateAt) $sampleFields[] = 'create_at';
    if ($hasCreateTime) $sampleFields[] = 'create_time';
    if ($hasUpdateAt) $sampleFields[] = 'update_at';
    if ($hasUpdateTime) $sampleFields[] = 'update_time';
    $sampleFields[] = 'status';

    $stmt = $pdo->query("
        SELECT " . implode(', ', $sampleFields) . "
        FROM sys_news_article
        ORDER BY id DESC
        LIMIT 3
    ");
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($samples as $sample) {
        echo "ID: {$sample['id']}, Name: " . substr($sample['name'], 0, 30) . "\n";
        foreach (['create_at', 'create_time', 'update_at', 'update_time'] as $field) {
            if (isset($sample[$field])) {
                echo "  $field: " . ($sample[$field] ?? 'NULL') . "\n";
            }
        }
        echo "  Status: {$sample['status']}\n\n";
    }

    // 6. 代码修改建议
    echo "6. 代码修改建议:\n";
    echo str_repeat("-", 50) . "\n";

    if ($hasCreateAt && !$hasCreateTime) {
        echo "需要修改实体类 (src/Entity/SysNewsArticle.php):\n";
        echo "将字段映射改为:\n";
        echo "```php\n";
        echo "#[ORM\Column(name: 'create_at', type: Types::DATETIME_MUTABLE, nullable: false)]\n";
        echo "private ?\DateTimeInterface \$createTime = null;\n\n";
        echo "#[ORM\Column(name: 'update_at', type: Types::DATETIME_MUTABLE, nullable: false)]\n";
        echo "private ?\DateTimeInterface \$updateTime = null;\n";
        echo "```\n";
    } elseif (!$hasCreateAt && $hasCreateTime) {
        echo "实体映射正确，无需修改\n";
    } elseif ($hasCreateAt && $hasCreateTime) {
        echo "建议:\n";
        echo "1. 保留 create_time 和 update_time 字段\n";
        echo "2. 删除 create_at 和 update_at 字段（如果不再需要）\n";
        echo "3. 或者修改实体映射使用 create_at 和 update_at\n";
    }

    echo "\n=== 修复完成 ===\n";

} catch (PDOException $e) {
    echo "数据库连接错误: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "执行错误: " . $e->getMessage() . "\n";
    exit(1);
}
