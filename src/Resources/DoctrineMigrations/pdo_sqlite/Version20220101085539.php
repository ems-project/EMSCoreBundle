<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220101085539 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TABLE log (id CHAR(36) NOT NULL --(DC2Type:uuid)
        , created DATETIME NOT NULL, modified DATETIME NOT NULL, message CLOB NOT NULL, context CLOB NOT NULL --(DC2Type:array)
        , level SMALLINT NOT NULL, level_name VARCHAR(50) NOT NULL, channel VARCHAR(255) NOT NULL, extra CLOB NOT NULL --(DC2Type:array)
        , formatted CLOB NOT NULL, username VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE environment ADD COLUMN label VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE managed_alias ADD COLUMN label VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP TABLE log');
        $this->addSql('DROP INDEX UNIQ_4626DE225E237E06');
        $this->addSql('CREATE TEMPORARY TABLE __temp__environment AS SELECT id, created, modified, name, alias, color, baseUrl, managed, snapshot, circles, in_default_search, extra, order_key, update_referrers FROM environment');
        $this->addSql('DROP TABLE environment');
        $this->addSql('CREATE TABLE environment (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL, alias VARCHAR(255) NOT NULL, color VARCHAR(50) DEFAULT NULL, baseUrl VARCHAR(1024) DEFAULT NULL, managed BOOLEAN NOT NULL, snapshot BOOLEAN DEFAULT \'0\' NOT NULL, circles CLOB DEFAULT NULL --(DC2Type:json_array)
        , in_default_search BOOLEAN DEFAULT NULL, extra CLOB DEFAULT NULL, order_key INTEGER DEFAULT NULL, update_referrers BOOLEAN DEFAULT \'0\' NOT NULL)');
        $this->addSql('INSERT INTO environment (id, created, modified, name, alias, color, baseUrl, managed, snapshot, circles, in_default_search, extra, order_key, update_referrers) SELECT id, created, modified, name, alias, color, baseUrl, managed, snapshot, circles, in_default_search, extra, order_key, update_referrers FROM __temp__environment');
        $this->addSql('DROP TABLE __temp__environment');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4626DE225E237E06 ON environment (name)');
        $this->addSql('DROP INDEX UNIQ_CCBD025A5E237E06');
        $this->addSql('DROP INDEX UNIQ_CCBD025AE16C6B94');
        $this->addSql('CREATE TEMPORARY TABLE __temp__managed_alias AS SELECT id, name, alias, created, modified, color, extra FROM managed_alias');
        $this->addSql('DROP TABLE managed_alias');
        $this->addSql('CREATE TABLE managed_alias (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, alias VARCHAR(255) NOT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, color VARCHAR(50) DEFAULT NULL, extra CLOB DEFAULT NULL)');
        $this->addSql('INSERT INTO managed_alias (id, name, alias, created, modified, color, extra) SELECT id, name, alias, created, modified, color, extra FROM __temp__managed_alias');
        $this->addSql('DROP TABLE __temp__managed_alias');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CCBD025A5E237E06 ON managed_alias (name)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CCBD025AE16C6B94 ON managed_alias (alias)');
    }
}
