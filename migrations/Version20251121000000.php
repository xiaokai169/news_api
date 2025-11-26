<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251121000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing fields to sys_news_article table including merchantId, userId, and category_id';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sys_news_article ADD merchantId INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE sys_news_article ADD userId INT DEFAULT 0 NOT NULL');
        // Check if category_id exists, if not add it
        if (!$schema->getTable('sys_news_article')->hasColumn('category_id')) {
            $this->addSql('ALTER TABLE sys_news_article ADD category_id INT NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sys_news_article DROP merchantId');
        $this->addSql('ALTER TABLE sys_news_article DROP userId');
        $this->addSql('ALTER TABLE sys_news_article DROP category_id');
    }
}