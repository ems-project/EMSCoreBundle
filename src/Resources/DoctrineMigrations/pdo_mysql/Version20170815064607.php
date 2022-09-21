<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20170815064607 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE analyzer ADD dirty TINYINT(1) NOT NULL, ADD `label` VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE filter ADD dirty TINYINT(1) NOT NULL, ADD `label` VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE analyzer DROP dirty, DROP `label`');
        $this->addSql('ALTER TABLE filter DROP dirty, DROP `label`');
    }
}
