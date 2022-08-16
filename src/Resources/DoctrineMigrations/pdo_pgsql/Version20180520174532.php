<?php

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180520174532 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE cache_asset_extractor_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE cache_asset_extractor (id INT NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, hash VARCHAR(255) NOT NULL, data JSON DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_83D3C2A4D1B862B8 ON cache_asset_extractor (hash)');
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP SEQUENCE cache_asset_extractor_id_seq CASCADE');
        $this->addSql('DROP TABLE cache_asset_extractor');
    }
}
