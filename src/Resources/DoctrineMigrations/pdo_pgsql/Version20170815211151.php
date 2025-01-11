<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20170815211151 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('CREATE SEQUENCE filter_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE filter (id INT NOT NULL, name VARCHAR(255) NOT NULL, dirty BOOLEAN NOT NULL, label VARCHAR(255) NOT NULL, options JSON NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7FC45F1D5E237E06 ON filter (name)');
        $this->addSql('ALTER TABLE analyzer ADD dirty BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE analyzer ADD label VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE analyzer RENAME COLUMN mofified TO modified');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('DROP SEQUENCE filter_id_seq CASCADE');
        $this->addSql('DROP TABLE filter');
        $this->addSql('ALTER TABLE analyzer DROP dirty');
        $this->addSql('ALTER TABLE analyzer DROP label');
        $this->addSql('ALTER TABLE analyzer RENAME COLUMN modified TO mofified');
    }
}
