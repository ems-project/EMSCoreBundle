<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210424070719 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TABLE query_search (id CHAR(36) NOT NULL --(DC2Type:uuid)
        , created DATETIME NOT NULL, modified DATETIME NOT NULL, label VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, options CLOB DEFAULT NULL --(DC2Type:json)
        , order_key INTEGER NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE environment_query_search (query_search_id CHAR(36) NOT NULL --(DC2Type:uuid)
        , environment_id INTEGER NOT NULL, PRIMARY KEY(query_search_id, environment_id))');
        $this->addSql('CREATE INDEX IDX_1DF055936B6C19 ON environment_query_search (query_search_id)');
        $this->addSql('CREATE INDEX IDX_1DF055903E3A94 ON environment_query_search (environment_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP TABLE query_search');
        $this->addSql('DROP TABLE environment_query_search');
    }
}
