<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20210102164524 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE channel (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL, public TINYINT(1) DEFAULT \'0\' NOT NULL, label VARCHAR(255) NOT NULL, options LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\', order_key INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE job DROP arguments, DROP service');
        $this->addSql('ALTER TABLE form_submission CHANGE label label VARCHAR(255) NOT NULL, CHANGE deadline_date deadline_date VARCHAR(255) NOT NULL, CHANGE expire_date expire_date DATE NOT NULL');
        $this->addSql('DROP INDEX asset_key_index ON asset_storage');
        $this->addSql('ALTER TABLE asset_storage DROP context, DROP last_update_date, CHANGE hash hash VARCHAR(1024) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_37945A62D1B862B8 ON asset_storage (hash)');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE channel');
        $this->addSql('DROP INDEX UNIQ_37945A62D1B862B8 ON asset_storage');
        $this->addSql('ALTER TABLE asset_storage ADD context VARCHAR(128) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, ADD last_update_date INT NOT NULL, CHANGE hash hash VARCHAR(128) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`');
        $this->addSql('CREATE UNIQUE INDEX asset_key_index ON asset_storage (hash, context)');
        $this->addSql('ALTER TABLE form_submission CHANGE expire_date expire_date DATE DEFAULT NULL, CHANGE label label VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, CHANGE deadline_date deadline_date VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`');
        $this->addSql('ALTER TABLE job ADD arguments LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci` COMMENT \'(DC2Type:json_array)\', ADD service VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`');
    }
}
