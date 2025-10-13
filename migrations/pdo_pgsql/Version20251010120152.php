<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251010120152 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Remove useless lockBy and lockUntil ContentType\'s attributes';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );
        $this->addSql('ALTER TABLE content_type DROP lockby');
        $this->addSql('ALTER TABLE content_type DROP lockuntil');
    }
    
    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );
        $this->addSql('ALTER TABLE content_type ADD lockby VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD lockuntil TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }
}
