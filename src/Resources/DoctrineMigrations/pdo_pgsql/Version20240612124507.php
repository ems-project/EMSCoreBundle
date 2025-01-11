<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240612124507 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Release revision add type and rename column';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE release_revision DROP CONSTRAINT fk_3663ceadfbfe9068');
        $this->addSql('DROP INDEX idx_3663ceadfbfe9068');
        $this->addSql('ALTER TABLE release_revision RENAME COLUMN revision_before_publish_id TO rollback_revision_id');
        $this->addSql('ALTER TABLE release_revision ADD CONSTRAINT FK_3663CEAD5B35C530 FOREIGN KEY (rollback_revision_id) REFERENCES revision (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_3663CEAD5B35C530 ON release_revision (rollback_revision_id)');

        $this->addSql('ALTER TABLE release_revision ADD type TEXT DEFAULT NULL');
        $this->addSql('UPDATE release_revision SET type = \'publish\' WHERE revision_id IS NOT NULL');
        $this->addSql('UPDATE release_revision SET type = \'unpublish\' WHERE revision_id IS NULL');
        $this->addSql('ALTER TABLE release_revision ALTER type SET NOT NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE release_revision DROP CONSTRAINT FK_3663CEAD5B35C530');
        $this->addSql('DROP INDEX IDX_3663CEAD5B35C530');
        $this->addSql('ALTER TABLE release_revision RENAME COLUMN rollback_revision_id TO revision_before_publish_id');
        $this->addSql('ALTER TABLE release_revision ADD CONSTRAINT fk_3663ceadfbfe9068 FOREIGN KEY (revision_before_publish_id) REFERENCES revision (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_3663ceadfbfe9068 ON release_revision (revision_before_publish_id)');

        $this->addSql('ALTER TABLE release_revision DROP type');
    }
}
