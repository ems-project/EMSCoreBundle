<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20221222131342 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $query = <<<QUERY
            select id, task_planned_ids from revision 
            where task_planned_ids is not null and task_planned_ids != '[]'
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
        // this down() migration is auto-generated, please modify it to your needs
    }
}
