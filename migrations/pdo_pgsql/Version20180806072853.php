<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20180806072853 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('COMMENT ON COLUMN "user".circles IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN revision.raw_data IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN revision.auto_save IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN environment.circles IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN field_type.options IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN template.circles_to IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN view.options IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN analyzer.options IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN filter.options IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE search ADD content_type_id BIGINT DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN search.environments IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN search.contentTypes IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE search ADD CONSTRAINT FK_B4F0DBA71A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B4F0DBA71A445520 ON search (content_type_id)');
        $this->addSql('COMMENT ON COLUMN i18n.content IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN job.arguments IS \'(DC2Type:json_array)\'');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('COMMENT ON COLUMN analyzer.options IS NULL');
        $this->addSql('COMMENT ON COLUMN filter.options IS NULL');
        $this->addSql('COMMENT ON COLUMN i18n.content IS NULL');
        $this->addSql('COMMENT ON COLUMN job.arguments IS NULL');
        $this->addSql('COMMENT ON COLUMN field_type.options IS NULL');
        $this->addSql('COMMENT ON COLUMN environment.circles IS NULL');
        $this->addSql('ALTER TABLE search DROP CONSTRAINT FK_B4F0DBA71A445520');
        $this->addSql('DROP INDEX UNIQ_B4F0DBA71A445520');
        $this->addSql('ALTER TABLE search DROP content_type_id');
        $this->addSql('COMMENT ON COLUMN search.environments IS NULL');
        $this->addSql('COMMENT ON COLUMN search.contenttypes IS NULL');
        $this->addSql('COMMENT ON COLUMN view.options IS NULL');
        $this->addSql('COMMENT ON COLUMN "user".circles IS NULL');
        $this->addSql('COMMENT ON COLUMN revision.raw_data IS NULL');
        $this->addSql('COMMENT ON COLUMN revision.auto_save IS NULL');
        $this->addSql('COMMENT ON COLUMN template.circles_to IS NULL');
    }
}
