<?php

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160818233147 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE user CHANGE username username VARCHAR(180) NOT NULL, CHANGE username_canonical username_canonical VARCHAR(180) NOT NULL, CHANGE email email VARCHAR(180) NOT NULL, CHANGE email_canonical email_canonical VARCHAR(180) NOT NULL');
        $this->addSql('ALTER TABLE notification ADD response_by VARCHAR(100) NOT NULL');
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE notification DROP response_by');
        $this->addSql('ALTER TABLE `user` CHANGE username username VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, CHANGE username_canonical username_canonical VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, CHANGE email email VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, CHANGE email_canonical email_canonical VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci');
    }
}
