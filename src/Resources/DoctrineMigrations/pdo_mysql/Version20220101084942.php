<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220101084942 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE log (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', created DATETIME NOT NULL, modified DATETIME NOT NULL, message LONGTEXT NOT NULL, context LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', level SMALLINT NOT NULL, level_name VARCHAR(50) NOT NULL, channel VARCHAR(255) NOT NULL, extra LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', formatted LONGTEXT NOT NULL, username VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE log');
    }
}
