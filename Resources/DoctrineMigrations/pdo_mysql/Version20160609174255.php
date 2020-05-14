<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160609174255 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE data_value');
        $this->addSql('ALTER TABLE data_field ADD raw_data LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json_array)\'');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE data_value (id INT AUTO_INCREMENT NOT NULL, data_field_id INT NOT NULL, integer_value BIGINT DEFAULT NULL, float_value DOUBLE PRECISION DEFAULT NULL, date_value DATETIME DEFAULT NULL, text_value LONGTEXT DEFAULT NULL COLLATE utf8_unicode_ci, sha1 VARCHAR(20) DEFAULT NULL COLLATE utf8_unicode_ci, index_key INT NOT NULL, INDEX IDX_53C894AB8EE9CE6C (data_field_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE data_value ADD CONSTRAINT FK_53C894AB8EE9CE6C FOREIGN KEY (data_field_id) REFERENCES data_field (id)');
        $this->addSql('ALTER TABLE data_field DROP raw_data');
    }
}
