<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251115132240 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Create a webhook_subscription table';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );
        $this->addSql('CREATE TABLE webhook_subscription (endpoint_url VARCHAR(255) NOT NULL, secret VARCHAR(255) NOT NULL, error_message VARCHAR(2048), events JSON NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, id CHAR(36) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci`');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );
        $this->addSql('DROP TABLE webhook_subscription');
    }
}
