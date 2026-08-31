<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830150556 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Create an mcp_tool table';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );
        $this->addSql('CREATE TABLE mcp_tool (name VARCHAR(255) NOT NULL, label VARCHAR(255) NOT NULL, enabled TINYINT DEFAULT 1 NOT NULL, role VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, input_schema LONGTEXT DEFAULT NULL, output_schema LONGTEXT DEFAULT NULL, response LONGTEXT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, id CHAR(36) NOT NULL, UNIQUE INDEX UNIQ_2444886D5E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8mb4_unicode_ci`');
    }
    
    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );
        $this->addSql('DROP TABLE mcp_tool');
    }
}
