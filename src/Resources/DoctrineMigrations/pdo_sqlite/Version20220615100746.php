<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220615100746 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TEMPORARY TABLE __temp__log_message AS SELECT id, created, modified, message, context, level, level_name, channel, extra, formatted, username, impersonator FROM log_message');
        $this->addSql('DROP TABLE log_message');
        $this->addSql('CREATE TABLE log_message (id CHAR(36) NOT NULL COLLATE BINARY --(DC2Type:uuid)
        , created DATETIME NOT NULL, modified DATETIME NOT NULL, message CLOB NOT NULL COLLATE BINARY, level SMALLINT NOT NULL, level_name VARCHAR(50) NOT NULL COLLATE BINARY, channel VARCHAR(255) NOT NULL COLLATE BINARY, formatted CLOB NOT NULL COLLATE BINARY, username VARCHAR(255) DEFAULT NULL COLLATE BINARY, impersonator VARCHAR(255) DEFAULT NULL COLLATE BINARY, context CLOB NOT NULL --(DC2Type:json)
        , extra CLOB NOT NULL --(DC2Type:json)
        , ouuid CHAR(36) DEFAULT NULL --(DC2Type:uuid)
        , PRIMARY KEY(id))');
        $this->addSql('INSERT INTO log_message (id, created, modified, message, context, level, level_name, channel, extra, formatted, username, impersonator) SELECT id, created, modified, message, context, level, level_name, channel, extra, formatted, username, impersonator FROM __temp__log_message');
        $this->addSql('DROP TABLE __temp__log_message');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8E7008E82D7B983B ON log_message (ouuid)');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TEMPORARY TABLE __temp__log_message AS SELECT id, created, modified, message, context, level, level_name, channel, extra, formatted, username, impersonator FROM log_message');
        $this->addSql('DROP TABLE log_message');
        $this->addSql('CREATE TABLE log_message (id CHAR(36) NOT NULL --(DC2Type:uuid)
        , created DATETIME NOT NULL, modified DATETIME NOT NULL, message CLOB NOT NULL, level SMALLINT NOT NULL, level_name VARCHAR(50) NOT NULL, channel VARCHAR(255) NOT NULL, formatted CLOB NOT NULL, username VARCHAR(255) DEFAULT NULL, impersonator VARCHAR(255) DEFAULT NULL, context CLOB NOT NULL COLLATE BINARY --(DC2Type:array)
        , extra CLOB NOT NULL COLLATE BINARY --(DC2Type:array)
        , PRIMARY KEY(id))');
        $this->addSql('INSERT INTO log_message (id, created, modified, message, context, level, level_name, channel, extra, formatted, username, impersonator) SELECT id, created, modified, message, context, level, level_name, channel, extra, formatted, username, impersonator FROM __temp__log_message');
        $this->addSql('DROP TABLE __temp__log_message');
    }
}
