<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 修改 sys_news_article 表的时间字段名称
 */
final class Version20251219110900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename time fields in sys_news_article table: updated_at -> update_at, create_at -> created_at';
    }

    public function up(Schema $schema): void
    {
        // 重命名字段：updated_at -> update_at
        $this->addSql('ALTER TABLE `sys_news_article` CHANGE `updated_at` `update_at` datetime DEFAULT NULL');

        // 重命名字段：create_at -> created_at
        $this->addSql('ALTER TABLE `sys_news_article` CHANGE `create_at` `created_at` datetime DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // 回滚操作：恢复原始字段名
        $this->addSql('ALTER TABLE `sys_news_article` CHANGE `update_at` `updated_at` datetime DEFAULT NULL');
        $this->addSql('ALTER TABLE `sys_news_article` CHANGE `created_at` `create_at` datetime DEFAULT NULL');
    }
}
