<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20201103081655 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE content_type ADD version_tags JSON DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN content_type.version_tags IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE content_type ADD version_date_from_field VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD version_date_to_field VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type DROP parentfield');

        $this->addSql('ALTER TABLE revision ADD version_uuid UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE revision ADD version_tag VARCHAR(255) DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN revision.version_uuid IS \'(DC2Type:uuid)\'');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE content_type ADD parentfield VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type DROP version_date_from_field');
        $this->addSql('ALTER TABLE content_type DROP version_date_to_field');
        $this->addSql('ALTER TABLE content_type DROP version_tags');

        $this->addSql('ALTER TABLE revision DROP version_uuid');
        $this->addSql('ALTER TABLE revision DROP version_tag');
    }
}
