<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251113054726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sys_news_article CHANGE release_time release_time DATETIME DEFAULT NULL, CHANGE update_time update_time DATETIME DEFAULT NULL, CHANGE create_time create_time DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sys_news_article CHANGE release_time release_time INT DEFAULT 0 NOT NULL, CHANGE update_time update_time INT DEFAULT 0 NOT NULL, CHANGE create_time create_time INT DEFAULT 0 NOT NULL');
    }
}
