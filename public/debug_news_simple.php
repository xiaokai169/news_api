<?php
// 简化的数据库调试脚本
try {
    // 直接使用 PDO 连接
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();

    $host = $_ENV['DATABASE_HOST'] ?? 'localhost';
    $port = $_ENV['DATABASE_PORT'] ?? '3306';
    $dbname = $_ENV['DATABASE_NAME'] ?? 'official_website';
    $user = $_ENV['DATABASE_USER'] ?? 'root';
    $password = $_ENV['DATABASE_PASSWORD'] ?? '';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "=== 新闻文章创建时间问题调试报告 ===\n\n";

    // 1. 检查表结构
    echo "1. 检查 sys_news_article 表结构:\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->query("DESCRIBE sys_news_article");
    $tableStructure = $stmt->fetchAll();

    foreach ($tableStructure as $column) {
        echo sprintf(
            "字段: %-20s 类型: %-25s NULL: %-8s 默认值: %s\n",
            $column['Field'],
            $column['Type'],
            $column['Null'],
            $column['Default'] ?? 'NULL'
        );
    }

    echo "\n";

    // 2. 检查时间字段
    echo "2. 检查时间字段定义:\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->query("
        SELECT
            COLUMN_NAME,
            COLUMN_TYPE,
            IS_NULLABLE,
            COLUMN_DEFAULT,
            EXTRA
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'sys_news_article'
        AND COLUMN_NAME LIKE '%time%'
        ORDER BY COLUMN_NAME
    ");
    $timeFields = $stmt->fetchAll();

    foreach ($timeFields as $field) {
        echo sprintf(
            "字段: %-15s 类型: %-20s NULL: %-8s 默认值: %-15s 额外: %s\n",
            $field['COLUMN_NAME'],
            $field['COLUMN_TYPE'],
            $field['IS_NULLABLE'],
            $field['COLUMN_DEFAULT'] ?? 'NULL',
            $field['EXTRA'] ?? ''
        );
    }

    echo "\n";

    // 3. 数据分析
    echo "3. 数据分析:\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->query("SELECT COUNT(*) FROM sys_news_article");
    $totalRecords = $stmt->fetchColumn();
    echo "总记录数: " . $totalRecords . "\n";

    // 检查各个时间字段的 NULL 值
    $fieldsToCheck = ['create_time', 'update_time', 'release_time', 'create_at', 'update_at'];

    foreach ($fieldsToCheck as $field) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM sys_news_article WHERE `$field` IS NULL");
            $nullCount = $stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM sys_news_article WHERE `$field` = '0000-00-00 00:00:00'");
            $zeroCount = $stmt->fetchColumn();

            $totalNull = $nullCount + $zeroCount;
            echo "$field 为 NULL 或零值: $totalNull 记录 (NULL: $nullCount, 零值: $zeroCount)\n";
        } catch (Exception $e) {
            echo "$field 字段不存在或无法查询\n";
        }
    }

    echo "\n";

    // 4. 查看问题记录
    echo "4. 有问题记录样本 (create_time IS NULL 或零值):\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->query("
        SELECT
            id,
            name,
            create_time,
            update_time,
            release_time,
            status
        FROM sys_news_article
        WHERE create_time IS NULL OR create_time = '0000-00-00 00:00:00'
        LIMIT 3
    ");
    $problemRecords = $stmt->fetchAll();

    if (empty($problemRecords)) {
        echo "未发现 create_time 为 NULL 或零值的记录\n";
    } else {
        foreach ($problemRecords as $record) {
            echo sprintf(
                "ID: %d, Name: %s, CreateTime: %s, UpdateTime: %s, ReleaseTime: %s, Status: %d\n",
                $record['id'],
                substr($record['name'], 0, 30),
                $record['create_time'] ?? 'NULL',
                $record['update_time'] ?? 'NULL',
                $record['release_time'] ?? 'NULL',
                $record['status']
            );
        }
    }

    echo "\n";

    // 5. 最近的记录
    echo "5. 最近创建的记录:\n";
    echo str_repeat("-", 60) . "\n";

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
    $recentRecords = $stmt->fetchAll();

    foreach ($recentRecords as $record) {
        echo sprintf(
            "ID: %d, Name: %s, CreateTime: %s, UpdateTime: %s, ReleaseTime: %s, Status: %d\n",
            $record['id'],
            substr($record['name'], 0, 30),
            $record['create_time'] ?? 'NULL',
            $record['update_time'] ?? 'NULL',
            $record['release_time'] ?? 'NULL',
            $record['status']
        );
    }

    echo "\n";

    // 6. 数据创建时间分布
    echo "6. 数据创建时间分布:\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->query("
        SELECT
            DATE(create_time) as create_date,
            COUNT(*) as count
        FROM sys_news_article
        WHERE create_time IS NOT NULL AND create_time != '0000-00-00 00:00:00'
        GROUP BY DATE(create_time)
        ORDER BY create_date DESC
        LIMIT 10
    ");
    $timeDistribution = $stmt->fetchAll();

    if (!empty($timeDistribution)) {
        foreach ($timeDistribution as $dist) {
            echo "日期: {$dist['create_date']}, 记录数: {$dist['count']}\n";
        }
    } else {
        echo "无有效的创建时间数据\n";
    }

    echo "\n=== 调试报告完成 ===\n";

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪: " . $e->getTraceAsString() . "\n";
}
