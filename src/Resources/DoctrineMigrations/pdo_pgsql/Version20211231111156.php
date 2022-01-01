<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20211231111156 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE log (id UUID NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, message TEXT NOT NULL, context TEXT NOT NULL, level SMALLINT NOT NULL, level_name VARCHAR(50) NOT NULL, channel VARCHAR(255) NOT NULL, extra TEXT NOT NULL, formatted TEXT NOT NULL, username VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN log.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN log.context IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN log.extra IS \'(DC2Type:array)\'');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE log');
    }
}
