<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251126063742 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE official ADD release_time VARCHAR(255) DEFAULT \'\' NOT NULL, ADD original_url VARCHAR(255) DEFAULT \'\' NOT NULL, DROP releaseTime, DROP originalUrl, CHANGE created_at create_at DATETIME NOT NULL, CHANGE articleId article_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE sys_news_article CHANGE name name VARCHAR(50) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE official ADD releaseTime VARCHAR(255) DEFAULT \'\' NOT NULL, ADD originalUrl VARCHAR(255) DEFAULT \'\' NOT NULL, DROP release_time, DROP original_url, CHANGE article_id articleId VARCHAR(255) DEFAULT NULL, CHANGE create_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE sys_news_article CHANGE name name VARCHAR(10) NOT NULL');
    }
}
