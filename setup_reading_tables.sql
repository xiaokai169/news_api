-- 创建文章表（如果不存在）
CREATE TABLE IF NOT EXISTS `sys_news_article` (
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
    PRIMARY KEY (`id`),
    KEY `idx_category_id` (`category_id`),
    KEY `idx_status` (`status`),
    KEY `idx_is_recommend` (`is_recommend`),
    KEY `idx_is_top` (`is_top`),
    KEY `idx_publish_time` (`publish_time`),
    KEY `idx_view_count` (`view_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='新闻文章表';

-- 插入测试文章数据
INSERT IGNORE INTO `sys_news_article` (id, title, content, summary, author, source, category_id, status, is_recommend, is_top, publish_time, view_count) VALUES
(1, 'Symfony框架入门教程', 'Symfony是一个强大的PHP框架，本文将介绍Symfony的基础知识和使用方法。Symfony遵循MVC架构模式，提供了丰富的组件和工具，帮助开发者快速构建高质量的Web应用程序。', 'Symfony框架的基础入门教程，适合初学者学习', '技术编辑', '原创', 1, 1, 1, 1, DATE_SUB(NOW(), INTERVAL 1 DAY), 0),
(2, 'PHP最佳实践指南', 'PHP作为最流行的Web开发语言之一，有很多最佳实践需要遵循。本文将介绍PHP开发中的编码规范、性能优化、安全防护等方面的最佳实践。', 'PHP开发的最佳实践，提升代码质量和性能', '资深开发者', '技术分享', 1, 1, 1, 0, DATE_SUB(NOW(), INTERVAL 2 DAY), 0),
(3, '数据库优化技巧', '数据库性能优化是Web应用开发中的重要环节。本文将介绍索引优化、查询优化、表结构设计等方面的技巧，帮助开发者提升数据库性能。', '数据库性能优化的实用技巧和方法', 'DBA专家', '技术专栏', 2, 1, 0, 0, DATE_SUB(NOW(), INTERVAL 3 DAY), 0);

-- 创建文章阅读记录表
CREATE TABLE IF NOT EXISTS `article_read_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `article_id` int(11) NOT NULL COMMENT '文章ID',
    `user_id` int(11) DEFAULT NULL COMMENT '用户ID，匿名用户为NULL',
    `ip_address` varchar(45) NOT NULL COMMENT 'IP地址',
    `user_agent` varchar(500) DEFAULT NULL COMMENT '用户代理',
    `read_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '阅读时间',
    `session_id` varchar(255) DEFAULT NULL COMMENT '会话ID',
    `device_type` varchar(20) DEFAULT NULL COMMENT '设备类型：desktop/mobile/tablet',
    `referer` varchar(500) DEFAULT NULL COMMENT '来源页面',
    `duration_seconds` int(11) DEFAULT NULL COMMENT '阅读时长（秒）',
    `is_completed` tinyint(1) DEFAULT '0' COMMENT '是否读完：1-是，0-否',
    `create_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `update_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_article_user_session_date` (`article_id`, `user_id`, `session_id`, DATE(`read_time`)),
    KEY `idx_article_id` (`article_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_read_time` (`read_time`),
    KEY `idx_device_type` (`device_type`),
    KEY `idx_create_at` (`create_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文章阅读记录表';

-- 创建文章阅读统计表
CREATE TABLE IF NOT EXISTS `article_read_statistics` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `article_id` int(11) NOT NULL COMMENT '文章ID',
    `stat_date` date NOT NULL COMMENT '统计日期',
    `total_reads` int(11) NOT NULL DEFAULT '0' COMMENT '总阅读次数',
    `unique_users` int(11) NOT NULL DEFAULT '0' COMMENT '独立用户数',
    `anonymous_reads` int(11) NOT NULL DEFAULT '0' COMMENT '匿名用户阅读数',
    `registered_reads` int(11) NOT NULL DEFAULT '0' COMMENT '注册用户阅读数',
    `avg_duration_seconds` decimal(10,2) DEFAULT '0.00' COMMENT '平均阅读时长（秒）',
    `completion_rate` decimal(5,2) DEFAULT '0.00' COMMENT '阅读完成率（%）',
    `create_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `update_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_article_date` (`article_id`, `stat_date`),
    KEY `idx_article_id` (`article_id`),
    KEY `idx_stat_date` (`stat_date`),
    KEY `idx_total_reads` (`total_reads`),
    KEY `idx_create_at` (`create_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文章阅读统计表';
