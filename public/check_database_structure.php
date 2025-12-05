<?php

/**
 * 检查数据库表结构
 */

echo "=== 检查数据库表结构 ===\n\n";

try {
    // 使用Symfony的数据库连接
    require_once __DIR__ . '/../vendor/autoload.php';

    // 创建简单的PDO连接
    $env = parse_ini_file(__DIR__ . '/../.env');
    $databaseUrl = $env['DATABASE_URL'] ?? '';

    // 解析DATABASE_URL
    $parsed = parse_url($databaseUrl);
    $host = $parsed['host'] ?? 'localhost';
    $port = $parsed['port'] ?? '3306';
    $dbname = ltrim($parsed['path'] ?? 'official_website', '/');
    $user = $parsed['user'] ?? 'root';
    $pass = $parsed['pass'] ?? '';

    echo "数据库连接信息:\n";
    echo "主机: {$host}\n";
    echo "端口: {$port}\n";
    echo "数据库: {$dbname}\n";
    echo "用户: {$user}\n\n";

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    echo "✅ 数据库连接成功\n\n";

    // 检查distributed_locks表是否存在
    echo "1. 检查distributed_locks表是否存在...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'distributed_locks'");
    $tableExists = $stmt->rowCount() > 0;

    if ($tableExists) {
        echo "   ✅ distributed_locks表存在\n\n";

        // 检查表结构
        echo "2. 检查distributed_locks表结构...\n";
        $stmt = $pdo->query("DESCRIBE distributed_locks");
        $columns = $stmt->fetchAll();

        echo "   表字段:\n";
        foreach ($columns as $column) {
            echo "   - {$column['Field']}: {$column['Type']} ({$column['Null']}, {$column['Key']})\n";
        }
        echo "\n";

        // 检查是否有lock_id字段
        $hasLockId = false;
        $hasLockKey = false;
        $hasExpireTime = false;
        $hasCreatedAt = false;

        foreach ($columns as $column) {
            switch ($column['Field']) {
                case 'lock_id':
                    $hasLockId = true;
                    break;
                case 'lock_key':
                    $hasLockKey = true;
                    break;
                case 'expire_time':
                    $hasExpireTime = true;
                    break;
                case 'created_at':
                    $hasCreatedAt = true;
                    break;
            }
        }

        echo "3. 检查必需字段...\n";
        echo "   lock_key字段: " . ($hasLockKey ? '✅' : '❌') . "\n";
        echo "   lock_id字段: " . ($hasLockId ? '✅' : '❌') . "\n";
        echo "   expire_time字段: " . ($hasExpireTime ? '✅' : '❌') . "\n";
        echo "   created_at字段: " . ($hasCreatedAt ? '✅' : '❌') . "\n";
        echo "\n";

        if (!$hasLockId) {
            echo "❌ 缺少lock_id字段，需要更新表结构\n";

            // 尝试添加lock_id字段
            echo "4. 尝试添加lock_id字段...\n";
            try {
                $pdo->exec("ALTER TABLE distributed_locks ADD COLUMN `lock_id` varchar(255) NOT NULL AFTER `lock_key`");
                echo "   ✅ lock_id字段添加成功\n";
            } catch (PDOException $e) {
                echo "   ❌ 添加lock_id字段失败: " . $e->getMessage() . "\n";
            }
        }

        // 检查official表
        echo "\n5. 检查official表...\n";
        $stmt = $pdo->query("SHOW TABLES LIKE 'official'");
        $officialExists = $stmt->rowCount() > 0;

        if ($officialExists) {
            echo "   ✅ official表存在\n";

            // 检查记录数
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM official");
            $count = $stmt->fetch()['count'];
            echo "   记录数: {$count}\n";
        } else {
            echo "   ❌ official表不存在\n";
        }

        // 检查wechat_public_account表
        echo "\n6. 检查wechat_public_account表...\n";
        $stmt = $pdo->query("SHOW TABLES LIKE 'wechat_public_account'");
        $wechatExists = $stmt->rowCount() > 0;

        if ($wechatExists) {
            echo "   ✅ wechat_public_account表存在\n";

            // 检查记录数
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM wechat_public_account");
            $count = $stmt->fetch()['count'];
            echo "   记录数: {$count}\n";
        } else {
            echo "   ❌ wechat_public_account表不存在\n";
        }

        // 测试分布式锁功能
        echo "\n7. 测试分布式锁功能...\n";
        testDistributedLock($pdo);

    } else {
        echo "   ❌ distributed_locks表不存在\n";

        // 创建表
        echo "3. 创建distributed_locks表...\n";
        $createSql = file_get_contents(__DIR__ . '/../create_distributed_locks_table.sql');
        try {
            $pdo->exec($createSql);
            echo "   ✅ distributed_locks表创建成功\n";
        } catch (PDOException $e) {
            echo "   ❌ 创建表失败: " . $e->getMessage() . "\n";
        }
    }

} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    echo "请检查数据库配置和连接\n";
}

echo "\n=== 检查完成 ===\n";

/**
 * 测试分布式锁功能
 */
function testDistributedLock(PDO $pdo): void
{
    $testKey = 'test_lock_' . time();
    $testId = md5($testKey);
    $expireTime = date('Y-m-d H:i:s', time() + 60);

    try {
        echo "   测试锁获取...\n";

        // 插入测试锁
        $sql = "INSERT INTO distributed_locks (lock_key, lock_id, expire_time, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$testKey, $testId, $expireTime]);

        if ($result) {
            echo "   ✅ 锁插入成功\n";

            // 检查锁是否存在
            $sql = "SELECT lock_id, expire_time FROM distributed_locks WHERE lock_key = ? AND expire_time > NOW()";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$testKey]);
            $lock = $stmt->fetch();

            if ($lock && $lock['lock_id'] === $testId) {
                echo "   ✅ 锁验证成功\n";
            } else {
                echo "   ❌ 锁验证失败\n";
            }

            // 释放锁
            $sql = "DELETE FROM distributed_locks WHERE lock_key = ?";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$testKey]);

            if ($result) {
                echo "   ✅ 锁释放成功\n";
            } else {
                echo "   ❌ 锁释放失败\n";
            }

        } else {
            echo "   ❌ 锁插入失败\n";
        }

    } catch (PDOException $e) {
        echo "   ❌ 分布式锁测试失败: " . $e->getMessage() . "\n";
    }
}
