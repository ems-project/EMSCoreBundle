<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20210809075429 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('CREATE TABLE task (id UUID NOT NULL, title VARCHAR(255) NOT NULL, status VARCHAR(25) NOT NULL, deadline TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, assignee TEXT NOT NULL, description TEXT NOT NULL, logs JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN task.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN task.deadline IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE revision ADD task_current_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE revision ADD task_planned_ids JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE revision ADD task_approved_ids JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE revision ADD owner TEXT DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN revision.task_current_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE revision ADD CONSTRAINT FK_6D6315CCE99931F3 FOREIGN KEY (task_current_id) REFERENCES task (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_6D6315CCE99931F3 ON revision (task_current_id)');
        $this->addSql('ALTER TABLE content_type ADD owner_role VARCHAR(100) DEFAULT NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE revision DROP CONSTRAINT FK_6D6315CCE99931F3');
        $this->addSql('DROP TABLE task');
        $this->addSql('ALTER TABLE content_type DROP owner_role');
        $this->addSql('DROP INDEX IDX_6D6315CCE99931F3');
        $this->addSql('ALTER TABLE revision DROP task_current_id');
        $this->addSql('ALTER TABLE revision DROP task_planned_ids');
        $this->addSql('ALTER TABLE revision DROP task_approved_ids');
        $this->addSql('ALTER TABLE revision DROP owner');
    }
}
