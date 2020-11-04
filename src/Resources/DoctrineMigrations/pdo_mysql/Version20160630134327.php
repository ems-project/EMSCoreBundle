<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160630134327 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE notification DROP INDEX UNIQ_BF5476CA5DA0FB8, ADD INDEX IDX_BF5476CA5DA0FB8 (template_id)');
        $this->addSql('ALTER TABLE notification DROP INDEX UNIQ_BF5476CA1DFA7C8F, ADD INDEX IDX_BF5476CA1DFA7C8F (revision_id)');
        $this->addSql('ALTER TABLE notification DROP INDEX UNIQ_BF5476CA903E3A94, ADD INDEX IDX_BF5476CA903E3A94 (environment_id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE notification DROP INDEX IDX_BF5476CA5DA0FB8, ADD UNIQUE INDEX UNIQ_BF5476CA5DA0FB8 (template_id)');
        $this->addSql('ALTER TABLE notification DROP INDEX IDX_BF5476CA1DFA7C8F, ADD UNIQUE INDEX UNIQ_BF5476CA1DFA7C8F (revision_id)');
        $this->addSql('ALTER TABLE notification DROP INDEX IDX_BF5476CA903E3A94, ADD UNIQUE INDEX UNIQ_BF5476CA903E3A94 (environment_id)');
    }
}
