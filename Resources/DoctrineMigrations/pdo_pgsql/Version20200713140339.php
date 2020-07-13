<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200713140339 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE form_submission (id INT NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, name VARCHAR(255) NOT NULL, locale VARCHAR(2) NOT NULL, data JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE form_submission_file (id INT NOT NULL, form_submission_id INT DEFAULT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, file BYTEA NOT NULL, filename VARCHAR(255) NOT NULL, form_field VARCHAR(255) NOT NULL, mime_type VARCHAR(1024) NOT NULL, size BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_AEFF00A6422B0E0C ON form_submission_file (form_submission_id)');
        $this->addSql('ALTER TABLE form_submission_file ADD CONSTRAINT FK_AEFF00A6422B0E0C FOREIGN KEY (form_submission_id) REFERENCES form_submission (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE form_submission_file DROP CONSTRAINT FK_AEFF00A6422B0E0C');
        $this->addSql('DROP TABLE form_submission');
        $this->addSql('DROP TABLE form_submission_file');
    }
}
