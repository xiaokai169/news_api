CREATE TABLE IF NOT EXISTS `distributed_locks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lock_key` varchar(255) NOT NULL,
  `lock_id` varchar(255) NOT NULL,
  `expire_time` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_lock_key` (`lock_key`),
  KEY `idx_expire_time` (`expire_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
