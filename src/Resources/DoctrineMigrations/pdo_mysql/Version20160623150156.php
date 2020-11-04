<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160623150156 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, template_id INT DEFAULT NULL, revision_id INT DEFAULT NULL, environment_id INT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, username VARCHAR(100) NOT NULL, status VARCHAR(20) NOT NULL, sent_timestamp DATETIME NOT NULL, response_text LONGTEXT DEFAULT NULL, response_timestamp DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_BF5476CA5DA0FB8 (template_id), UNIQUE INDEX UNIQ_BF5476CA1DFA7C8F (revision_id), UNIQUE INDEX UNIQ_BF5476CA903E3A94 (environment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA5DA0FB8 FOREIGN KEY (template_id) REFERENCES template (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA1DFA7C8F FOREIGN KEY (revision_id) REFERENCES revision (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE notification');
    }
}
