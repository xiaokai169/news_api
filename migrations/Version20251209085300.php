<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 创建异步任务队列相关表
 */
final class Version20251209085300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create async task queue tables: async_tasks, task_execution_logs, queue_statistics, task_dependencies';
    }

    public function up(Schema $schema): void
    {
        // 创建异步任务表
        $this->addSql('
            CREATE TABLE `async_tasks` (
                `id` varchar(36) NOT NULL COMMENT \'任务UUID\',
                `type` varchar(50) NOT NULL COMMENT \'任务类型\',
                `priority` int NOT NULL DEFAULT 5 COMMENT \'优先级(1-10)\',
                `status` varchar(20) NOT NULL DEFAULT \'pending\' COMMENT \'任务状态\',
                `payload` json NOT NULL COMMENT \'任务数据\',
                `result` json DEFAULT NULL COMMENT \'执行结果\',
                `error_message` text DEFAULT NULL COMMENT \'错误信息\',
                `retry_count` int NOT NULL DEFAULT 0 COMMENT \'重试次数\',
                `max_retries` int NOT NULL DEFAULT 3 COMMENT \'最大重试次数\',
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT \'创建时间\',
                `started_at` datetime DEFAULT NULL COMMENT \'开始时间\',
                `completed_at` datetime DEFAULT NULL COMMENT \'完成时间\',
                `expires_at` datetime DEFAULT NULL COMMENT \'过期时间\',
                `created_by` varchar(100) DEFAULT NULL COMMENT \'创建者\',
                `queue_name` varchar(50) DEFAULT NULL COMMENT \'队列名称\',
                PRIMARY KEY (`id`),
                KEY `idx_status_priority` (`status`, `priority`),
                KEY `idx_type_status` (`type`, `status`),
                KEY `idx_created_at` (`created_at`),
                KEY `idx_queue_name` (`queue_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT=\'异步任务表\'
        ');

        // 创建任务执行日志表
        $this->addSql('
            CREATE TABLE `task_execution_logs` (
                `id` bigint NOT NULL AUTO_INCREMENT,
                `task_id` varchar(36) NOT NULL COMMENT \'任务ID\',
                `execution_id` varchar(36) NOT NULL COMMENT \'执行ID\',
                `status` varchar(20) NOT NULL COMMENT \'执行状态\',
                `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT \'开始时间\',
                `completed_at` datetime DEFAULT NULL COMMENT \'结束时间\',
                `duration_ms` int DEFAULT NULL COMMENT \'执行时长(毫秒)\',
                `memory_usage` int DEFAULT NULL COMMENT \'内存使用(字节)\',
                `processed_items` int DEFAULT NULL COMMENT \'处理项目数\',
                `error_message` text DEFAULT NULL COMMENT \'错误信息\',
                `stack_trace` text DEFAULT NULL COMMENT \'错误堆栈\',
                `metadata` json DEFAULT NULL COMMENT \'元数据\',
                PRIMARY KEY (`id`),
                KEY `idx_task_id` (`task_id`),
                KEY `idx_execution_id` (`execution_id`),
                KEY `idx_started_at` (`started_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT=\'任务执行日志表\'
        ');

        // 创建队列统计表
        $this->addSql('
            CREATE TABLE `queue_statistics` (
                `id` bigint NOT NULL AUTO_INCREMENT,
                `queue_name` varchar(50) NOT NULL COMMENT \'队列名称\',
                `stat_date` date NOT NULL COMMENT \'统计日期\',
                `stat_hour` tinyint NOT NULL COMMENT \'统计小时(0-23)\',
                `enqueued_count` int NOT NULL DEFAULT 0 COMMENT \'入队数量\',
                `dequeued_count` int NOT NULL DEFAULT 0 COMMENT \'出队数量\',
                `completed_count` int NOT NULL DEFAULT 0 COMMENT \'完成数量\',
                `failed_count` int NOT NULL DEFAULT 0 COMMENT \'失败数量\',
                `avg_duration_ms` int DEFAULT NULL COMMENT \'平均执行时长\',
                `max_duration_ms` int DEFAULT NULL COMMENT \'最大执行时长\',
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT \'创建时间\',
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_queue_date_hour` (`queue_name`, `stat_date`, `stat_hour`),
                KEY `idx_stat_date` (`stat_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT=\'队列统计表\'
        ');

        // 创建任务依赖表
        $this->addSql('
            CREATE TABLE `task_dependencies` (
                `id` bigint NOT NULL AUTO_INCREMENT,
                `task_id` varchar(36) NOT NULL COMMENT \'任务ID\',
                `depends_on_task_id` varchar(36) NOT NULL COMMENT \'依赖的任务ID\',
                `dependency_type` varchar(20) NOT NULL DEFAULT \'finish\' COMMENT \'依赖类型\',
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT \'创建时间\',
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_task_dependency` (`task_id`, `depends_on_task_id`),
                KEY `idx_task_id` (`task_id`),
                KEY `idx_depends_on` (`depends_on_task_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT=\'任务依赖表\'
        ');
    }

    public function down(Schema $schema): void
    {
        // 删除表（按依赖关系倒序）
        $this->addSql('DROP TABLE IF EXISTS `task_dependencies`');
        $this->addSql('DROP TABLE IF EXISTS `queue_statistics`');
        $this->addSql('DROP TABLE IF EXISTS `task_execution_logs`');
        $this->addSql('DROP TABLE IF EXISTS `async_tasks`');
    }
}
