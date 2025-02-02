<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20201215133710 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('UPDATE form_submission SET label = \'submission\' WHERE label IS NULL');
        $this->addSql('UPDATE form_submission SET deadline_date = \'01/01/2054\' WHERE deadline_date IS NULL');
        $this->addSql('UPDATE form_submission SET expire_date = \'01/01/2054\' WHERE expire_date IS NULL');

        $this->addSql('CREATE TABLE channel (id UUID NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, name VARCHAR(255) NOT NULL, public BOOLEAN DEFAULT \'false\' NOT NULL, label VARCHAR(255) NOT NULL, options JSON DEFAULT NULL, order_key INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN channel.id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE job DROP arguments');
        $this->addSql('ALTER TABLE job DROP service');
        $this->addSql('ALTER TABLE form_submission ALTER label SET NOT NULL');
        $this->addSql('ALTER TABLE form_submission ALTER deadline_date SET NOT NULL');
        $this->addSql('ALTER TABLE form_submission ALTER expire_date SET NOT NULL');
        $this->addSql('DROP INDEX IF EXISTS asset_key_index');
        $this->addSql('ALTER TABLE asset_storage DROP context');
        $this->addSql('ALTER TABLE asset_storage DROP last_update_date');
        $this->addSql('ALTER TABLE asset_storage ALTER hash TYPE VARCHAR(1024)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_37945A62D1B862B8 ON asset_storage (hash)');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('DROP TABLE channel');
        $this->addSql('DROP INDEX UNIQ_37945A62D1B862B8');
        $this->addSql('ALTER TABLE asset_storage ADD context VARCHAR(128) DEFAULT NULL');
        $this->addSql('ALTER TABLE asset_storage ADD last_update_date INT NOT NULL');
        $this->addSql('ALTER TABLE asset_storage ALTER hash TYPE VARCHAR(128)');
        $this->addSql('CREATE UNIQUE INDEX asset_key_index ON asset_storage (hash, context)');
        $this->addSql('ALTER TABLE form_submission ALTER expire_date DROP NOT NULL');
        $this->addSql('ALTER TABLE form_submission ALTER label DROP NOT NULL');
        $this->addSql('ALTER TABLE form_submission ALTER deadline_date DROP NOT NULL');
        $this->addSql('ALTER TABLE job ADD arguments JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE job ADD service VARCHAR(255) DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN job.arguments IS \'(DC2Type:json_array)\'');
    }
}
