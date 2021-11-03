<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20211102124553 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');
        $this->addSql('CREATE TABLE dashboard (id CHAR(36) NOT NULL --(DC2Type:uuid)
        , created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL, icon CLOB NOT NULL, label VARCHAR(255) NOT NULL, sidebar_menu BOOLEAN DEFAULT \'1\' NOT NULL, notification_menu BOOLEAN DEFAULT \'0\' NOT NULL, landing_page BOOLEAN DEFAULT \'0\' NOT NULL, quick_search BOOLEAN DEFAULT \'0\' NOT NULL, type VARCHAR(2048) NOT NULL, role VARCHAR(100) NOT NULL, color VARCHAR(50) DEFAULT NULL, options CLOB DEFAULT NULL --(DC2Type:json)
        , order_key INTEGER NOT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');
        $this->addSql('DROP TABLE dashboard');
    }
}
