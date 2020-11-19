<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201103082319 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE content_type ADD version_tags JSON DEFAULT NULL COMMENT \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE content_type ADD version_date_from_field VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD version_date_to_field VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type DROP parentfield');

        $this->addSql('ALTER TABLE revision ADD version_uuid CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE revision ADD version_tag VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE content_type ADD parentField VARCHAR(100) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`');
        $this->addSql('ALTER TABLE content_type DROP version_tags, DROP version_date_from_field, DROP version_date_to_field');
        $this->addSql('ALTER TABLE revision DROP version_uuid');
        $this->addSql('ALTER TABLE revision DROP version_tag');
    }
}
