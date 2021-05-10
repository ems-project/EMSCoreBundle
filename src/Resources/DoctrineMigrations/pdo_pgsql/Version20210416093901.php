<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210416093901 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE query_search (id UUID NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, label VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, options JSON DEFAULT NULL, order_key INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN query_search.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE environment_query_search (query_search_id UUID NOT NULL, environment_id INT NOT NULL, PRIMARY KEY(query_search_id, environment_id))');
        $this->addSql('CREATE INDEX IDX_1DF055936B6C19 ON environment_query_search (query_search_id)');
        $this->addSql('CREATE INDEX IDX_1DF055903E3A94 ON environment_query_search (environment_id)');
        $this->addSql('COMMENT ON COLUMN environment_query_search.query_search_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE environment_query_search ADD CONSTRAINT FK_1DF055936B6C19 FOREIGN KEY (query_search_id) REFERENCES query_search (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE environment_query_search ADD CONSTRAINT FK_1DF055903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');
        $this->addSql('ALTER TABLE environment_query_search DROP CONSTRAINT FK_1DF055936B6C19');
        $this->addSql('DROP TABLE query_search');
        $this->addSql('DROP TABLE environment_query_search');
    }
}
