-- 插入测试文章分类
INSERT IGNORE INTO sys_news_article_category (id, code, name, creator) VALUES
(1, 'TEST_CAT', '测试分类', 'admin'),
(2, 'NEWS_CAT', '新闻分类', 'admin');

-- 插入测试文章
INSERT IGNORE INTO sys_news_article (id, category_id, merchant_id, user_id, name, cover, content, status, is_recommend, perfect, release_time, create_time, update_time, view_count) VALUES
(1, 1, 0, 0, '测试文章1', 'https://example.com/cover1.jpg', '<p>这是测试文章1的内容</p>', 1, 0, '', NOW(), NOW(), NOW(), 0),
(2, 1, 0, 0, '测试文章2', 'https://example.com/cover2.jpg', '<p>这是测试文章2的内容</p>', 1, 1, '', NOW(), NOW(), NOW(), 0),
(3, 2, 0, 0, '新闻文章1', 'https://example.com/news1.jpg', '<p>这是新闻文章1的内容</p>', 1, 0, '', NOW(), NOW(), NOW(), 0);
