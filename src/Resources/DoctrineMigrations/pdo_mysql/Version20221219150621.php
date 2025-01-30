<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20221219150621 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE task ADD created_by LONGTEXT DEFAULT NULL');
        $this->addSql("update task set task.created_by = JSON_UNQUOTE(JSON_EXTRACT(logs, '$[0].username'))");
        $this->addSql('ALTER TABLE task MODIFY created_by LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE revision DROP owner');
        $this->addSql('ALTER TABLE content_type ADD settings LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\'');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE task DROP created_by');
        $this->addSql('ALTER TABLE revision ADD owner LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type DROP settings');
    }
}
