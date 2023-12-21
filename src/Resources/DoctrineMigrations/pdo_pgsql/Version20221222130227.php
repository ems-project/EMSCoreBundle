<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20221222130227 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $query = <<<QUERY
            select id, task_planned_ids from revision 
            where task_planned_ids is not null and task_planned_ids::text != '[]'
QUERY;

        $rows = $this->connection->executeQuery($query)->iterateAssociative();

        foreach ($rows as $row) {
            $wrongJson = \json_decode((string) $row['task_planned_ids'], true);
            $correctJson = \json_encode(\array_values($wrongJson));

            $this->connection->update('revision', ['task_planned_ids' => $correctJson], ['id' => $row['id']]);
        }
    }

    public function down(Schema $schema): void
    {
    }
}
