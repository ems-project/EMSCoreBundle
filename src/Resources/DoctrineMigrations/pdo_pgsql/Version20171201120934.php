<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20171201120934 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE revision ALTER labelfield TYPE TEXT');
        $this->addSql('ALTER TABLE revision ALTER labelfield DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE revision ALTER labelField TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE revision ALTER labelField DROP DEFAULT');
    }
}
