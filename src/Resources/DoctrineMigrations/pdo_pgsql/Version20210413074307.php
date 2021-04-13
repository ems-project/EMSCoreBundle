<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210413074307 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE query_search (id UUID NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, label VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, options JSON DEFAULT NULL, order_key INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN query_search.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE query_search_environment (query_search_id UUID NOT NULL, environment_id INT NOT NULL, PRIMARY KEY(query_search_id, environment_id))');
        $this->addSql('CREATE INDEX IDX_AD594AFB936B6C19 ON query_search_environment (query_search_id)');
        $this->addSql('CREATE INDEX IDX_AD594AFB903E3A94 ON query_search_environment (environment_id)');
        $this->addSql('COMMENT ON COLUMN query_search_environment.query_search_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE query_search_environment ADD CONSTRAINT FK_AD594AFB936B6C19 FOREIGN KEY (query_search_id) REFERENCES query_search (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE query_search_environment ADD CONSTRAINT FK_AD594AFB903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('COMMENT ON COLUMN wysiwyg_styles_set.assets IS NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE query_search_environment DROP CONSTRAINT FK_AD594AFB936B6C19');
        $this->addSql('DROP TABLE query_search');
        $this->addSql('DROP TABLE query_search_environment');
        $this->addSql('COMMENT ON COLUMN wysiwyg_styles_set.assets IS \'(DC2Type:json_array)\'');
    }
}
