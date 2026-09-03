<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831190222 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Add a description field to the environments';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );
        $this->addSql('ALTER TABLE environment ADD description LONGTEXT DEFAULT NULL');
        $this->addSql('CREATE TABLE mcp_resource (name VARCHAR(255) NOT NULL, label VARCHAR(255) NOT NULL, uri VARCHAR(1024) NOT NULL, enabled TINYINT DEFAULT 1 NOT NULL, role VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, mime_type VARCHAR(255) NOT NULL, response LONGTEXT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, id CHAR(36) NOT NULL, UNIQUE INDEX UNIQ_860F7BF05E237E06 (name), UNIQUE INDEX UNIQ_860F7BF0841CB121 (uri), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci`');
        $this->addSql('CREATE TABLE mcp_prompt (name VARCHAR(255) NOT NULL, label VARCHAR(255) NOT NULL, enabled TINYINT DEFAULT 1 NOT NULL, role VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, arguments LONGTEXT DEFAULT NULL, response LONGTEXT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, id CHAR(36) NOT NULL, UNIQUE INDEX UNIQ_89D9F825E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );
        $this->addSql('ALTER TABLE environment DROP description');
        $this->addSql('DROP TABLE mcp_resource');
        $this->addSql('DROP TABLE mcp_prompt');
    }
}
