<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 创建分布式锁表
 */
final class Version20251117033000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '创建分布式锁表';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE distributed_locks (
            id INT AUTO_INCREMENT NOT NULL,
            lock_key VARCHAR(255) NOT NULL,
            lock_id VARCHAR(32) NOT NULL,
            expire_time DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY(id),
            UNIQUE INDEX UNIQ_lock_key (lock_key),
            INDEX IDX_expire_time (expire_time)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE distributed_locks');
    }
}
