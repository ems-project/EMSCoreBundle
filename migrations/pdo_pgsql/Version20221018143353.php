<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use EMS\Helpers\Standard\Json;

final class Version20221018143353 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE task ADD created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE task ADD modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP');

        $result = $this->connection->executeQuery('select * from task');
        while ($row = $result->fetchAssociative()) {
            $logs = Json::decode($row['logs']);

            $creationDate = \array_shift($logs)['date'];
            $modificationDate = \count($logs) > 0 ? \array_pop($logs)['date'] : $creationDate;

            $this->addSql('UPDATE task SET created = :created, modified = :modified WHERE id = :id', [
                'created' => $creationDate,
                'modified' => $modificationDate,
                'id' => $row['id'],
            ]);
        }
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE task DROP created');
        $this->addSql('ALTER TABLE task DROP modified');
    }
}
