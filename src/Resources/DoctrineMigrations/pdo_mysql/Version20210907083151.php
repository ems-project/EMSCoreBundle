<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20210907083151 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE environment ADD label VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE managed_alias ADD label VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE revision CHANGE task_planned_ids task_planned_ids LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE task_approved_ids task_approved_ids LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE task CHANGE logs logs LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\'');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE environment DROP label');
        $this->addSql('ALTER TABLE managed_alias DROP label');
        $this->addSql('ALTER TABLE revision CHANGE task_planned_ids task_planned_ids LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE task_approved_ids task_approved_ids LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE task CHANGE logs logs LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`');
    }
}
