CREATE TABLE IF NOT EXISTS `official` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_id` int unsigned NOT NULL,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` smallint NOT NULL DEFAULT '2',
  `create_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `release_time` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `original_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `article_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_9877320D12469DE2` (`category_id`),
  CONSTRAINT `FK_9877320D12469DE2` FOREIGN KEY (`category_id`) REFERENCES `sys_news_article_category` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
