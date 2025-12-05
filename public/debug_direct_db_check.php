<?php

// 直接使用数据库连接，不通过Symfony容器
try {
    // 从.env文件解析数据库连接信息
    $envFile = __DIR__ . '/../.env';
    $envContent = file_get_contents($envFile);

    // 提取DATABASE_URL
    preg_match('/DATABASE_URL="([^"]+)"/', $envContent, $matches);
    if (!isset($matches[1])) {
        throw new Exception('无法找到DATABASE_URL');
    }

    $databaseUrl = $matches[1];
    // 解析 MySQL URL: mysql://user:pass@host:port/dbname?serverVersion=8.0&charset=utf8
    $parsed = parse_url($databaseUrl);

    $host = $parsed['host'];
    $port = $parsed['port'] ?? 3306;
    $user = $parsed['user'];
    $pass = $parsed['pass'];
    $dbname = substr($parsed['path'], 1); // 去掉开头的 /

    // 创建PDO连接
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "=== 数据库连接成功 ===\n";
    echo "主机: $host:$port\n";
    echo "数据库: $dbname\n\n";

    // 检查distributed_locks表是否存在
    $tableCheckSql = "SHOW TABLES LIKE 'distributed_locks'";
    $stmt = $pdo->query($tableCheckSql);
    $tableExists = $stmt->rowCount() > 0;

    if (!$tableExists) {
        echo "distributed_locks 表不存在\n";
        exit;
    }

    echo "=== 检查 distributed_locks 表状态 ===\n";
    $sql = 'SELECT * FROM distributed_locks ORDER BY created_at DESC';
    $stmt = $pdo->query($sql);
    $locks = $stmt->fetchAll();

    if (empty($locks)) {
        echo "没有找到任何锁记录\n";
    } else {
        echo "找到 " . count($locks) . " 个锁记录:\n\n";
        foreach ($locks as $lock) {
            echo "Lock Key: " . $lock['lock_key'] . "\n";
            echo "Lock ID: " . $lock['lock_id'] . "\n";
            echo "Expire Time: " . $lock['expire_time'] . "\n";
            echo "Created At: " . $lock['created_at'] . "\n";
            echo "Is Expired: " . (strtotime($lock['expire_time']) < time() ? 'YES' : 'NO') . "\n";
            echo "---\n";
        }
    }

    echo "\n=== 特别检查 wechat_sync_gh_27a426f64edbef94 锁 ===\n";
    $specificSql = 'SELECT * FROM distributed_locks WHERE lock_key = ?';
    $specificStmt = $pdo->prepare($specificSql);
    $specificStmt->execute(['wechat_sync_gh_27a426f64edbef94']);
    $specificLocks = $specificStmt->fetchAll();

    if (empty($specificLocks)) {
        echo "没有找到 wechat_sync_gh_27a426f64edbef94 锁\n";
    } else {
        foreach ($specificLocks as $lock) {
            echo "Lock Key: " . $lock['lock_key'] . "\n";
            echo "Lock ID: " . $lock['lock_id'] . "\n";
            echo "Expire Time: " . $lock['expire_time'] . "\n";
            echo "Created At: " . $lock['created_at'] . "\n";
            echo "Is Expired: " . (strtotime($lock['expire_time']) < time() ? 'YES' : 'NO') . "\n";
        }
    }

    echo "\n=== 检查所有微信相关锁 ===\n";
    $wechatSql = "SELECT * FROM distributed_locks WHERE lock_key LIKE 'wechat_%' ORDER BY created_at DESC";
    $wechatStmt = $pdo->query($wechatSql);
    $wechatLocks = $wechatStmt->fetchAll();

    if (empty($wechatLocks)) {
        echo "没有找到微信相关的锁\n";
    } else {
        echo "找到 " . count($wechatLocks) . " 个微信相关锁:\n\n";
        foreach ($wechatLocks as $lock) {
            echo "Lock Key: " . $lock['lock_key'] . "\n";
            echo "Lock ID: " . $lock['lock_id'] . "\n";
            echo "Expire Time: " . $lock['expire_time'] . "\n";
            echo "Created At: " . $lock['created_at'] . "\n";
            echo "Is Expired: " . (strtotime($lock['expire_time']) < time() ? 'YES' : 'NO') . "\n";
            echo "---\n";
        }
    }

    echo "\n=== 当前时间信息 ===\n";
    echo "当前时间戳: " . time() . "\n";
    echo "当前时间: " . date('Y-m-d H:i:s') . "\n";

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪: " . $e->getTraceAsString() . "\n";
}
