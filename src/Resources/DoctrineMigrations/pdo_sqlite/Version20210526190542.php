<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210526190542 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TEMPORARY TABLE __temp__revision AS SELECT id, content_type_id, created, modified, auto_save_at, deleted, version, start_time, end_time, draft, finalized_by, finalized_date, deleted_by, lock_by, auto_save_by, lock_until, raw_data, auto_save, circles, labelField, sha1, version_uuid, version_tag, ouuid FROM revision');
        $this->addSql('DROP TABLE revision');
        $this->addSql('CREATE TABLE revision (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, auto_save_at DATETIME DEFAULT NULL, deleted BOOLEAN NOT NULL, version INTEGER DEFAULT 1 NOT NULL, start_time DATETIME NOT NULL, end_time DATETIME DEFAULT NULL, draft BOOLEAN NOT NULL, finalized_by VARCHAR(255) DEFAULT NULL COLLATE BINARY, finalized_date DATETIME DEFAULT NULL, deleted_by VARCHAR(255) DEFAULT NULL COLLATE BINARY, lock_by VARCHAR(255) DEFAULT NULL COLLATE BINARY, auto_save_by VARCHAR(255) DEFAULT NULL COLLATE BINARY, lock_until DATETIME DEFAULT NULL, raw_data CLOB DEFAULT NULL COLLATE BINARY --(DC2Type:json_array)
        , auto_save CLOB DEFAULT NULL COLLATE BINARY --(DC2Type:json_array)
        , circles CLOB DEFAULT NULL COLLATE BINARY --(DC2Type:simple_array)
        , labelField CLOB DEFAULT NULL COLLATE BINARY, sha1 VARCHAR(255) DEFAULT NULL COLLATE BINARY, version_uuid CHAR(36) DEFAULT NULL COLLATE BINARY --(DC2Type:uuid)
        , version_tag VARCHAR(255) DEFAULT NULL COLLATE BINARY, ouuid VARCHAR(255) DEFAULT NULL COLLATE utf8_bin, archived BOOLEAN DEFAULT \'0\' NOT NULL, archived_by VARCHAR(255) DEFAULT NULL, CONSTRAINT FK_6D6315CC1A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO revision (id, content_type_id, created, modified, auto_save_at, deleted, version, start_time, end_time, draft, finalized_by, finalized_date, deleted_by, lock_by, auto_save_by, lock_until, raw_data, auto_save, circles, labelField, sha1, version_uuid, version_tag, ouuid) SELECT id, content_type_id, created, modified, auto_save_at, deleted, version, start_time, end_time, draft, finalized_by, finalized_date, deleted_by, lock_by, auto_save_by, lock_until, raw_data, auto_save, circles, labelField, sha1, version_uuid, version_tag, ouuid FROM __temp__revision');
        $this->addSql('DROP TABLE __temp__revision');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TEMPORARY TABLE __temp__revision AS SELECT id, content_type_id, created, modified, auto_save_at, deleted, version, ouuid, start_time, end_time, draft, finalized_by, finalized_date, deleted_by, lock_by, auto_save_by, lock_until, raw_data, auto_save, circles, labelField, sha1, version_uuid, version_tag FROM revision');
        $this->addSql('DROP TABLE revision');
        $this->addSql('CREATE TABLE revision (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, auto_save_at DATETIME DEFAULT NULL, deleted BOOLEAN NOT NULL, version INTEGER DEFAULT 1 NOT NULL, start_time DATETIME NOT NULL, end_time DATETIME DEFAULT NULL, draft BOOLEAN NOT NULL, finalized_by VARCHAR(255) DEFAULT NULL, finalized_date DATETIME DEFAULT NULL, deleted_by VARCHAR(255) DEFAULT NULL, lock_by VARCHAR(255) DEFAULT NULL, auto_save_by VARCHAR(255) DEFAULT NULL, lock_until DATETIME DEFAULT NULL, raw_data CLOB DEFAULT NULL --(DC2Type:json_array)
        , auto_save CLOB DEFAULT NULL --(DC2Type:json_array)
        , circles CLOB DEFAULT NULL --(DC2Type:simple_array)
        , labelField CLOB DEFAULT NULL, sha1 VARCHAR(255) DEFAULT NULL, version_uuid CHAR(36) DEFAULT NULL --(DC2Type:uuid)
        , version_tag VARCHAR(255) DEFAULT NULL, ouuid VARCHAR(255) DEFAULT NULL COLLATE BINARY)');
        $this->addSql('INSERT INTO revision (id, content_type_id, created, modified, auto_save_at, deleted, version, ouuid, start_time, end_time, draft, finalized_by, finalized_date, deleted_by, lock_by, auto_save_by, lock_until, raw_data, auto_save, circles, labelField, sha1, version_uuid, version_tag) SELECT id, content_type_id, created, modified, auto_save_at, deleted, version, ouuid, start_time, end_time, draft, finalized_by, finalized_date, deleted_by, lock_by, auto_save_by, lock_until, raw_data, auto_save, circles, labelField, sha1, version_uuid, version_tag FROM __temp__revision');
        $this->addSql('DROP TABLE __temp__revision');
    }
}
