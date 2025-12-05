-- MySQL dump 10.13  Distrib 8.0.36, for Linux (x86_64)
--
-- Host: localhost    Database: official_website
-- ------------------------------------------------------
-- Server version	8.0.36

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `article_read_logs`
--

DROP TABLE IF EXISTS `article_read_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `article_read_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `article_id` int NOT NULL,
  `user_id` int NOT NULL DEFAULT '0',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `read_time` datetime NOT NULL,
  `session_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referer` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration_seconds` int NOT NULL DEFAULT '0',
  `is_completed` tinyint(1) NOT NULL DEFAULT '0',
  `create_at` datetime NOT NULL,
  `update_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_article_read_logs_article_id` (`article_id`),
  KEY `idx_article_read_logs_user_id` (`user_id`),
  KEY `idx_article_read_logs_read_time` (`read_time`),
  KEY `idx_article_read_logs_ip_address` (`ip_address`),
  KEY `idx_article_read_logs_session_article` (`session_id`,`article_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `article_read_logs`
--

LOCK TABLES `article_read_logs` WRITE;
/*!40000 ALTER TABLE `article_read_logs` DISABLE KEYS */;
INSERT INTO `article_read_logs` VALUES (1,1,1,'127.0.0.1','Test-Agent','2025-11-27 04:04:36','test-session-123','desktop','https://example.com',60,1,'2025-11-27 04:04:36','2025-11-27 04:04:36');
/*!40000 ALTER TABLE `article_read_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `article_read_statistics`
--

DROP TABLE IF EXISTS `article_read_statistics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `article_read_statistics` (
  `id` int NOT NULL AUTO_INCREMENT,
  `article_id` int NOT NULL,
  `stat_date` date NOT NULL,
  `total_reads` int NOT NULL DEFAULT '0',
  `unique_users` int NOT NULL DEFAULT '0',
  `anonymous_reads` int NOT NULL DEFAULT '0',
  `registered_reads` int NOT NULL DEFAULT '0',
  `avg_duration_seconds` decimal(10,2) NOT NULL DEFAULT '0.00',
  `completion_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `create_at` datetime NOT NULL,
  `update_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_article_date` (`article_id`,`stat_date`),
  KEY `idx_article_read_statistics_article_id` (`article_id`),
  KEY `idx_article_read_statistics_stat_date` (`stat_date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `article_read_statistics`
--

LOCK TABLES `article_read_statistics` WRITE;
/*!40000 ALTER TABLE `article_read_statistics` DISABLE KEYS */;
INSERT INTO `article_read_statistics` VALUES (1,1,'2025-11-27',1,1,0,1,60.00,100.00,'2025-11-27 04:04:36','2025-11-27 04:04:36');
/*!40000 ALTER TABLE `article_read_statistics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `distributed_locks`
--

DROP TABLE IF EXISTS `distributed_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `distributed_locks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lockKey` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `lockId` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `expire_time` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_3327048557F10DA4` (`lockKey`),
  KEY `idx_expire_time` (`expire_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `distributed_locks`
--

LOCK TABLES `distributed_locks` WRITE;
/*!40000 ALTER TABLE `distributed_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `distributed_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doctrine_migration_versions`
--

DROP TABLE IF EXISTS `doctrine_migration_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctrine_migration_versions` (
  `version` varchar(191) COLLATE utf8mb3_unicode_ci NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int DEFAULT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctrine_migration_versions`
--

LOCK TABLES `doctrine_migration_versions` WRITE;
/*!40000 ALTER TABLE `doctrine_migration_versions` DISABLE KEYS */;
INSERT INTO `doctrine_migration_versions` VALUES ('DoctrineMigrations\\Version20251204084207','2025-12-04 08:42:36',760);
/*!40000 ALTER TABLE `doctrine_migration_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `official`
--

DROP TABLE IF EXISTS `official`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `official` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_id` int unsigned NOT NULL,
  `title` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `content` longtext COLLATE utf8mb3_unicode_ci NOT NULL,
  `status` smallint NOT NULL DEFAULT '2',
  `create_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `release_time` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `original_url` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `article_id` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_9877320D12469DE2` (`category_id`),
  CONSTRAINT `FK_9877320D12469DE2` FOREIGN KEY (`category_id`) REFERENCES `sys_news_article_category` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `official`
--

LOCK TABLES `official` WRITE;
/*!40000 ALTER TABLE `official` DISABLE KEYS */;
/*!40000 ALTER TABLE `official` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sys_news_article`
--

DROP TABLE IF EXISTS `sys_news_article`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sys_news_article` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_id` int unsigned NOT NULL,
  `merchant_id` int NOT NULL DEFAULT '0',
  `user_id` int NOT NULL DEFAULT '0',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cover` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `release_time` datetime DEFAULT NULL,
  `original_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` smallint NOT NULL DEFAULT '1',
  `is_recommend` tinyint(1) NOT NULL DEFAULT '0',
  `perfect` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `update_time` datetime DEFAULT NULL,
  `create_time` datetime DEFAULT NULL,
  `view_count` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `IDX_FCC4214812469DE2` (`category_id`),
  CONSTRAINT `FK_FCC4214812469DE2` FOREIGN KEY (`category_id`) REFERENCES `sys_news_article_category` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sys_news_article`
--

LOCK TABLES `sys_news_article` WRITE;
/*!40000 ALTER TABLE `sys_news_article` DISABLE KEYS */;
INSERT INTO `sys_news_article` VALUES (1,1,0,0,'测试文章','https://example.com/image.jpg','这是一篇用于测试阅读功能的文章内容',NULL,'',1,0,'',NULL,NULL,1),(2,1,0,0,'士大夫','https://files.arab-bee.com/f3/e5/f3e5cf3.png','<p>1111</p>',NULL,'',1,0,'',NULL,NULL,0),(3,1,0,0,'123111','https://files.arab-bee.com/biz/f3/e5/f3e5cf3.png','<p>111</p>',NULL,'',1,0,'',NULL,NULL,0),(4,3,0,0,'525','https://files.arab-bee.com/biz/5a/ca/5aca33b.jpg','<p>5455</p>',NULL,'',1,1,'',NULL,NULL,0);
/*!40000 ALTER TABLE `sys_news_article` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sys_news_article_category`
--

DROP TABLE IF EXISTS `sys_news_article_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sys_news_article_category` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creator` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sys_news_article_category`
--

LOCK TABLES `sys_news_article_category` WRITE;
/*!40000 ALTER TABLE `sys_news_article_category` DISABLE KEYS */;
INSERT INTO `sys_news_article_category` VALUES (1,'TEST_CAT','测试分类','admin'),(2,'GZ_0012','士大夫','系统'),(3,'verify_fix_1764827396','验证修复测试','验证脚本');
/*!40000 ALTER TABLE `sys_news_article_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `nickname` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `createdAt` datetime NOT NULL,
  `updatedAt` datetime DEFAULT NULL,
  `status` smallint NOT NULL,
  `password` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `roles` json NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_1483A5E9E7927C74` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wechat_public_account`
--

DROP TABLE IF EXISTS `wechat_public_account`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wechat_public_account` (
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wechat_public_account`
--

LOCK TABLES `wechat_public_account` WRITE;
/*!40000 ALTER TABLE `wechat_public_account` DISABLE KEYS */;
INSERT INTO `wechat_public_account` VALUES ('gh_5bd14b072cce27b2','1','2','','wx844c41dbae899300','6b71dc3b63c3622a2a9c3d190e0c63f6','2025-12-04 06:19:38','2025-12-04 06:19:38',1,'35a513b864deba09d1c1496fe2f37711','pDDKH7Qiw7khd7DiB76cI3TOtkBIH1Oq'),('test_account_001','测试公众号','这是一个用于测试的微信公众号',NULL,'test_app_id_001','test_app_secret_001','2025-12-04 06:18:29','2025-12-04 06:18:29',1,NULL,NULL);
/*!40000 ALTER TABLE `wechat_public_account` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-12-05 11:16:45
