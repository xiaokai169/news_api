<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251119094000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create distributed_locks table for synchronization';
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
            UNIQUE INDEX UNIQ_lock_key (lock_key)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE distributed_locks');
    }
}
