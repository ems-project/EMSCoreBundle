<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20170515114115 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE search CHANGE user username VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE job CHANGE user username VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE uploaded_asset CHANGE user username VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE job CHANGE username user VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE search CHANGE username user VARCHAR(100) NOT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE uploaded_asset CHANGE username user VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci');
    }
}
