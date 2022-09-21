<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20200226102824 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE content_type ADD searchLinkDisplayRole VARCHAR(255) DEFAULT \'ROLE_USER\' NOT NULL, ADD createLinkDisplayRole VARCHAR(255) DEFAULT \'ROLE_USER\' NOT NULL');
        $this->addSql('ALTER TABLE environment ADD snapshot TINYINT(1) DEFAULT \'0\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE content_type DROP searchLinkDisplayRole, DROP createLinkDisplayRole');
        $this->addSql('ALTER TABLE environment DROP snapshot');
    }
}
