CREATE TABLE IF NOT EXISTS `wechat_public_account` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `avatar_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `app_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `app_secret` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `token` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `encoding_aeskey` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_EEB65770B9A18565` (`app_secret`),
  UNIQUE KEY `UNIQ_EEB657707987212D` (`app_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
