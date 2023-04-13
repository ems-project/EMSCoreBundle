<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230413125840 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Force OUUID collate to utf8_bin';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );
        $this->addSql('ALTER TABLE revision CHANGE ouuid ouuid VARCHAR(255) DEFAULT NULL COLLATE `utf8_bin`');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );
        $this->addSql('ALTER TABLE revision CHANGE ouuid ouuid VARCHAR(255) DEFAULT NULL COLLATE `utf8_bin`');
    }
}
