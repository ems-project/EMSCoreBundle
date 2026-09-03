<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831182724 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a description field to the environments';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );
        $this->addSql('ALTER TABLE environment ADD description TEXT DEFAULT NULL');
        $this->addSql('CREATE TABLE mcp_resource (name VARCHAR(255) NOT NULL, label VARCHAR(255) NOT NULL, uri VARCHAR(1024) NOT NULL, enabled BOOLEAN DEFAULT true NOT NULL, role VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, mime_type VARCHAR(255) NOT NULL, response TEXT DEFAULT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_860F7BF05E237E06 ON mcp_resource (name)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_860F7BF0841CB121 ON mcp_resource (uri)');
        $this->addSql('CREATE TABLE mcp_prompt (name VARCHAR(255) NOT NULL, label VARCHAR(255) NOT NULL, enabled BOOLEAN DEFAULT true NOT NULL, role VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, arguments TEXT DEFAULT NULL, response TEXT DEFAULT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_89D9F825E237E06 ON mcp_prompt (name)');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );
        $this->addSql('ALTER TABLE environment DROP description');
        $this->addSql('DROP TABLE mcp_resource');
        $this->addSql('DROP TABLE mcp_prompt');
    }
}
