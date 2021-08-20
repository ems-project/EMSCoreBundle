<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20210818123844 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TEMPORARY TABLE __temp__task AS SELECT id, title, status, deadline, assignee, description, logs FROM task');
        $this->addSql('DROP TABLE task');
        $this->addSql('CREATE TABLE task (id CHAR(36) NOT NULL COLLATE BINARY --(DC2Type:uuid)
        , title VARCHAR(255) NOT NULL COLLATE BINARY, status VARCHAR(25) NOT NULL COLLATE BINARY, assignee CLOB NOT NULL COLLATE BINARY, logs CLOB NOT NULL COLLATE BINARY --(DC2Type:json)
        , deadline DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , description CLOB DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('INSERT INTO task (id, title, status, deadline, assignee, description, logs) SELECT id, title, status, deadline, assignee, description, logs FROM __temp__task');
        $this->addSql('DROP TABLE __temp__task');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__task AS SELECT id, title, status, deadline, assignee, description, logs FROM task');
        $this->addSql('DROP TABLE task');
        $this->addSql('CREATE TABLE task (id CHAR(36) NOT NULL --(DC2Type:uuid)
        , title VARCHAR(255) NOT NULL, status VARCHAR(25) NOT NULL, assignee CLOB NOT NULL, logs CLOB NOT NULL --(DC2Type:json)
        , deadline DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , description CLOB NOT NULL COLLATE BINARY, PRIMARY KEY(id))');
        $this->addSql('INSERT INTO task (id, title, status, deadline, assignee, description, logs) SELECT id, title, status, deadline, assignee, description, logs FROM __temp__task');
        $this->addSql('DROP TABLE __temp__task');
    }
}
