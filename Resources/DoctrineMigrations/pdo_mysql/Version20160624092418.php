<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160624092418 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE template ADD environment_id INT DEFAULT NULL, ADD active TINYINT(1) NOT NULL, ADD role LONGTEXT NOT NULL COMMENT \'(DC2Type:json_array)\', ADD role_to LONGTEXT NOT NULL COMMENT \'(DC2Type:json_array)\', ADD role_cc LONGTEXT NOT NULL COMMENT \'(DC2Type:json_array)\', ADD circles LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json_array)\', ADD response_template LONGTEXT DEFAULT NULL, DROP recipient');
        $this->addSql('ALTER TABLE template ADD CONSTRAINT FK_97601F83903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id)');
        $this->addSql('CREATE INDEX IDX_97601F83903E3A94 ON template (environment_id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE template DROP FOREIGN KEY FK_97601F83903E3A94');
        $this->addSql('DROP INDEX IDX_97601F83903E3A94 ON template');
        $this->addSql('ALTER TABLE template ADD recipient VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, DROP environment_id, DROP active, DROP role, DROP role_to, DROP role_cc, DROP circles, DROP response_template');
    }
}
