<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160627135212 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE environment_template (template_id INT NOT NULL, environment_id INT NOT NULL, INDEX IDX_735C713F5DA0FB8 (template_id), INDEX IDX_735C713F903E3A94 (environment_id), PRIMARY KEY(template_id, environment_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE environment_template ADD CONSTRAINT FK_735C713F5DA0FB8 FOREIGN KEY (template_id) REFERENCES template (id)');
        $this->addSql('ALTER TABLE environment_template ADD CONSTRAINT FK_735C713F903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id)');
        $this->addSql('ALTER TABLE template DROP FOREIGN KEY FK_97601F83903E3A94');
        $this->addSql('DROP INDEX IDX_97601F83903E3A94 ON template');
        $this->addSql('ALTER TABLE template DROP environment_id, CHANGE role role VARCHAR(255) NOT NULL, CHANGE role_to role_to VARCHAR(255) NOT NULL, CHANGE role_cc role_cc VARCHAR(255) NOT NULL');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE environment_template');
        $this->addSql('ALTER TABLE template ADD environment_id INT DEFAULT NULL, CHANGE role role LONGTEXT NOT NULL COLLATE utf8_unicode_ci COMMENT \'(DC2Type:json_array)\', CHANGE role_to role_to LONGTEXT NOT NULL COLLATE utf8_unicode_ci COMMENT \'(DC2Type:json_array)\', CHANGE role_cc role_cc LONGTEXT NOT NULL COLLATE utf8_unicode_ci COMMENT \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE template ADD CONSTRAINT FK_97601F83903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id)');
        $this->addSql('CREATE INDEX IDX_97601F83903E3A94 ON template (environment_id)');
    }
}
