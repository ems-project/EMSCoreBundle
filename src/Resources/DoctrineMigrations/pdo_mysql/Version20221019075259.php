<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use EMS\Helpers\Standard\DateTime;
use EMS\Helpers\Standard\Json;

final class Version20221019075259 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE task ADD created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE task ADD modified DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

        $result = $this->connection->executeQuery('select * from task');
        $format = $this->connection->getDatabasePlatform()->getDateTimeFormatString();

        while ($row = $result->fetchAssociative()) {
            $logs = Json::decode($row['logs']);

            $creationDate = \array_shift($logs)['date'];
            $modificationDate = \count($logs) > 0 ? \array_pop($logs)['date'] : $creationDate;

            $creationDateTime = DateTime::createFromFormat($creationDate, \DateTimeInterface::ATOM);
            $modificationDateTime = DateTime::createFromFormat($modificationDate, \DateTimeInterface::ATOM);

            $this->addSql('UPDATE task SET created = :created, modified = :modified WHERE id = :id', [
                'created' => $creationDateTime->format($format),
                'modified' => $modificationDateTime->format($format),
                'id' => $row['id'],
            ]);
        }
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE task DROP created, DROP modified');
    }
}
