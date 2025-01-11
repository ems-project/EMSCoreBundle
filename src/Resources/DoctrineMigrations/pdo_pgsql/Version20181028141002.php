<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20181028141002 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE search_field_option ADD contentTypes JSON NOT NULL');
        $this->addSql('ALTER TABLE search_field_option ADD operators JSON NOT NULL');
        $this->addSql('COMMENT ON COLUMN search_field_option.contentTypes IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN search_field_option.operators IS \'(DC2Type:json_array)\'');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE search_field_option DROP contentTypes');
        $this->addSql('ALTER TABLE search_field_option DROP operators');
    }
}
