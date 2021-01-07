<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210107173649 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');
        $this->addSql('ALTER TABLE environment ADD COLUMN update_referrers BOOLEAN DEFAULT \'0\' NOT NULL');

        $this->addSql('DROP TABLE single_type_index');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TEMPORARY TABLE __temp__environment AS SELECT id, created, modified, name, alias, color, baseUrl, managed, snapshot, circles, in_default_search, extra, order_key FROM environment');
        $this->addSql('DROP TABLE environment');
        $this->addSql('CREATE TABLE environment (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL, alias VARCHAR(255) NOT NULL, color VARCHAR(50) DEFAULT NULL, baseUrl VARCHAR(1024) DEFAULT NULL, managed BOOLEAN NOT NULL, snapshot BOOLEAN DEFAULT \'0\' NOT NULL, circles CLOB DEFAULT NULL --(DC2Type:json_array)
        , in_default_search BOOLEAN DEFAULT NULL, extra CLOB DEFAULT NULL, order_key INTEGER DEFAULT NULL)');
        $this->addSql('INSERT INTO environment (id, created, modified, name, alias, color, baseUrl, managed, snapshot, circles, in_default_search, extra, order_key) SELECT id, created, modified, name, alias, color, baseUrl, managed, snapshot, circles, in_default_search, extra, order_key FROM __temp__environment');
        $this->addSql('DROP TABLE __temp__environment');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4626DE225E237E06 ON environment (name)');
        $this->addSql('DROP INDEX IDX_895F7B701DFA7C8F');
        $this->addSql('DROP INDEX IDX_895F7B70903E3A94');

        $this->addSql('CREATE TABLE single_type_index (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, environment_id INTEGER DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL COLLATE BINARY)');
        $this->addSql('CREATE INDEX IDX_FEAD46B31A445520 ON single_type_index (content_type_id)');
        $this->addSql('CREATE INDEX IDX_FEAD46B3903E3A94 ON single_type_index (environment_id)');
    }
}
