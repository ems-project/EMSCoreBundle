<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180131091124 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE managed_alias (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, alias VARCHAR(255) NOT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, color VARCHAR(50) DEFAULT NULL, extra LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_CCBD025A5E237E06 (name), UNIQUE INDEX UNIQ_CCBD025AE16C6B94 (alias), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE uploaded_asset CHANGE size size BIGINT NOT NULL, CHANGE uploaded uploaded BIGINT NOT NULL');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE managed_alias');
        $this->addSql('ALTER TABLE uploaded_asset CHANGE size size INT NOT NULL, CHANGE uploaded uploaded INT NOT NULL');
    }
}
