<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20221023190143 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE content_type DROP userField, DROP dateField, DROP startDateField, DROP endDateField, DROP locationField, DROP ouuidField, DROP videoField');
        $this->addSql('ALTER TABLE task CHANGE modified modified DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE content_type ADD userField VARCHAR(100) DEFAULT NULL, ADD dateField VARCHAR(100) DEFAULT NULL, ADD startDateField VARCHAR(100) DEFAULT NULL, ADD endDateField VARCHAR(100) DEFAULT NULL, ADD locationField VARCHAR(100) DEFAULT NULL, ADD ouuidField VARCHAR(100) DEFAULT NULL, ADD videoField VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE task CHANGE modified modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
    }
}
