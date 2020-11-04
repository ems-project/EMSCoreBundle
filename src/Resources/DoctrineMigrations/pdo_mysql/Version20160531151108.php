<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160531151108 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE user ADD circles LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE job ADD command VARCHAR(255) DEFAULT NULL, CHANGE service service VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE environment_revision DROP FOREIGN KEY FK_895F7B701DFA7C8F');
        $this->addSql('ALTER TABLE environment_revision DROP FOREIGN KEY FK_895F7B70903E3A94');
        $this->addSql('ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B701DFA7C8F FOREIGN KEY (revision_id) REFERENCES revision (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B70903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) ON DELETE CASCADE');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE environment_revision DROP FOREIGN KEY FK_895F7B701DFA7C8F');
        $this->addSql('ALTER TABLE environment_revision DROP FOREIGN KEY FK_895F7B70903E3A94');
        $this->addSql('ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B701DFA7C8F FOREIGN KEY (revision_id) REFERENCES revision (id)');
        $this->addSql('ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B70903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id)');
        $this->addSql('ALTER TABLE job DROP command, CHANGE service service VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE `user` DROP circles');
    }
}
