<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180408102537 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE single_type_index (id BIGINT AUTO_INCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, environment_id INT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL, INDEX IDX_FEAD46B31A445520 (content_type_id), INDEX IDX_FEAD46B3903E3A94 (environment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE single_type_index ADD CONSTRAINT FK_FEAD46B31A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id)');
        $this->addSql('ALTER TABLE single_type_index ADD CONSTRAINT FK_FEAD46B3903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE single_type_index');
    }
}
