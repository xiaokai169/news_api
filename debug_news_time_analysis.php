<?php
// 专门调试新闻文章创建时间问题的脚本
$host = '127.0.0.1';
$dbname = 'official_website';
$username = 'root';
$password = 'qwe147258..';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== 新闻文章创建时间问题调试报告 ===\n\n";

    // 1. 检查表是否存在
    echo "1. 检查表是否存在:\n";
    echo str_repeat("-", 50) . "\n";

    $stmt = $pdo->query("SHOW TABLES LIKE 'sys_news_article'");
    $tableExists = $stmt->rowCount() > 0;
    echo "sys_news_article 表: " . ($tableExists ? "✓ 存在" : "✗ 不存在") . "\n";

    if (!$tableExists) {
        echo "错误: sys_news_article 表不存在，无法继续调试\n";
        exit(1);
    }

    // 2. 检查表结构
    echo "\n2. 检查表结构:\n";
    echo str_repeat("-", 50) . "\n";

    $stmt = $pdo->query("DESCRIBE sys_news_article");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $timeColumns = [];
    foreach ($columns as $column) {
        echo sprintf(
            "字段: %-20s 类型: %-25s NULL: %-8s 默认值: %s\n",
            $column['Field'],
            $column['Type'],
            $column['Null'],
            $column['Default'] ?? 'NULL'
        );

        if (strpos($column['Field'], 'time') !== false) {
            $timeColumns[] = $column;
        }
    }

    // 3. 专门分析时间字段
    echo "\n3. 时间字段详细分析:\n";
    echo str_repeat("-", 50) . "\n";

    foreach ($timeColumns as $column) {
        echo sprintf(
            "字段: %-15s 类型: %-20s NULL: %-8s 默认值: %-15s 额外: %s\n",
            $column['Field'],
            $column['Type'],
            $column['Null'],
            $column['Default'] ?? 'NULL',
            $column['Extra'] ?? ''
        );
    }

    // 4. 数据分析
    echo "\n4. 数据分析:\n";
    echo str_repeat("-", 50) . "\n";

    $stmt = $pdo->query("SELECT COUNT(*) FROM sys_news_article");
    $totalRecords = $stmt->fetchColumn();
    echo "总记录数: " . $totalRecords . "\n";

    // 检查各个时间字段的问题
    $timeFields = ['create_time', 'update_time', 'release_time', 'create_at', 'update_at'];

    foreach ($timeFields as $field) {
        try {
            // 检查字段是否存在
            $stmt = $pdo->query("SHOW COLUMNS FROM sys_news_article LIKE '$field'");
            if ($stmt->rowCount() === 0) {
                echo "$field: 字段不存在\n";
                continue;
            }

            // 检查 NULL 值
            $stmt = $pdo->query("SELECT COUNT(*) FROM sys_news_article WHERE `$field` IS NULL");
            $nullCount = $stmt->fetchColumn();

            // 检查零值
            $stmt = $pdo->query("SELECT COUNT(*) FROM sys_news_article WHERE `$field` = '0000-00-00 00:00:00'");
            $zeroCount = $stmt->fetchColumn();

            // 检查空字符串
            $stmt = $pdo->query("SELECT COUNT(*) FROM sys_news_article WHERE `$field` = ''");
            $emptyCount = $stmt->fetchColumn();

            $totalProblem = $nullCount + $zeroCount + $emptyCount;
            echo "$field: 问题记录 $totalNull 条 (NULL: $nullCount, 零值: $zeroCount, 空字符串: $emptyCount)\n";

            if ($totalProblem > 0) {
                // 查看问题样本
                $stmt = $pdo->query("
                    SELECT id, name, `$field` as value
                    FROM sys_news_article
                    WHERE `$field` IS NULL OR `$field` = '0000-00-00 00:00:00' OR `$field` = ''
                    LIMIT 2
                ");
                $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($samples as $sample) {
                    echo "  - ID: {$sample['id']}, Name: " . substr($sample['name'], 0, 30) . ", Value: " . ($sample['value'] ?? 'NULL') . "\n";
                }
            }

        } catch (Exception $e) {
            echo "$field: 检查失败 - " . $e->getMessage() . "\n";
        }
    }

    // 5. 查看最近记录
    echo "\n5. 最近创建的记录 (前5条):\n";
    echo str_repeat("-", 50) . "\n";

    $stmt = $pdo->query("
        SELECT
            id,
            name,
            create_time,
            update_time,
            release_time,
            status
        FROM sys_news_article
        ORDER BY id DESC
        LIMIT 5
    ");
    $recentRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($recentRecords as $record) {
        echo sprintf(
            "ID: %d, Name: %s\n  CreateTime: %s\n  UpdateTime: %s\n  ReleaseTime: %s\n  Status: %d\n\n",
            $record['id'],
            substr($record['name'], 0, 40),
            $record['create_time'] ?? 'NULL',
            $record['update_time'] ?? 'NULL',
            $record['release_time'] ?? 'NULL',
            $record['status']
        );
    }

    // 6. 数据创建时间分布
    echo "6. 数据创建时间分布:\n";
    echo str_repeat("-", 50) . "\n";

    $stmt = $pdo->query("
        SELECT
            DATE(create_time) as create_date,
            COUNT(*) as count
        FROM sys_news_article
        WHERE create_time IS NOT NULL
        AND create_time != '0000-00-00 00:00:00'
        GROUP BY DATE(create_time)
        ORDER BY create_date DESC
        LIMIT 10
    ");
    $timeDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($timeDistribution)) {
        foreach ($timeDistribution as $dist) {
            echo "日期: {$dist['create_date']}, 记录数: {$dist['count']}\n";
        }
    } else {
        echo "无有效的创建时间数据\n";
    }

    // 7. 问题总结
    echo "\n7. 问题总结:\n";
    echo str_repeat("-", 50) . "\n";

    // 检查主要问题
    $mainProblems = [];

    // 检查 create_time 问题
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM sys_news_article
            WHERE create_time IS NULL OR create_time = '0000-00-00 00:00:00'
        ");
        $createTimeProblemCount = $stmt->fetchColumn();
        if ($createTimeProblemCount > 0) {
            $mainProblems[] = "create_time 有 {$createTimeProblemCount} 条记录为空";
        }
    } catch (Exception $e) {
        $mainProblems[] = "create_time 检查失败";
    }

    // 检查 update_time 问题
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM sys_news_article
            WHERE update_time IS NULL OR update_time = '0000-00-00 00:00:00'
        ");
        $updateTimeProblemCount = $stmt->fetchColumn();
        if ($updateTimeProblemCount > 0) {
            $mainProblems[] = "update_time 有 {$updateTimeProblemCount} 条记录为空";
        }
    } catch (Exception $e) {
        $mainProblems[] = "update_time 检查失败";
    }

    if (empty($mainProblems)) {
        echo "✓ 未发现明显的时间字段问题\n";
    } else {
        echo "⚠ 发现以下问题:\n";
        foreach ($mainProblems as $problem) {
            echo "  - $problem\n";
        }
    }

    echo "\n=== 调试报告完成 ===\n";

} catch (PDOException $e) {
    echo "数据库连接错误: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "执行错误: " . $e->getMessage() . "\n";
    exit(1);
}
