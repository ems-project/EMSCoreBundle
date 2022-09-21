<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20161022084319 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE search DROP boolean');
        $this->addSql('ALTER TABLE search_filter CHANGE inverted boolean_clause TINYINT(1) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE search ADD boolean VARCHAR(100) NOT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE search_filter CHANGE boolean_clause inverted TINYINT(1) DEFAULT NULL');
    }
}
