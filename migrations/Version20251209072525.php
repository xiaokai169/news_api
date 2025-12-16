<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251209072525 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_3327048557F10DA4 ON distributed_locks');
        $this->addSql('ALTER TABLE distributed_locks DROP lockKey, DROP lockId');
        $this->addSql('ALTER TABLE distributed_locks RENAME INDEX idx_lock_key TO UNIQ_3327048519A50076');
        $this->addSql('ALTER TABLE official DROP FOREIGN KEY FK_9877320D12469DE2');
        $this->addSql('DROP INDEX IDX_9877320D12469DE2 ON official');
        $this->addSql('ALTER TABLE official CHANGE category_id category_id INT DEFAULT 1 NOT NULL, CHANGE digest digest LONGTEXT DEFAULT NULL, CHANGE show_cover_pic show_cover_pic SMALLINT DEFAULT 0 NOT NULL, CHANGE need_open_comment need_open_comment SMALLINT DEFAULT 0 NOT NULL, CHANGE only_fans_can_comment only_fans_can_comment SMALLINT DEFAULT 0 NOT NULL, CHANGE is_deleted is_deleted TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE distributed_locks ADD lockKey VARCHAR(255) NOT NULL, ADD lockId VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3327048557F10DA4 ON distributed_locks (lockKey)');
        $this->addSql('ALTER TABLE distributed_locks RENAME INDEX uniq_3327048519a50076 TO idx_lock_key');
        $this->addSql('ALTER TABLE official CHANGE category_id category_id INT UNSIGNED NOT NULL, CHANGE is_deleted is_deleted TINYINT(1) DEFAULT 0, CHANGE digest digest TEXT DEFAULT NULL, CHANGE show_cover_pic show_cover_pic SMALLINT DEFAULT 0, CHANGE need_open_comment need_open_comment SMALLINT DEFAULT 0, CHANGE only_fans_can_comment only_fans_can_comment SMALLINT DEFAULT 0');
        $this->addSql('ALTER TABLE official ADD CONSTRAINT FK_9877320D12469DE2 FOREIGN KEY (category_id) REFERENCES sys_news_article_category (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_9877320D12469DE2 ON official (category_id)');
    }
}
