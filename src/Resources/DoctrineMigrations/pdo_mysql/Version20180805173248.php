<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20180805173248 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE search ADD content_type_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE search ADD CONSTRAINT FK_B4F0DBA71A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B4F0DBA71A445520 ON search (content_type_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE search DROP FOREIGN KEY FK_B4F0DBA71A445520');
        $this->addSql('DROP INDEX UNIQ_B4F0DBA71A445520 ON search');
        $this->addSql('ALTER TABLE search DROP content_type_id');
    }
}
