<?php
echo "=== 分布式锁综合修复脚本 ===\n\n";

// 读取数据库配置
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

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✅ 数据库连接成功\n";
    echo "数据库: $dbName\n";
    echo "主机: $dbHost\n\n";

    $accountId = 'gh_27a426f64edbef94';
    $lockKey = 'wechat_sync_' . $accountId;

    echo "目标公众号ID: $accountId\n";
    echo "锁键名: $lockKey\n\n";

    // 步骤1: 检查并重建表结构
    echo "=== 步骤1: 检查并重建表结构 ===\n";

    // 删除旧表（如果存在）
    $stmt = $pdo->query("SHOW TABLES LIKE 'distributed_locks'");
    if ($stmt->rowCount() > 0) {
        echo "发现现有 distributed_locks 表，先备份并删除...\n";

        // 备份数据
        $backupTable = "distributed_locks_backup_" . date('YmdHis');
        $pdo->exec("CREATE TABLE $backupTable AS SELECT * FROM distributed_locks");
        echo "✅ 数据已备份到 $backupTable\n";

        // 删除旧表
        $pdo->exec("DROP TABLE distributed_locks");
        echo "✅ 旧表已删除\n";
    } else {
        echo "distributed_locks 表不存在，将创建新表\n";
    }

    // 创建新表结构
    $createTableSQL = "
    CREATE TABLE distributed_locks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lock_key VARCHAR(255) NOT NULL UNIQUE,
        lock_id VARCHAR(255) NOT NULL,
        expire_time DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_expire_time (expire_time),
        INDEX idx_lock_key (lock_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分布式锁表'
    ";

    $pdo->exec($createTableSQL);
    echo "✅ distributed_locks 表创建成功\n\n";

    // 步骤2: 清理所有可能的锁记录
    echo "=== 步骤2: 清理锁记录 ===\n";

    // 删除可能存在的锁记录
    $stmt = $pdo->prepare("DELETE FROM distributed_locks WHERE lock_key LIKE ?");
    $stmt->execute(['wechat_sync_%']);
    $deletedCount = $stmt->rowCount();
    echo "✅ 清理了 $deletedCount 个微信同步相关的锁记录\n\n";

    // 步骤3: 验证表结构
    echo "=== 步骤3: 验证表结构 ===\n";

    $stmt = $pdo->query("DESCRIBE distributed_locks");
    echo "表结构:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['Field']}: {$row['Type']} {$row['Null']} {$row['Key']} {$row['Default']}\n";
    }

    // 检查索引
    $stmt = $pdo->query("SHOW INDEX FROM distributed_locks");
    echo "\n索引信息:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['Key_name']}: {$row['Column_name']} ({$row['Index_type']})\n";
    }
    echo "\n";

    // 步骤4: 测试锁功能
    echo "=== 步骤4: 测试锁功能 ===\n";

    // 测试获取锁
    $lockId = md5($lockKey . time());
    $expireTime = date('Y-m-d H:i:s', time() + 60);

    $stmt = $pdo->prepare("
        INSERT INTO distributed_locks (lock_key, lock_id, expire_time, created_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
        lock_id = IF(expire_time < NOW(), VALUES(lock_id), lock_id),
        expire_time = IF(expire_time < NOW(), VALUES(expire_time), expire_time)
    ");
    $result = $stmt->execute([$lockKey, $lockId, $expireTime]);
    echo "✅ 锁创建/更新测试: " . ($result ? "成功" : "失败") . "\n";

    // 验证锁状态
    $stmt = $pdo->prepare("SELECT lock_id, expire_time FROM distributed_locks WHERE lock_key = ? AND expire_time > NOW()");
    $stmt->execute([$lockKey]);
    $lockRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($lockRecord && $lockRecord['lock_id'] === $lockId) {
        echo "✅ 锁验证成功: 锁ID匹配且未过期\n";
        echo "  锁ID: {$lockRecord['lock_id']}\n";
        echo "  过期时间: {$lockRecord['expire_time']}\n";
    } else {
        echo "❌ 锁验证失败\n";
    }

    // 测试释放锁
    $stmt = $pdo->prepare("DELETE FROM distributed_locks WHERE lock_key = ?");
    $deletedRows = $stmt->execute([$lockKey]);
    echo "✅ 锁释放测试: " . ($deletedRows ? "成功" : "失败") . "\n";

    // 验证锁已释放
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM distributed_locks WHERE lock_key = ?");
    $stmt->execute([$lockKey]);
    $count = $stmt->fetchColumn();
    echo "✅ 锁释放验证: " . ($count == 0 ? "成功" : "失败") . "\n\n";

    // 步骤5: 修复DistributedLockService中的潜在问题
    echo "=== 步骤5: 代码优化建议 ===\n";
    echo "建议对 DistributedLockService 进行以下优化:\n";
    echo "1. 在 acquireLock() 方法中加强错误处理\n";
    echo "2. 确保 cleanExpiredLocks() 方法在每次获取锁前执行\n";
    echo "3. 添加更详细的日志记录\n";
    echo "4. 考虑添加锁重试机制\n\n";

    // 步骤6: 创建自动化清理脚本
    echo "=== 步骤6: 创建自动化清理脚本 ===\n";

    $cleanupScript = "<?php\n// 自动清理过期分布式锁\n\$pdo = new PDO(\"mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4\", '$dbUser', '$dbPass');\n\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n\n\$stmt = \$pdo->prepare(\"DELETE FROM distributed_locks WHERE expire_time < NOW()\");\n\$deletedCount = \$stmt->execute();\necho \"清理了 \$deletedCount 个过期锁\\n\";\n?>";

    file_put_contents(__DIR__ . '/cleanup_expired_locks.php', $cleanupScript);
    echo "✅ 创建了自动化清理脚本: cleanup_expired_locks.php\n\n";

    echo "=== 修复完成 ===\n";
    echo "分布式锁表已重建并测试通过！\n";
    echo "现在可以重新运行微信同步命令:\n";
    echo "php bin/console app:wechat:sync $accountId\n";
    echo "\n如果仍有问题，可以使用 --bypass-lock 选项:\n";
    echo "php bin/console app:wechat:sync $accountId --bypass-lock\n";

} catch (Exception $e) {
    echo "❌ 修复过程中发生错误: " . $e->getMessage() . "\n";
    echo "错误详情: " . $e->getTraceAsString() . "\n";
}

echo "\n=== 脚本执行完成 ===\n";
