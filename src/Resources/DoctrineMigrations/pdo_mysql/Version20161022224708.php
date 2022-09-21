<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20161022224708 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE search CHANGE sort_by sort_by VARCHAR(100) DEFAULT NULL, CHANGE sort_order sort_order VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE search CHANGE sort_by sort_by VARCHAR(100) NOT NULL COLLATE utf8_unicode_ci, CHANGE sort_order sort_order VARCHAR(100) NOT NULL COLLATE utf8_unicode_ci');
    }
}
