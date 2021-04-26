<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210424070347 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE query_search (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', created DATETIME NOT NULL, modified DATETIME NOT NULL, label VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, options LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\', order_key INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE environment_query_search (query_search_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', environment_id INT NOT NULL, INDEX IDX_1DF055936B6C19 (query_search_id), INDEX IDX_1DF055903E3A94 (environment_id), PRIMARY KEY(query_search_id, environment_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE environment_query_search ADD CONSTRAINT FK_1DF055936B6C19 FOREIGN KEY (query_search_id) REFERENCES query_search (id)');
        $this->addSql('ALTER TABLE environment_query_search ADD CONSTRAINT FK_1DF055903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE environment_query_search DROP FOREIGN KEY FK_1DF055936B6C19');
        $this->addSql('DROP TABLE query_search');
        $this->addSql('DROP TABLE environment_query_search');
    }
}
