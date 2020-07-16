<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20161022084512 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE search_filter CHANGE boolean_clause boolean_clause VARCHAR(20) DEFAULT NULL');
        $this->addSql('UPDATE search_filter SET boolean_clause = "must" where boolean_clause = "0"');
        $this->addSql('UPDATE search_filter SET boolean_clause = "must_not" where boolean_clause = "1"');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('UPDATE search_filter SET boolean_clause = "0" where boolean_clause = "must"');
        $this->addSql('UPDATE search_filter SET boolean_clause = "1" where boolean_clause = "must_not"');
        $this->addSql('ALTER TABLE search_filter CHANGE boolean_clause boolean_clause TINYINT(1) DEFAULT NULL');
    }
}
