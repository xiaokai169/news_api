CREATE TABLE IF NOT EXISTS `distributed_locks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lockKey` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lockId` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expire_time` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_3327048557F10DA4` (`lockKey`),
  KEY `idx_expire_time` (`expire_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
