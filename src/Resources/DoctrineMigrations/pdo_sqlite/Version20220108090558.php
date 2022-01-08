<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220108090558 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('ALTER TABLE log_message ADD COLUMN impersonator VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TEMPORARY TABLE __temp__log_message AS SELECT id, created, modified, message, context, level, level_name, channel, extra, formatted, username FROM log_message');
        $this->addSql('DROP TABLE log_message');
        $this->addSql('CREATE TABLE log_message (id CHAR(36) NOT NULL --(DC2Type:uuid)
        , created DATETIME NOT NULL, modified DATETIME NOT NULL, message CLOB NOT NULL, context CLOB NOT NULL --(DC2Type:array)
        , level SMALLINT NOT NULL, level_name VARCHAR(50) NOT NULL, channel VARCHAR(255) NOT NULL, extra CLOB NOT NULL --(DC2Type:array)
        , formatted CLOB NOT NULL, username VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('INSERT INTO log_message (id, created, modified, message, context, level, level_name, channel, extra, formatted, username) SELECT id, created, modified, message, context, level, level_name, channel, extra, formatted, username FROM __temp__log_message');
        $this->addSql('DROP TABLE __temp__log_message');
    }
}
