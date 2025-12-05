<?php
// 简单的分布式锁调试脚本
echo "=== 微信同步分布式锁简单调试 ===\n\n";

// 直接使用PDO连接数据库，避免Symfony依赖
try {
    // 从.env文件读取数据库配置
    $envFile = __DIR__ . '/../.env';
    $dbHost = 'localhost';
    $dbName = 'newsapi';
    $dbUser = 'root';
    $dbPass = '';

    if (file_exists($envFile)) {
        $envContent = file_get_contents($envFile);
        if (preg_match('/DATABASE_URL="mysql:\/\/([^:]+):([^@]+)@([^\/]+)\/([^"]+)"/', $envContent, $matches)) {
            $dbUser = $matches[1];
            $dbPass = $matches[2];
            $dbHost = $matches[3];
            $dbName = $matches[4];
        }
    }

    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "数据库连接: ✅ 成功\n";
    echo "数据库: $dbName\n";
    echo "主机: $dbHost\n\n";

    $accountId = 'gh_27a426f64edbef94';
    $lockKey = 'wechat_sync_' . $accountId;

    echo "公众号ID: $accountId\n";
    echo "锁键名: $lockKey\n\n";

    // 1. 检查表是否存在
    echo "1. 检查 distributed_locks 表:\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'distributed_locks'");
    $tableExists = $stmt->rowCount() > 0;

    if ($tableExists) {
        echo "✅ distributed_locks 表存在\n\n";

        // 显示表结构
        echo "表结构:\n";
        $stmt = $pdo->query("DESCRIBE distributed_locks");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  - {$row['Field']}: {$row['Type']} {$row['Null']} {$row['Key']}\n";
        }
        echo "\n";

        // 检查当前锁记录
        echo "2. 检查当前锁记录:\n";
        $stmt = $pdo->prepare("SELECT * FROM distributed_locks WHERE lock_key = ?");
        $stmt->execute([$lockKey]);
        $lockRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($lockRecord) {
            echo "🔒 找到锁记录:\n";
            echo "  ID: {$lockRecord['id']}\n";
            echo "  锁键: {$lockRecord['lock_key']}\n";
            echo "  锁ID: {$lockRecord['lock_id']}\n";
            echo "  过期时间: {$lockRecord['expire_time']}\n";
            echo "  创建时间: {$lockRecord['created_at']}\n";

            // 检查是否过期
            $now = new DateTime();
            $expireTime = new DateTime($lockRecord['expire_time']);
            $isExpired = $expireTime < $now;
            echo "  状态: " . ($isExpired ? "⚠️ 已过期" : "✅ 有效") . "\n";

            if ($isExpired) {
                echo "  🔍 问题: 锁已过期但未清理！\n";
            }
        } else {
            echo "🔓 没有找到锁记录\n";
        }
        echo "\n";

        // 检查所有锁
        echo "3. 检查所有锁记录:\n";
        $stmt = $pdo->query("SELECT lock_key, expire_time, created_at FROM distributed_locks ORDER BY created_at DESC");
        $locks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($locks) > 0) {
            echo "总锁记录数: " . count($locks) . "\n";
            $activeCount = 0;
            $expiredCount = 0;

            foreach ($locks as $lock) {
                $expireTime = new DateTime($lock['expire_time']);
                $isExpired = $expireTime < new DateTime();

                if ($isExpired) {
                    $expiredCount++;
                    echo "  ⚠️ {$lock['lock_key']} (已过期: {$lock['expire_time']})\n";
                } else {
                    $activeCount++;
                    echo "  ✅ {$lock['lock_key']} (有效至: {$lock['expire_time']})\n";
                }
            }

            echo "\n统计: $activeCount 个活跃锁, $expiredCount 个过期锁\n";

            if ($expiredCount > 0) {
                echo "\n4. 清理过期锁:\n";
                $stmt = $pdo->prepare("DELETE FROM distributed_locks WHERE expire_time < NOW()");
                $deletedCount = $stmt->execute();
                echo "✅ 清理了 $deletedCount 个过期锁\n";
            }
        } else {
            echo "✅ 没有任何锁记录\n";
        }

    } else {
        echo "❌ distributed_locks 表不存在\n";

        // 尝试创建表
        echo "\n尝试创建表:\n";
        $createTableSQL = "
        CREATE TABLE distributed_locks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lock_key VARCHAR(255) NOT NULL UNIQUE,
            lock_id VARCHAR(255) NOT NULL,
            expire_time DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_expire_time (expire_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        try {
            $pdo->exec($createTableSQL);
            echo "✅ 表创建成功\n";
        } catch (Exception $e) {
            echo "❌ 表创建失败: " . $e->getMessage() . "\n";
        }
    }

} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
}

echo "\n=== 调试完成 ===\n";
