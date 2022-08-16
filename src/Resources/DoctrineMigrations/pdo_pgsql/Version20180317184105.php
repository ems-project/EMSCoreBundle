<?php

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180317184105 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE sort_option_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE sort_option (id INT NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, name VARCHAR(255) NOT NULL, field TEXT NOT NULL, orderKey INT NOT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP SEQUENCE sort_option_id_seq CASCADE');
        $this->addSql('DROP TABLE sort_option');
    }
}
