<?php
/**
 * 修复表字段名不一致问题
 */

echo "=== 修复表字段名不一致问题 ===\n\n";

// 数据库配置
$dbConfig = [
    'host' => '127.0.0.1',
    'port' => '3306',
    'dbname' => 'official_website',
    'username' => 'root',
    'password' => 'qwe147258..',
    'charset' => 'utf8mb4'
];

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ 数据库连接成功\n";
} catch (Exception $e) {
    echo "✗ 数据库连接失败: " . $e->getMessage() . "\n";
    exit(1);
}

$fixes = [];

// 修复1: sys_news_article - 重命名 create_time 为 create_at，update_time 为 updated_at
echo "\n=== 修复 sys_news_article 表 ===\n";
try {
    // 检查当前字段
    $stmt = $pdo->query("DESCRIBE sys_news_article");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('create_time', $columns) && !in_array('create_at', $columns)) {
        $pdo->exec("ALTER TABLE sys_news_article CHANGE COLUMN create_time create_at datetime NULL");
        echo "✓ 重命名 create_time 为 create_at\n";
        $fixes[] = 'sys_news_article.create_time -> create_at';
    }

    if (in_array('update_time', $columns) && !in_array('updated_at', $columns)) {
        $pdo->exec("ALTER TABLE sys_news_article CHANGE COLUMN update_time updated_at datetime NULL");
        echo "✓ 重命名 update_time 为 updated_at\n";
        $fixes[] = 'sys_news_article.update_time -> updated_at';
    }

} catch (Exception $e) {
    echo "✗ 修复 sys_news_article 时出错: " . $e->getMessage() . "\n";
}

// 修复2: article_read_logs - 重命名 update_at 为 updated_at
echo "\n=== 修复 article_read_logs 表 ===\n";
try {
    $stmt = $pdo->query("DESCRIBE article_read_logs");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('update_at', $columns) && !in_array('updated_at', $columns)) {
        $pdo->exec("ALTER TABLE article_read_logs CHANGE COLUMN update_at updated_at datetime NOT NULL");
        echo "✓ 重命名 update_at 为 updated_at\n";
        $fixes[] = 'article_read_logs.update_at -> updated_at';
    }

} catch (Exception $e) {
    echo "✗ 修复 article_read_logs 时出错: " . $e->getMessage() . "\n";
}

// 修复3: article_read_statistics - 重命名 update_at 为 updated_at
echo "\n=== 修复 article_read_statistics 表 ===\n";
try {
    $stmt = $pdo->query("DESCRIBE article_read_statistics");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('update_at', $columns) && !in_array('updated_at', $columns)) {
        $pdo->exec("ALTER TABLE article_read_statistics CHANGE COLUMN update_at updated_at datetime NOT NULL");
        echo "✓ 重命名 update_at 为 updated_at\n";
        $fixes[] = 'article_read_statistics.update_at -> updated_at';
    }

} catch (Exception $e) {
    echo "✗ 修复 article_read_statistics 时出错: " . $e->getMessage() . "\n";
}

echo "\n=== 修复完成 ===\n";
echo "总共应用了 " . count($fixes) . " 个修复:\n";
foreach ($fixes as $fix) {
    echo "  - $fix\n";
}

// 验证修复结果
echo "\n=== 验证修复结果 ===\n";
$tables = ['sys_news_article', 'article_read_logs', 'article_read_statistics'];

foreach ($tables as $table) {
    echo "\n表 $table:\n";
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (in_array('create_at', $columns)) {
            echo "  ✓ 包含 create_at\n";
        } else {
            echo "  ✗ 缺少 create_at\n";
        }

        if (in_array('updated_at', $columns)) {
            echo "  ✓ 包含 updated_at\n";
        } else {
            echo "  ✗ 缺少 updated_at\n";
        }

        if (in_array('update_at', $columns)) {
            echo "  ✗ 仍有错误的 update_at\n";
        } else {
            echo "  ✓ 没有错误的 update_at\n";
        }

    } catch (Exception $e) {
        echo "  ✗ 验证时出错: " . $e->getMessage() . "\n";
    }
}

echo "\n修复脚本执行完毕！\n";
