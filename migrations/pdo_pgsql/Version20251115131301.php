<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251115131301 extends AbstractMigration
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
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );
        $this->addSql('CREATE TABLE webhook_subscription (endpoint_url VARCHAR(255) NOT NULL, secret VARCHAR(255) NOT NULL, error_message VARCHAR(2048), events JSON NOT NULL, enabled BOOLEAN DEFAULT true NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
    }
    
    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );
        $this->addSql('DROP TABLE webhook_subscription');
    }
}
