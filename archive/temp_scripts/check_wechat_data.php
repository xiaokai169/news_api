<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// 加载环境变量
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/../.env');

echo "=== 检查微信公众号数据 ===\n\n";

try {
    // 创建数据库连接
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=official_website", 'root', 'qwe147258..');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✅ 数据库连接成功\n\n";

    // 检查表中的数据
    echo "📊 检查 wechat_public_account 表数据:\n";

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wechat_public_account");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $result['count'];

    echo "   总记录数: $count\n\n";

    if ($count > 0) {
        echo "📋 表中数据:\n";
        $stmt = $pdo->query("SELECT id, name, app_id, is_active, created_at FROM wechat_public_account ORDER BY created_at DESC");
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($accounts as $account) {
            echo "   ID: {$account['id']}\n";
            echo "   名称: {$account['name']}\n";
            echo "   AppID: {$account['app_id']}\n";
            echo "   激活状态: " . ($account['is_active'] ? '是' : '否') . "\n";
            echo "   创建时间: {$account['created_at']}\n";
            echo "   ---\n";
        }
    } else {
        echo "❌ 表中没有数据！\n";
        echo "💡 这可能是导致 '公众号ID不能为空' 错误的原因\n";
    }

    // 测试API请求示例
    echo "\n🧪 测试API请求参数:\n";

    if ($count > 0) {
        $firstAccount = $pdo->query("SELECT id FROM wechat_public_account LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $accountId = $firstAccount['id'];

        echo "✅ 可以使用的公众号ID: $accountId\n";
        echo "📝 测试请求示例:\n";
        echo "   POST /official-api/wechat/sync\n";
        echo "   Content-Type: application/json\n";
        echo "   Body: {\n";
        echo "     \"publicAccountId\": \"$accountId\",\n";
        echo "     \"syncType\": \"articles\",\n";
        echo "     \"forceSync\": false\n";
        echo "   }\n";
    } else {
        echo "❌ 没有可用的公众号ID\n";
        echo "💡 需要先创建公众号记录\n";
        echo "📝 创建示例:\n";
        echo "   POST /official-api/wechatpublicaccount\n";
        echo "   Content-Type: application/json\n";
        echo "   Body: {\n";
        echo "     \"id\": \"test_account_001\",\n";
        echo "     \"name\": \"测试公众号\",\n";
        echo "     \"appId\": \"your_app_id\",\n";
        echo "     \"appSecret\": \"your_app_secret\"\n";
        echo "   }\n";
    }

} catch (PDOException $e) {
    echo "❌ 数据库连接失败: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ 检查过程中出错: " . $e->getMessage() . "\n";
}

echo "\n=== 检查完成 ===\n";
