<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20221219144622 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE task ADD created_by TEXT DEFAULT NULL');
        $this->addSql("UPDATE task SET created_by = logs->0->>'username'");
        $this->addSql('ALTER TABLE task ALTER created_by SET NOT NULL');

        $this->addSql('ALTER TABLE revision DROP owner');
        $this->addSql('ALTER TABLE content_type ADD settings JSON DEFAULT NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE task DROP created_by');
        $this->addSql('ALTER TABLE revision ADD owner TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type DROP settings');
    }
}
