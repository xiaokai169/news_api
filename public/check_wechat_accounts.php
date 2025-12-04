
<?php
/**
 * 检查微信公众账户数据
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// 加载环境变量
$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/../.env.local');

try {
    // 连接数据库
    $pdo = new PDO(
        "mysql:host=" . $_ENV["DATABASE_HOST"] .
        ";dbname=" . $_ENV["DATABASE_NAME"] .
        ";charset=utf8mb4",
        $_ENV["DATABASE_USER"],
        $_ENV["DATABASE_PASSWORD"]
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h2>微信公众账户数据检查</h2>\n";

    // 查询所有公众账户
    $stmt = $pdo->query("SELECT id, public_account_id, name, created_at FROM wechat_public_account ORDER BY id DESC LIMIT 10");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($accounts)) {
        echo "<p style='color: red;'>❌ 数据库中没有微信公众账户数据</p>\n";

        // 尝试创建测试数据
        echo "<h3>创建测试账户数据</h3>\n";
        $testAccountId = 'gh_e4b07b2a992e6669';
        $insertStmt = $pdo->prepare("
            INSERT INTO wechat_public_account (public_account_id, name, description, app_id, app_secret, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $result = $insertStmt->execute([
            $testAccountId,
            '测试公众号',
            '用于API测试的微信公众号',
            'test_app_id',
            'test_app_secret'
        ]);

        if ($result) {
            echo "<p style='color: green;'>✅ 成功创建测试账户: {$testAccountId}</p>\n";
        } else {
            echo "<p style='color: red;'>❌ 创建测试账户失败</p>\n";
        }
    } else {
        echo "<h3>现有的微信公众账户：</h3>\n";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>ID</th><th>Public Account ID</th><th>Name</th><th>Created At</th></tr>\n";

        foreach ($accounts as $account) {
            echo "<tr>\n";
            echo "<td>{$account['id']}</td>\n";
            echo "<td>{$account['public_account_id']}</td>\n";
            echo "<td>{$account['name']}</td>\n";
            echo "<td>{$account['created_at']}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";

        // 检查测试账户是否存在
        $testAccountId = 'gh_e4b07b2a992e6669';
        $exists = false;
        foreach ($accounts as $account) {
            if ($account['public_account_id'] === $testAccountId) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            echo "<h3>创建测试账户数据</h3>\n";
            $insertStmt = $pdo->prepare("
                INSERT INTO wechat_public_account (public_account_id, name, description, app_id, app_secret, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $result = $insertStmt->execute([
                $testAccountId,
                '测试公众号',
                '用于API测试的微信公众号',
                'test_app_id',
                'test_app_secret'
            ]);

            if ($result) {
                echo "<p style='color: green;'>✅ 成功创建测试账户: {$testAccountId}</p>\n";
            } else {
                echo "<p style='color: red;'>❌ 创建测试账户失败</p>\n";
            }
        } else {
            echo "<p style='color: green;'>✅ 测试账户已存在: {$testAccountId}</p>\n";
        }
    }

    // 验证数据
    echo "<h3>验证测试数据</h3>\n";
    $verifyStmt = $pdo->prepare("SELECT * FROM wechat_public_account WHERE public_account_id = ?");
    $verifyStmt->execute([$testAccountId]);
    $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);

    if ($verifyResult) {
        echo "<p style='color: green;'>✅ 测试账户验证成功</p>\n";
        echo "<pre>" . json_encode($verifyResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>\n";
    } else {
        echo "<p style='color: red;'>❌ 测试账户验证失败</p>\n";
    }

} catch (Exception $e) {
