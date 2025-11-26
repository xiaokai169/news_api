<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251118080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Modify encoding_aeskey column length from 43 to 44 to fix data truncation error';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE wechat_public_account MODIFY encoding_aeskey VARCHAR(44) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE wechat_public_account MODIFY encoding_aeskey VARCHAR(43) DEFAULT NULL');
    }
}
