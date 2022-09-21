<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20211102124632 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('CREATE TABLE dashboard (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL, icon TINYTEXT NOT NULL, label VARCHAR(255) NOT NULL, sidebar_menu TINYINT(1) DEFAULT \'1\' NOT NULL, notification_menu TINYINT(1) DEFAULT \'0\' NOT NULL, landing_page TINYINT(1) DEFAULT \'0\' NOT NULL, quick_search TINYINT(1) DEFAULT \'0\' NOT NULL, type VARCHAR(2048) NOT NULL, role VARCHAR(100) NOT NULL, color VARCHAR(50) DEFAULT NULL, options LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\', order_key INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('DROP TABLE dashboard');
    }
}
