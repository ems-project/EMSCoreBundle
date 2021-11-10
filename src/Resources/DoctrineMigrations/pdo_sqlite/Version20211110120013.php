<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20211110120013 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP INDEX IDX_FEFDAB8E1A445520');
        $this->addSql('CREATE TEMPORARY TABLE __temp__view AS SELECT id, content_type_id, created, modified, name, type, icon, options, orderKey, public FROM "view"');
        $this->addSql('DROP TABLE "view"');
        $this->addSql('CREATE TABLE "view" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL COLLATE BINARY, type VARCHAR(255) NOT NULL COLLATE BINARY, icon VARCHAR(255) DEFAULT NULL COLLATE BINARY, options CLOB DEFAULT NULL COLLATE BINARY --(DC2Type:json_array)
        , public BOOLEAN DEFAULT \'0\' NOT NULL, order_key INTEGER NOT NULL, role VARCHAR(100) DEFAULT NULL, CONSTRAINT FK_FEFDAB8E1A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "view" (id, content_type_id, created, modified, name, type, icon, options, order_key, public) SELECT id, content_type_id, created, modified, name, type, icon, options, orderKey, public FROM __temp__view');
        $this->addSql('DROP TABLE __temp__view');
        $this->addSql('CREATE INDEX IDX_FEFDAB8E1A445520 ON "view" (content_type_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP INDEX IDX_FEFDAB8E1A445520');
        $this->addSql('CREATE TEMPORARY TABLE __temp__view AS SELECT id, content_type_id, created, modified, name, type, icon, options, order_key, public FROM "view"');
        $this->addSql('DROP TABLE "view"');
        $this->addSql('CREATE TABLE "view" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, icon VARCHAR(255) DEFAULT NULL, options CLOB DEFAULT NULL --(DC2Type:json_array)
        , public BOOLEAN DEFAULT \'0\' NOT NULL, orderKey INTEGER NOT NULL)');
        $this->addSql('INSERT INTO "view" (id, content_type_id, created, modified, name, type, icon, options, orderKey, public) SELECT id, content_type_id, created, modified, name, type, icon, options, order_key, public FROM __temp__view');
        $this->addSql('DROP TABLE __temp__view');
        $this->addSql('CREATE INDEX IDX_FEFDAB8E1A445520 ON "view" (content_type_id)');
    }
}
