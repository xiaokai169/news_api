<?php

require_once __DIR__ . '/../vendor/autoload.php';

echo "=== 数据库微信配置检查 ===<br>\n";
echo "检查时间: " . date('Y-m-d H:i:s') . "<br>\n";

try {
    // 直接使用数据库连接
    $databaseUrl = "mysql://root:qwe147258..@127.0.0.1:3306/official_website?serverVersion=8.0&charset=utf8";

    // 解析数据库URL
    $parsed = parse_url($databaseUrl);
    $host = $parsed['host'];
    $port = $parsed['port'] ?? 3306;
    $dbname = ltrim($parsed['path'], '/');
    $username = $parsed['user'];
    $password = $parsed['pass'];

    echo "📊 数据库连接信息:<br>\n";
    echo "- 主机: {$host}:{$port}<br>\n";
    echo "- 数据库: {$dbname}<br>\n";
    echo "- 用户名: {$username}<br>\n";

    // 创建PDO连接
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "✅ 数据库连接成功<br>\n";

    // 1. 检查所有表
    echo "<h2>1. 数据库表检查</h2>\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "📊 总表数: " . count($tables) . "<br>\n";

    $requiredTables = ['wechat_public_account', 'official', 'distributed_locks', 'sys_news_article_category'];
    foreach ($requiredTables as $table) {
        if (in_array($table, $tables)) {
            echo "✅ 表 {$table} 存在<br>\n";
        } else {
            echo "❌ 表 {$table} 不存在<br>\n";
        }
    }

    // 2. 检查微信公众号账户表
    if (in_array('wechat_public_account', $tables)) {
        echo "<h2>2. 微信公众号账户检查</h2>\n";

        // 检查表结构
        $columns = $pdo->query("DESCRIBE wechat_public_account")->fetchAll();
        echo "📊 wechat_public_account表结构:<br>\n";
        foreach ($columns as $column) {
            echo "- {$column['Field']} ({$column['Type']}) " . ($column['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . "<br>\n";
        }

        // 检查数据
        $accounts = $pdo->query("SELECT * FROM wechat_public_account")->fetchAll();
        echo "<br>📊 公众号账户数据 (共 " . count($accounts) . " 条):<br>\n";

        if (empty($accounts)) {
            echo "❌ 没有配置任何公众号账户<br>\n";
        } else {
            foreach ($accounts as $account) {
                $id = $account['id'] ?? 'N/A';
                $name = $account['name'] ?? 'N/A';
                $appId = $account['app_id'] ?? 'N/A';
                $appSecret = $account['app_secret'] ? substr($account['app_secret'], 0, 8) . '***' : 'N/A';
                $isActive = $account['is_active'] ?? 0;
                $createdAt = $account['created_at'] ?? 'N/A';

                echo "<br>📱 账户详情:<br>\n";
                echo "- ID: {$id}<br>\n";
                echo "- 名称: {$name}<br>\n";
                echo "- APPID: {$appId}<br>\n";
                echo "- APPSECRET: {$appSecret}<br>\n";
                echo "- 激活状态: " . ($isActive ? '是' : '否') . "<br>\n";
                echo "- 创建时间: {$createdAt}<br>\n";

                // 验证必要字段
                if (!$appId) {
                    echo "⚠️ 警告: 缺少APPID<br>\n";
                }
                if (!$account['app_secret']) {
                    echo "⚠️ 警告: 缺少APPSECRET<br>\n";
                }

                // 如果有完整配置，测试API连接
                if ($appId && $account['app_secret']) {
                    echo "🔑 测试API连接...<br>\n";
                    $apiTest = testWechatApi($appId, $account['app_secret']);
                    if ($apiTest['success']) {
                        echo "✅ API连接成功<br>\n";
                    } else {
                        echo "❌ API连接失败: " . $apiTest['error'] . "<br>\n";
                    }
                }
            }
        }
    }

    // 3. 检查official表
    if (in_array('official', $tables)) {
        echo "<h2>3. 文章数据检查</h2>\n";

        $totalArticles = $pdo->query("SELECT COUNT(*) as count FROM official")->fetch()['count'];
        $activeArticles = $pdo->query("SELECT COUNT(*) as count FROM official WHERE status = 1")->fetch()['count'];

        echo "📊 文章统计:<br>\n";
        echo "- 总文章数: {$totalArticles}<br>\n";
        echo "- 活跃文章数: {$activeArticles}<br>\n";

        if ($totalArticles > 0) {
            // 检查最近的文章
            $recentArticles = $pdo->query("SELECT id, title, article_id, create_at, release_time FROM official ORDER BY create_at DESC LIMIT 5")->fetchAll();
            echo "<br>📝 最近5篇文章:<br>\n";
            foreach ($recentArticles as $article) {
                $title = mb_substr($article['title'], 0, 50);
                $articleId = $article['article_id'] ?: '无';
                $createTime = $article['create_at'];
                echo "- ID:{$article['id']}, 标题:{$title}..., 文章ID:{$articleId}, 创建时间:{$createTime}<br>\n";
            }

            // 检查有article_id的文章数量
            $articlesWithId = $pdo->query("SELECT COUNT(*) as count FROM official WHERE article_id IS NOT NULL AND article_id != ''")->fetch()['count'];
            echo "<br>📊 有微信文章ID的文章数: {$articlesWithId}<br>\n";
        } else {
            echo "⚠️ 没有同步的文章数据<br>\n";
        }
    }

    // 4. 检查分布式锁表
    if (in_array('distributed_locks', $tables)) {
        echo "<h2>4. 分布式锁检查</h2>\n";

        $activeLocks = $pdo->query("SELECT COUNT(*) as count FROM distributed_locks WHERE expire_time > NOW()")->fetch()['count'];
        $expiredLocks = $pdo->query("SELECT COUNT(*) as count FROM distributed_locks WHERE expire_time <= NOW()")->fetch()['count'];

        echo "📊 锁统计:<br>\n";
        echo "- 活跃锁数: {$activeLocks}<br>\n";
        echo "- 过期锁数: {$expiredLocks}<br>\n";

        if ($activeLocks > 0) {
            $locks = $pdo->query("SELECT lock_key, expire_time FROM distributed_locks WHERE expire_time > NOW()")->fetchAll();
            echo "<br>🔒 活跃锁详情:<br>\n";
            foreach ($locks as $lock) {
                echo "- {$lock['lock_key']} (过期时间: {$lock['expire_time']})<br>\n";
            }
        }
    }

    // 5. 检查分类表
    if (in_array('sys_news_article_category', $tables)) {
        echo "<h2>5. 文章分类检查</h2>\n";

        $categories = $pdo->query("SELECT * FROM sys_news_article_category ORDER BY id")->fetchAll();
        echo "📊 分类数量: " . count($categories) . "<br>\n";

        // 查找ID为18的分类（GZH_001）
        $gzhCategory = null;
        foreach ($categories as $category) {
            if ($category['id'] == 18) {
                $gzhCategory = $category;
                break;
            }
        }

        if ($gzhCategory) {
            echo "✅ 找到公众号专用分类 (ID:18): {$gzhCategory['name']}<br>\n";
        } else {
            echo "❌ 未找到ID为18的公众号分类<br>\n";
        }

        echo "<br>📂 所有分类:<br>\n";
        foreach ($categories as $category) {
            $marker = ($category['id'] == 18) ? '🔸' : '  ';
            echo "{$marker} ID:{$category['id']}, 名称:{$category['name']}<br>\n";
        }
    }

} catch (Exception $e) {
    echo "❌ 检查过程中发生错误: " . $e->getMessage() . "<br>\n";
    echo "堆栈跟踪: <pre>" . $e->getTraceAsString() . "</pre><br>\n";
}

function testWechatApi($appId, $appSecret) {
    try {
        $client = \Symfony\Component\HttpClient\HttpClient::create();
        $response = $client->request('GET', 'https://api.weixin.qq.com/cgi-bin/token', [
            'query' => [
                'grant_type' => 'client_credential',
                'appid' => $appId,
                'secret' => $appSecret,
            ]
        ]);

        $data = $response->toArray();

        if (isset($data['access_token'])) {
            return ['success' => true, 'token' => $data['access_token']];
        } else {
            return ['success' => false, 'error' => $data['errmsg'] ?? '未知错误', 'code' => $data['errcode'] ?? 'unknown'];
        }

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

echo "<br>=== 检查完成 ===<br>\n";
