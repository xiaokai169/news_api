<?php
require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// 加载环境变量
$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/../.env');

// 解析DATABASE_URL
$databaseUrl = $_ENV['DATABASE_URL'] ?? '';
$parsedUrl = parse_url($databaseUrl);

$dbHost = $parsedUrl['host'] ?? '127.0.0.1';
$dbPort = $parsedUrl['port'] ?? '3306';
$dbName = ltrim($parsedUrl['path'] ?? 'official_website', '/');
$dbUser = $parsedUrl['user'] ?? 'root';
$dbPassword = $parsedUrl['pass'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    echo "=== 微信文章数据详细检查 ===\n\n";

    // 1. 检查sys_news_article表的详细数据
    echo "=== 1. sys_news_article 表详细数据 ===\n";
    $articles = $pdo->query("SELECT * FROM sys_news_article ORDER BY create_time DESC")->fetchAll();

    if (empty($articles)) {
        echo "sys_news_article 表中没有数据\n\n";
    } else {
        echo "共有 " . count($articles) . " 篇文章:\n\n";

        foreach ($articles as $index => $article) {
            echo "文章 " . ($index + 1) . ":\n";
            echo "  ID: {$article['id']}\n";
            echo "  标题: {$article['name']}\n";
            echo "  分类ID: {$article['category_id']}\n";
            echo "  封面: {$article['cover']}\n";
            echo "  内容: " . substr($article['content'], 0, 100) . "...\n";
            echo "  发布时间: {$article['release_time']}\n";
            echo "  原始URL: {$article['original_url']}\n";
            echo "  状态: {$article['status']}\n";
            echo "  是否推荐: " . ($article['is_recommend'] ? '是' : '否') . "\n";
            echo "  浏览量: {$article['view_count']}\n";
            echo "  创建时间: {$article['create_time']}\n";
            echo "  更新时间: {$article['update_time']}\n\n";
        }
    }

    // 2. 检查sys_news_article_category表
    echo "=== 2. sys_news_article_category 表数据 ===\n";
    $categories = $pdo->query("SELECT * FROM sys_news_article_category")->fetchAll();

    foreach ($categories as $category) {
        echo "分类ID: {$category['id']}\n";
        echo "  代码: {$category['code']}\n";
        echo "  名称: {$category['name']}\n";
        echo "  创建者: {$category['creator']}\n\n";
    }

    // 3. 检查是否有微信相关的字段或表
    echo "=== 3. 检查微信相关字段 ===\n";

    // 检查sys_news_article表中是否有微信相关字段
    $articleColumns = $pdo->query("DESCRIBE sys_news_article")->fetchAll();
    $wechatFields = array_filter($articleColumns, function($column) {
        $field = $column['Field'];
        return stripos($field, 'wechat') !== false ||
               stripos($field, 'wx') !== false ||
               stripos($field, 'mp') !== false ||
               stripos($field, 'material') !== false ||
               stripos($field, 'media') !== false ||
               stripos($field, 'article_id') !== false ||
               stripos($field, 'sync') !== false;
    });

    if (!empty($wechatFields)) {
        echo "sys_news_article表中的微信相关字段:\n";
        foreach ($wechatFields as $field) {
            echo "  - {$field['Field']} ({$field['Type']})\n";
        }
    } else {
        echo "sys_news_article表中没有找到明显的微信相关字段\n";
    }
    echo "\n";

    // 4. 检查文章内容是否包含微信相关信息
    echo "=== 4. 文章内容微信相关信息检查 ===\n";
    foreach ($articles as $article) {
        echo "文章: {$article['name']} (ID: {$article['id']})\n";

        // 检查内容中是否包含微信相关关键词
        $content = $article['content'] . ' ' . $article['name'] . ' ' . $article['original_url'];
        $wechatKeywords = ['wechat', 'weixin', '微信', '公众号', 'mp.weixin', 'wx'];

        $foundKeywords = [];
        foreach ($wechatKeywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                $foundKeywords[] = $keyword;
            }
        }

        if (!empty($foundKeywords)) {
            echo "  发现微信关键词: " . implode(', ', $foundKeywords) . "\n";
        } else {
            echo "  未发现微信相关信息\n";
        }
        echo "\n";
    }

    // 5. 检查是否有隐藏的微信文章表
    echo "=== 5. 检查可能的微信文章表 ===\n";
    $allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    $possibleWechatTables = [];
    foreach ($allTables as $table) {
        if (stripos($table, 'article') !== false ||
            stripos($table, 'wechat') !== false ||
            stripos($table, 'wx') !== false ||
            stripos($table, 'material') !== false ||
            stripos($table, 'media') !== false) {
            $possibleWechatTables[] = $table;
        }
    }

    if (!empty($possibleWechatTables)) {
        echo "可能的微信相关表:\n";
        foreach ($possibleWechatTables as $table) {
            echo "  - {$table}\n";

            // 检查表记录数
            $count = $pdo->query("SELECT COUNT(*) as count FROM {$table}")->fetch()['count'];
            echo "    记录数: {$count}\n";

            // 如果记录数少，显示样本数据
            if ($count > 0 && $count <= 5) {
                $sample = $pdo->query("SELECT * FROM {$table} LIMIT 2")->fetchAll();
                echo "    样本数据:\n";
                foreach ($sample as $row) {
                    echo "      " . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
                }
            }
            echo "\n";
        }
    } else {
        echo "未找到可能的微信相关表\n\n";
    }

    // 6. 检查wechat_public_account表的详细信息
    echo "=== 6. wechat_public_account 详细信息 ===\n";
    $accounts = $pdo->query("SELECT * FROM wechat_public_account ORDER BY created_at DESC")->fetchAll();

    foreach ($accounts as $account) {
        echo "公众号详情:\n";
        echo "  ID: {$account['id']}\n";
        echo "  名称: {$account['name']}\n";
        echo "  描述: " . substr($account['description'] ?? '', 0, 100) . "...\n";
        echo "  头像: {$account['avatar_url']}\n";
        echo "  App ID: {$account['app_id']}\n";
        echo "  App Secret: " . (empty($account['app_secret']) ? '(空)' : str_repeat('*', strlen($account['app_secret']))) . "\n";
        echo "  Token: {$account['token']}\n";
        echo "  EncodingAESKey: " . (empty($account['encoding_aeskey']) ? '(空)' : str_repeat('*', strlen($account['encoding_aeskey']))) . "\n";
        echo "  激活状态: " . ($account['is_active'] ? '激活' : '未激活') . "\n";
        echo "  创建时间: {$account['created_at']}\n";
        echo "  更新时间: {$account['updated_at']}\n\n";
    }

    echo "=== 详细检查完成 ===\n";

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
