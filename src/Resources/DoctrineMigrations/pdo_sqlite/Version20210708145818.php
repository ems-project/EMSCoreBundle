<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210708145818 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TEMPORARY TABLE __temp__form_submission AS SELECT id, created, modified, name, instance, locale, process_id, process_try_counter, data, process_by, label, expire_date FROM form_submission');
        $this->addSql('DROP TABLE form_submission');
        $this->addSql('CREATE TABLE form_submission (id CHAR(36) NOT NULL COLLATE BINARY --(DC2Type:uuid)
        , created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL COLLATE BINARY, instance VARCHAR(255) NOT NULL COLLATE BINARY, locale VARCHAR(2) NOT NULL COLLATE BINARY, process_id VARCHAR(255) DEFAULT NULL COLLATE BINARY, process_try_counter INTEGER DEFAULT 0 NOT NULL, data CLOB DEFAULT NULL COLLATE BINARY --(DC2Type:json_array)
        , process_by VARCHAR(255) DEFAULT NULL COLLATE BINARY, label VARCHAR(255) NOT NULL COLLATE BINARY, expire_date DATE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('INSERT INTO form_submission (id, created, modified, name, instance, locale, process_id, process_try_counter, data, process_by, label, expire_date) SELECT id, created, modified, name, instance, locale, process_id, process_try_counter, data, process_by, label, expire_date FROM __temp__form_submission');
        $this->addSql('DROP TABLE __temp__form_submission');
        $this->addSql('DROP INDEX IDX_AEFF00A6422B0E0C');
        $this->addSql('CREATE TEMPORARY TABLE __temp__form_submission_file AS SELECT id, form_submission_id, created, modified, file, filename, form_field, mime_type, size FROM form_submission_file');
        $this->addSql('DROP TABLE form_submission_file');
        $this->addSql('CREATE TABLE form_submission_file (id CHAR(36) NOT NULL COLLATE BINARY --(DC2Type:uuid)
        , form_submission_id CHAR(36) DEFAULT NULL COLLATE BINARY --(DC2Type:uuid)
        , created DATETIME NOT NULL, modified DATETIME NOT NULL, file BLOB NOT NULL, filename VARCHAR(255) NOT NULL COLLATE BINARY, form_field VARCHAR(255) NOT NULL COLLATE BINARY, mime_type VARCHAR(1024) NOT NULL COLLATE BINARY, size BIGINT NOT NULL, PRIMARY KEY(id), CONSTRAINT FK_AEFF00A6422B0E0C FOREIGN KEY (form_submission_id) REFERENCES form_submission (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO form_submission_file (id, form_submission_id, created, modified, file, filename, form_field, mime_type, size) SELECT id, form_submission_id, created, modified, file, filename, form_field, mime_type, size FROM __temp__form_submission_file');
        $this->addSql('DROP TABLE __temp__form_submission_file');
        $this->addSql('CREATE INDEX IDX_AEFF00A6422B0E0C ON form_submission_file (form_submission_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TEMPORARY TABLE __temp__form_submission AS SELECT id, created, modified, name, instance, locale, data, expire_date, label, process_try_counter, process_id, process_by FROM form_submission');
        $this->addSql('DROP TABLE form_submission');
        $this->addSql('CREATE TABLE form_submission (id CHAR(36) NOT NULL --(DC2Type:uuid)
        , created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL, instance VARCHAR(255) NOT NULL, locale VARCHAR(2) NOT NULL, data CLOB DEFAULT NULL --(DC2Type:json_array)
        , label VARCHAR(255) NOT NULL, process_try_counter INTEGER DEFAULT 0 NOT NULL, process_id VARCHAR(255) DEFAULT NULL, process_by VARCHAR(255) DEFAULT NULL, expire_date DATE NOT NULL, deadline_date VARCHAR(255) NOT NULL COLLATE BINARY, PRIMARY KEY(id))');
        $this->addSql('INSERT INTO form_submission (id, created, modified, name, instance, locale, data, expire_date, label, process_try_counter, process_id, process_by) SELECT id, created, modified, name, instance, locale, data, expire_date, label, process_try_counter, process_id, process_by FROM __temp__form_submission');
        $this->addSql('DROP TABLE __temp__form_submission');
        $this->addSql('DROP INDEX IDX_AEFF00A6422B0E0C');
        $this->addSql('CREATE TEMPORARY TABLE __temp__form_submission_file AS SELECT id, form_submission_id, created, modified, file, filename, form_field, mime_type, size FROM form_submission_file');
        $this->addSql('DROP TABLE form_submission_file');
        $this->addSql('CREATE TABLE form_submission_file (id CHAR(36) NOT NULL --(DC2Type:uuid)
        , form_submission_id CHAR(36) DEFAULT NULL --(DC2Type:uuid)
        , created DATETIME NOT NULL, modified DATETIME NOT NULL, file BLOB NOT NULL, filename VARCHAR(255) NOT NULL, form_field VARCHAR(255) NOT NULL, mime_type VARCHAR(1024) NOT NULL, size BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('INSERT INTO form_submission_file (id, form_submission_id, created, modified, file, filename, form_field, mime_type, size) SELECT id, form_submission_id, created, modified, file, filename, form_field, mime_type, size FROM __temp__form_submission_file');
        $this->addSql('DROP TABLE __temp__form_submission_file');
        $this->addSql('CREATE INDEX IDX_AEFF00A6422B0E0C ON form_submission_file (form_submission_id)');
    }
}
