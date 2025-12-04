<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251204084207 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE distributed_locks (id INT AUTO_INCREMENT NOT NULL, lockKey VARCHAR(255) NOT NULL, lockId VARCHAR(255) NOT NULL, expire_time DATETIME NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_3327048557F10DA4 (lockKey), INDEX idx_expire_time (expire_time), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE official (id INT AUTO_INCREMENT NOT NULL, category_id INT UNSIGNED NOT NULL, title VARCHAR(100) DEFAULT \'\' NOT NULL, content LONGTEXT NOT NULL, status SMALLINT DEFAULT 2 NOT NULL, create_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, release_time VARCHAR(255) DEFAULT \'\' NOT NULL, original_url VARCHAR(255) DEFAULT \'\' NOT NULL, article_id VARCHAR(255) DEFAULT NULL, INDEX IDX_9877320D12469DE2 (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, nickname VARCHAR(255) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME DEFAULT NULL, status SMALLINT NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE official ADD CONSTRAINT FK_9877320D12469DE2 FOREIGN KEY (category_id) REFERENCES sys_news_article_category (id)');
        $this->addSql('ALTER TABLE sys_news_article ADD CONSTRAINT FK_FCC4214812469DE2 FOREIGN KEY (category_id) REFERENCES sys_news_article_category (id)');
        $this->addSql('ALTER TABLE wechat_public_account DROP INDEX app_secret, ADD UNIQUE INDEX UNIQ_EEB65770B9A18565 (app_secret)');
        $this->addSql('ALTER TABLE wechat_public_account DROP INDEX app_id, ADD UNIQUE INDEX UNIQ_EEB657707987212D (app_id)');
        $this->addSql('DROP INDEX appSecret ON wechat_public_account');
        $this->addSql('DROP INDEX appId ON wechat_public_account');
        $this->addSql('ALTER TABLE wechat_public_account CHANGE description description LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE official DROP FOREIGN KEY FK_9877320D12469DE2');
        $this->addSql('DROP TABLE distributed_locks');
        $this->addSql('DROP TABLE official');
        $this->addSql('DROP TABLE users');
        $this->addSql('ALTER TABLE sys_news_article DROP FOREIGN KEY FK_FCC4214812469DE2');
        $this->addSql('ALTER TABLE wechat_public_account CHANGE description description TEXT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX appSecret ON wechat_public_account (app_secret)');
        $this->addSql('CREATE UNIQUE INDEX appId ON wechat_public_account (app_id)');
        $this->addSql('ALTER TABLE wechat_public_account RENAME INDEX uniq_eeb65770b9a18565 TO app_secret');
        $this->addSql('ALTER TABLE wechat_public_account RENAME INDEX uniq_eeb657707987212d TO app_id');
    }
}
