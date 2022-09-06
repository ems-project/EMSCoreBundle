<?php

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20161026093840 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE user ADD allowed_to_configure_wysiwyg TINYINT(1) NOT NULL, ADD wysiwyg_profile TINYTEXT DEFAULT NULL, ADD wysiwyg_options LONGTEXT DEFAULT NULL');
        $this->addSql('update `user` set wysiwyg_profile = \'standard\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE `user` DROP allowed_to_configure_wysiwyg, DROP wysiwyg_profile, DROP wysiwyg_options');
    }
}
