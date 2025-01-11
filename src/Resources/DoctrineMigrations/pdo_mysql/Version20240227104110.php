<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240227104110 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Add revision ouuid on task table and version_next_tag on revision';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('UPDATE revision SET task_current_id = NULL, task_planned_ids = NULL, task_approved_ids = NULL');
        $this->addSql('DELETE FROM task');
        $this->addSql('ALTER TABLE task ADD revision_ouuid VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE revision ADD version_next_tag VARCHAR(255) DEFAULT NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE task DROP revision_ouuid');
        $this->addSql('ALTER TABLE revision DROP version_next_tag');
    }
}
