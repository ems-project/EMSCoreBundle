<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20211102082914 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');
        $this->addSql('CREATE TABLE dashboard (id UUID NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, name VARCHAR(255) NOT NULL, icon TEXT NOT NULL, label VARCHAR(255) NOT NULL, sidebar_menu BOOLEAN DEFAULT \'true\' NOT NULL, notification_menu BOOLEAN DEFAULT \'false\' NOT NULL, type VARCHAR(2048) NOT NULL, role VARCHAR(100) NOT NULL, color VARCHAR(50) DEFAULT NULL, options JSON DEFAULT NULL, order_key INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN dashboard.id IS \'(DC2Type:uuid)\'');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');
        $this->addSql('DROP TABLE dashboard');
    }
}
