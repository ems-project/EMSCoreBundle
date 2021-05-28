<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210111193957 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('ALTER TABLE channel ADD COLUMN alias VARCHAR(255) DEFAULT \'alias\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TEMPORARY TABLE __temp__channel AS SELECT id, created, modified, name, public, label, options, order_key FROM channel');
        $this->addSql('DROP TABLE channel');
        $this->addSql('CREATE TABLE channel (id CHAR(36) NOT NULL --(DC2Type:uuid)
        , created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL, public BOOLEAN DEFAULT \'0\' NOT NULL, label VARCHAR(255) NOT NULL, options CLOB DEFAULT NULL --(DC2Type:json)
        , order_key INTEGER NOT NULL, PRIMARY KEY(id))');
        $this->addSql('INSERT INTO channel (id, created, modified, name, public, label, options, order_key) SELECT id, created, modified, name, public, label, options, order_key FROM __temp__channel');
        $this->addSql('DROP TABLE __temp__channel');
    }
}
