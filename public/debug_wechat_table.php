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

echo "=== 微信公众号表诊断报告 ===\n\n";

try {
    // 创建数据库连接
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✅ 数据库连接成功\n";
    echo "📊 数据库: $database\n\n";

    // 检查所有表
    echo "📋 现有表列表:\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        echo "  - $table\n";
    }

    echo "\n🔍 检查 wechat_public_account 表:\n";

    // 检查表是否存在
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'wechat_public_account'");
    $stmt->execute();
    $result = $stmt->fetch();

    if ($result) {
        echo "✅ 表 'wechat_public_account' 存在\n";

        // 显示表结构
        echo "\n📝 表结构:\n";
        $stmt = $pdo->query("DESCRIBE wechat_public_account");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $column) {
            echo "  - {$column['Field']}: {$column['Type']} {$column['Null']} {$column['Key']} {$column['Default']}\n";
        }
    } else {
        echo "❌ 表 'wechat_public_account' 不存在\n";

        // 检查是否有类似的表名
        echo "\n🔍 搜索包含 'wechat' 的表:\n";
        $stmt = $pdo->prepare("SHOW TABLES LIKE '%wechat%'");
        $stmt->execute();
        $similarTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if ($similarTables) {
            foreach ($similarTables as $table) {
                echo "  - $table\n";
            }
        } else {
            echo "  (无包含 'wechat' 的表)\n";
        }
    }

    // 检查Entity文件是否存在
    echo "\n📁 检查相关文件:\n";
    $entityFile = __DIR__ . '/../src/Entity/WechatPublicAccount.php';
    if (file_exists($entityFile)) {
        echo "✅ Entity文件存在: src/Entity/WechatPublicAccount.php\n";
    } else {
        echo "❌ Entity文件不存在: src/Entity/WechatPublicAccount.php\n";
    }

    $controllerFile = __DIR__ . '/../src/Controller/WechatPublicAccountController.php';
    if (file_exists($controllerFile)) {
        echo "✅ Controller文件存在: src/Controller/WechatPublicAccountController.php\n";
    } else {
        echo "❌ Controller文件不存在: src/Controller/WechatPublicAccountController.php\n";
    }

    $repositoryFile = __DIR__ . '/../src/Repository/WechatPublicAccountRepository.php';
    if (file_exists($repositoryFile)) {
        echo "✅ Repository文件存在: src/Repository/WechatPublicAccountRepository.php\n";
    } else {
        echo "❌ Repository文件不存在: src/Repository/WechatPublicAccountRepository.php\n";
    }

    // 检查SQL脚本
    echo "\n📄 检查SQL脚本:\n";
    $sqlFile = __DIR__ . '/../create_table.sql';
    if (file_exists($sqlFile)) {
        echo "✅ SQL脚本存在: create_table.sql\n";

        // 检查脚本内容是否包含表创建语句
        $sqlContent = file_get_contents($sqlFile);
        if (strpos($sqlContent, 'wechat_public_account') !== false) {
            echo "✅ SQL脚本包含 wechat_public_account 表创建语句\n";
        } else {
            echo "❌ SQL脚本不包含 wechat_public_account 表创建语句\n";
        }
    } else {
        echo "❌ SQL脚本不存在: create_table.sql\n";
    }

} catch (PDOException $e) {
    echo "❌ 数据库连接失败: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ 诊断过程中出错: " . $e->getMessage() . "\n";
}

echo "\n=== 诊断完成 ===\n";
