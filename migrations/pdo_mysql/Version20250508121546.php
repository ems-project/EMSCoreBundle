<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250508121546 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Add action table';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );
        
        $this->addSql(<<<'SQL'
            CREATE TABLE cache_action (id CHAR(36) NOT NULL, request JSON NOT NULL, request_hash VARCHAR(255) NOT NULL, response JSON DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, UNIQUE INDEX uniq_request_hash (request_hash), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci`
        SQL);
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql(<<<'SQL'
            DROP TABLE cache_action
        SQL);
    }
}
