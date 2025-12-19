<?php
require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// 加载环境变量
$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

// 数据库连接参数
$host = $_ENV['DATABASE_HOST'] ?? '127.0.0.1';
$port = $_ENV['DATABASE_PORT'] ?? '3306';
$dbname = $_ENV['DATABASE_NAME'] ?? 'official_website';
$username = $_ENV['DATABASE_USER'] ?? 'root';
$password = $_ENV['DATABASE_PASSWORD'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== 数据库连接成功 ===\n\n";

    // 检查 sys_news_article 表结构
    echo "=== sys_news_article 表结构 ===\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM sys_news_article");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }

    echo "\n=== article_read_logs 表结构 ===\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM article_read_logs");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }

    echo "\n=== article_read_statistics 表结构 ===\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM article_read_statistics");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }

} catch (PDOException $e) {
    echo "数据库连接失败: " . $e->getMessage() . "\n";
}
