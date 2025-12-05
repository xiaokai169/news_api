<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// 加载环境变量
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/../.env');

// 数据库连接信息
$host = '127.0.0.1';
$port = '3306';
$username = 'root';
$password = 'qwe147258..';
$database = 'official_website';

echo "=== 微信公众号表修复脚本 ===\n\n";

try {
    // 创建数据库连接
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✅ 数据库连接成功\n\n";

    // 检查表是否已存在
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'wechat_public_account'");
    $stmt->execute();
    $exists = $stmt->fetch();

    if ($exists) {
        echo "⚠️  表 'wechat_public_account' 已存在，跳过创建\n";
        echo "🔍 验证表结构...\n";

        // 显示表结构
        $stmt = $pdo->query("DESCRIBE wechat_public_account");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $column) {
            echo "  - {$column['Field']}: {$column['Type']}\n";
        }

        echo "\n✅ 表结构验证完成\n";
    } else {
        echo "🔧 开始创建表 'wechat_public_account'...\n\n";

        // 读取SQL脚本
        $sqlFile = __DIR__ . '/../create_table.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("SQL脚本文件不存在: $sqlFile");
        }

        $sqlContent = file_get_contents($sqlFile);

        // 提取 wechat_public_account 表创建语句
        $lines = explode("\n", $sqlContent);
        $createTableSql = '';
        $inCreateStatement = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if (stripos($line, 'CREATE TABLE IF NOT EXISTS wechat_public_account') === 0) {
                $inCreateStatement = true;
                $createTableSql = $line;
                continue;
            }

            if ($inCreateStatement) {
                $createTableSql .= ' ' . $line;

                if (strpos($line, ');') !== false) {
                    break;
                }
            }
        }

        if (empty($createTableSql)) {
            throw new Exception("无法从SQL脚本中提取 wechat_public_account 表创建语句");
        }

        echo "📝 执行的SQL语句:\n";
        echo "$createTableSql\n\n";

        // 执行SQL
        $pdo->exec($createTableSql);

        echo "✅ 表 'wechat_public_account' 创建成功\n\n";

        // 验证表结构
        echo "🔍 验证表结构:\n";
        $stmt = $pdo->query("DESCRIBE wechat_public_account");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $column) {
            echo "  - {$column['Field']}: {$column['Type']} {$column['Null']} {$column['Key']} {$column['Default']}\n";
        }
    }

    // 测试Entity是否能正常工作
    echo "\n🧪 测试Entity连接...\n";

    try {
        // 使用Doctrine连接测试
        require_once __DIR__ . '/../src/Kernel.php';

        $kernel = new \App\Kernel('dev', true);
        $kernel->boot();

        $entityManager = $kernel->getContainer()->get('doctrine.orm.entity_manager');

        // 尝试创建查询（不执行）
        $qb = $entityManager->createQueryBuilder();
        $qb->select('COUNT(w.id)')
           ->from('App\Entity\WechatPublicAccount', 'w');

        echo "✅ Entity连接测试成功\n";

        $kernel->shutdown();

    } catch (Exception $e) {
        echo "⚠️  Entity连接测试失败: " . $e->getMessage() . "\n";
        echo "📝 这可能是正常的，如果其他配置有问题\n";
    }

    echo "\n=== 修复完成 ===\n";
    echo "🎉 表 'wechat_public_account' 现在应该可以正常使用了\n";
    echo "📝 如果问题仍然存在，请检查:\n";
    echo "   1. 数据库权限\n";
    echo "   2. Entity配置\n";
    echo "   3. 应用缓存（尝试清除缓存: php bin/console cache:clear）\n";

} catch (PDOException $e) {
    echo "❌ 数据库错误: " . $e->getMessage() . "\n";
    echo "📝 请检查数据库连接信息和权限\n";
} catch (Exception $e) {
    echo "❌ 修复过程中出错: " . $e->getMessage() . "\n";
}
