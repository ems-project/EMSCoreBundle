<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820095624 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Add USER_ROLE to roleless enabled users';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );
        $this->addSql('UPDATE "user" SET roles = \'["ROLE_USER"]\' WHERE enabled = true AND roles::jsonb = \'[]\'::jsonb;');
    }
    
    #[\Override]
    public function down(Schema $schema): void
    {
    }
}
