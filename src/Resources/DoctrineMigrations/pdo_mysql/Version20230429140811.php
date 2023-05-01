<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230429140811 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a table in order to persist data (Store Data)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE store_data (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', `key` VARCHAR(2048) NOT NULL, data LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\', created DATETIME NOT NULL, modified DATETIME NOT NULL, UNIQUE INDEX UNIQ_4F4A5DAD8A90ABA9 (`key`), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE store_data');
    }
}
