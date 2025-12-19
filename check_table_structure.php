<?php

// 简单的数据库连接检查
try {
    // 从 .env 文件读取配置
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $envContent = file_get_contents($envFile);
        preg_match('/DATABASE_HOST=(.+)/', $envContent, $hostMatch);
        preg_match('/DATABASE_PORT=(.+)/', $envContent, $portMatch);
        preg_match('/DATABASE_NAME=(.+)/', $envContent, $nameMatch);
        preg_match('/DATABASE_USER=(.+)/', $envContent, $userMatch);
        preg_match('/DATABASE_PASSWORD=(.+)/', $envContent, $passMatch);

        $host = trim($hostMatch[1] ?? '127.0.0.1');
        $port = trim($portMatch[1] ?? '3306');
        $dbname = trim($nameMatch[1] ?? 'official_website');
        $username = trim($userMatch[1] ?? 'root');
        $password = trim($passMatch[1] ?? '');
    } else {
        die("找不到 .env 文件\n");
    }

    // 连接数据库
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "=== 检查 sys_news_article 表结构 ===\n\n";

    // 检查表是否存在
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'sys_news_article'");
    $stmt->execute();
    $tableExists = $stmt->fetch();

    if (!$tableExists) {
        echo "❌ 表 sys_news_article 不存在\n";

        // 检查是否有类似的表
        $stmt = $pdo->prepare("SHOW TABLES LIKE '%news%'");
        $stmt->execute();
        $similarTables = $stmt->fetchAll();

        if ($similarTables) {
            echo "找到类似的表:\n";
            foreach ($similarTables as $table) {
                $tableName = array_values($table)[0];
                echo "  - $tableName\n";
            }
        }
        exit;
    }

    echo "✓ 表 sys_news_article 存在\n\n";

    // 获取表结构
    $stmt = $pdo->prepare("DESCRIBE sys_news_article");
    $stmt->execute();
    $columns = $stmt->fetchAll();

    echo "表结构:\n";
    echo str_repeat("-", 60) . "\n";
    printf("%-20s %-20s %-10s %-10s %s\n", "字段名", "类型", "允许NULL", "键", "默认值");
    echo str_repeat("-", 60) . "\n";

    $timeFields = [];
    foreach ($columns as $column) {
        printf("%-20s %-20s %-10s %-10s %s\n",
            $column['Field'],
            $column['Type'],
            $column['Null'],
            $column['Key'],
            $column['Default'] ?? 'NULL'
        );

        // 查找时间相关字段
        if (strpos(strtolower($column['Field']), 'time') !== false ||
            strpos(strtolower($column['Field']), 'create') !== false ||
            strpos(strtolower($column['Field']), 'update') !== false) {
            $timeFields[] = $column['Field'];
        }
    }

    echo "\n时间相关字段: " . implode(', ', $timeFields) . "\n\n";

    // 检查具体的列名
    echo "=== 关键列名检查 ===\n";

    $checkColumns = ['update_at', 'updated_at', 'create_at', 'created_at'];
    foreach ($checkColumns as $colName) {
        $exists = false;
        foreach ($columns as $column) {
            if ($column['Field'] === $colName) {
                $exists = true;
                break;
            }
        }
        echo sprintf("%-12s: %s\n", $colName, $exists ? '✓ 存在' : '✗ 不存在');
    }

} catch (Exception $e) {
    echo "❌ 数据库连接失败: " . $e->getMessage() . "\n";
}
