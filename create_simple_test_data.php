<?php

require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

// 加载环境变量
$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

// Doctrine配置
$dbParams = [
    'driver'   => 'pdo_mysql',
    'host'     => '127.0.0.1',
    'port'     => '3306',
    'dbname'   => 'official_website',
    'user'     => 'root',
    'password' => 'qwe147258',
    'charset'  => 'utf8mb4',
    'defaultTableOptions' => [
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci'
    ]
];

$config = Setup::createAnnotationMetadataConfiguration([__DIR__.'/src/Entity'], true);
$entityManager = EntityManager::create($dbParams, $config);

try {
    echo "开始创建测试数据...\n";

    // 创建文章表
    $connection = $entityManager->getConnection();

    // 检查表是否存在
    $tableExists = $connection->fetchOne("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'official_website' AND table_name = 'sys_news_article'");

    if ($tableExists == 0) {
        echo "创建sys_news_article表...\n";
        $sql = "
        CREATE TABLE `sys_news_article` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(255) NOT NULL COMMENT '标题',
            `content` longtext NOT NULL COMMENT '内容',
            `summary` varchar(500) DEFAULT NULL COMMENT '摘要',
            `author` varchar(100) DEFAULT NULL COMMENT '作者',
            `source` varchar(100) DEFAULT NULL COMMENT '来源',
            `category_id` int(11) DEFAULT NULL COMMENT '分类ID',
            `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：1-发布，0-草稿',
            `is_recommend` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否推荐',
            `is_top` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否置顶',
            `publish_time` datetime DEFAULT NULL COMMENT '发布时间',
            `view_count` int(11) NOT NULL DEFAULT '0' COMMENT '阅读数量',
            `create_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
            `update_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $connection->executeStatement($sql);
    }

    // 插入测试文章
    $testArticles = [
        [
            'title' => 'Symfony框架入门教程',
            'content' => 'Symfony是一个强大的PHP框架，本文将介绍Symfony的基础知识和使用方法。',
            'summary' => 'Symfony框架的基础入门教程',
            'author' => '技术编辑',
            'source' => '原创',
            'category_id' => 1,
            'is_recommend' => 1,
            'is_top' => 1,
            'publish_time' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ],
        [
            'title' => 'PHP最佳实践指南',
            'content' => 'PHP作为最流行的Web开发语言之一，有很多最佳实践需要遵循。',
            'summary' => 'PHP开发的最佳实践',
            'author' => '资深开发者',
            'source' => '技术分享',
            'category_id' => 1,
            'is_recommend' => 1,
            'is_top' => 0,
            'publish_time' => date('Y-m-d H:i:s', strtotime('-2 days'))
        ]
    ];

    foreach ($testArticles as $article) {
        $sql = "INSERT INTO sys_news_article (title, content, summary, author, source, category_id, status, is_recommend, is_top, publish_time, view_count)
                VALUES (:title, :content, :summary, :author, :source, :category_id, 1, :is_recommend, :is_top, :publish_time, 0)";

        $connection->executeStatement($sql, [
            ':title' => $article['title'],
            ':content' => $article['content'],
            ':summary' => $article['summary'],
            ':author' => $article['author'],
            ':source' => $article['source'],
            ':category_id' => $article['category_id'],
            ':is_recommend' => $article['is_recommend'],
            ':is_top' => $article['is_top'],
            ':publish_time' => $article['publish_time']
        ]);

        echo "插入文章: " . $article['title'] . "\n";
    }

    // 查询插入的文章
    $articles = $connection->fetchAllAssociative("SELECT id, title, view_count FROM sys_news_article ORDER BY id DESC LIMIT 5");

    echo "\n当前文章列表：\n";
    foreach ($articles as $article) {
        echo "ID: {$article['id']}, 标题: {$article['title']}, 阅读数: {$article['view_count']}\n";
    }

    echo "\n测试数据创建完成！\n";

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
