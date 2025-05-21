<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250521100623 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Add an index for the job tag column';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );
        
        $this->addSql(<<<'SQL'
            CREATE INDEX tag_idx ON job (tag)
        SQL);
    }
    
    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );
        
        $this->addSql(<<<'SQL'
            DROP INDEX tag_idx
        SQL);
    }
}
