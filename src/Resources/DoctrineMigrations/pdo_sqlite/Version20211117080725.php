<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20211117080725 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP INDEX IDX_6D6315CC1A445520');
        $this->addSql('DROP INDEX IDX_6D6315CCE99931F3');
        $this->addSql('DROP INDEX tuple_index');
        $this->addSql('CREATE TEMPORARY TABLE __temp__revision AS SELECT id, content_type_id, task_current_id, created, modified, auto_save_at, archived, deleted, version, start_time, end_time, draft, finalized_by, finalized_date, archived_by, deleted_by, lock_by, auto_save_by, lock_until, raw_data, auto_save, circles, labelField, sha1, version_uuid, version_tag, task_planned_ids, task_approved_ids, owner, ouuid FROM revision');
        $this->addSql('DROP TABLE revision');
        $this->addSql('CREATE TABLE revision (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, task_current_id CHAR(36) DEFAULT NULL COLLATE BINARY --(DC2Type:uuid)
        , created DATETIME NOT NULL, modified DATETIME NOT NULL, auto_save_at DATETIME DEFAULT NULL, archived BOOLEAN DEFAULT \'0\' NOT NULL, deleted BOOLEAN NOT NULL, version INTEGER DEFAULT 1 NOT NULL, start_time DATETIME NOT NULL, end_time DATETIME DEFAULT NULL, draft BOOLEAN NOT NULL, finalized_by VARCHAR(255) DEFAULT NULL COLLATE BINARY, finalized_date DATETIME DEFAULT NULL, archived_by VARCHAR(255) DEFAULT NULL COLLATE BINARY, deleted_by VARCHAR(255) DEFAULT NULL COLLATE BINARY, lock_by VARCHAR(255) DEFAULT NULL COLLATE BINARY, auto_save_by VARCHAR(255) DEFAULT NULL COLLATE BINARY, lock_until DATETIME DEFAULT NULL, raw_data CLOB DEFAULT NULL COLLATE BINARY --(DC2Type:json_array)
        , auto_save CLOB DEFAULT NULL COLLATE BINARY --(DC2Type:json_array)
        , circles CLOB DEFAULT NULL COLLATE BINARY --(DC2Type:simple_array)
        , labelField CLOB DEFAULT NULL COLLATE BINARY, sha1 VARCHAR(255) DEFAULT NULL COLLATE BINARY, version_uuid CHAR(36) DEFAULT NULL COLLATE BINARY --(DC2Type:uuid)
        , version_tag VARCHAR(255) DEFAULT NULL COLLATE BINARY, task_planned_ids CLOB DEFAULT NULL COLLATE BINARY --(DC2Type:json)
        , task_approved_ids CLOB DEFAULT NULL COLLATE BINARY --(DC2Type:json)
        , owner CLOB DEFAULT NULL COLLATE BINARY, ouuid VARCHAR(255) DEFAULT NULL COLLATE BINARY, draft_save_date DATETIME DEFAULT NULL, CONSTRAINT FK_6D6315CC1A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6D6315CCE99931F3 FOREIGN KEY (task_current_id) REFERENCES task (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO revision (id, content_type_id, task_current_id, created, modified, auto_save_at, archived, deleted, version, start_time, end_time, draft, finalized_by, finalized_date, archived_by, deleted_by, lock_by, auto_save_by, lock_until, raw_data, auto_save, circles, labelField, sha1, version_uuid, version_tag, task_planned_ids, task_approved_ids, owner, ouuid) SELECT id, content_type_id, task_current_id, created, modified, auto_save_at, archived, deleted, version, start_time, end_time, draft, finalized_by, finalized_date, archived_by, deleted_by, lock_by, auto_save_by, lock_until, raw_data, auto_save, circles, labelField, sha1, version_uuid, version_tag, task_planned_ids, task_approved_ids, owner, ouuid FROM __temp__revision');
        $this->addSql('DROP TABLE __temp__revision');
        $this->addSql('CREATE INDEX IDX_6D6315CC1A445520 ON revision (content_type_id)');
        $this->addSql('CREATE INDEX IDX_6D6315CCE99931F3 ON revision (task_current_id)');
        $this->addSql('CREATE UNIQUE INDEX tuple_index ON revision (end_time, ouuid)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP INDEX IDX_6D6315CC1A445520');
        $this->addSql('DROP INDEX IDX_6D6315CCE99931F3');
        $this->addSql('DROP INDEX tuple_index');
        $this->addSql('CREATE TEMPORARY TABLE __temp__revision AS SELECT id, content_type_id, task_current_id, created, modified, auto_save_at, archived, deleted, version, ouuid, start_time, end_time, draft, finalized_by, finalized_date, archived_by, deleted_by, lock_by, auto_save_by, lock_until, raw_data, auto_save, circles, labelField, sha1, version_uuid, version_tag, task_planned_ids, task_approved_ids, owner FROM revision');
        $this->addSql('DROP TABLE revision');
        $this->addSql('CREATE TABLE revision (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, task_current_id CHAR(36) DEFAULT NULL --(DC2Type:uuid)
        , created DATETIME NOT NULL, modified DATETIME NOT NULL, auto_save_at DATETIME DEFAULT NULL, archived BOOLEAN DEFAULT \'0\' NOT NULL, deleted BOOLEAN NOT NULL, version INTEGER DEFAULT 1 NOT NULL, start_time DATETIME NOT NULL, end_time DATETIME DEFAULT NULL, draft BOOLEAN NOT NULL, finalized_by VARCHAR(255) DEFAULT NULL, finalized_date DATETIME DEFAULT NULL, archived_by VARCHAR(255) DEFAULT NULL, deleted_by VARCHAR(255) DEFAULT NULL, lock_by VARCHAR(255) DEFAULT NULL, auto_save_by VARCHAR(255) DEFAULT NULL, lock_until DATETIME DEFAULT NULL, raw_data CLOB DEFAULT NULL --(DC2Type:json_array)
        , auto_save CLOB DEFAULT NULL --(DC2Type:json_array)
        , circles CLOB DEFAULT NULL --(DC2Type:simple_array)
        , labelField CLOB DEFAULT NULL, sha1 VARCHAR(255) DEFAULT NULL, version_uuid CHAR(36) DEFAULT NULL --(DC2Type:uuid)
        , version_tag VARCHAR(255) DEFAULT NULL, task_planned_ids CLOB DEFAULT NULL --(DC2Type:json)
        , task_approved_ids CLOB DEFAULT NULL --(DC2Type:json)
        , owner CLOB DEFAULT NULL, ouuid VARCHAR(255) DEFAULT NULL COLLATE BINARY)');
        $this->addSql('INSERT INTO revision (id, content_type_id, task_current_id, created, modified, auto_save_at, archived, deleted, version, ouuid, start_time, end_time, draft, finalized_by, finalized_date, archived_by, deleted_by, lock_by, auto_save_by, lock_until, raw_data, auto_save, circles, labelField, sha1, version_uuid, version_tag, task_planned_ids, task_approved_ids, owner) SELECT id, content_type_id, task_current_id, created, modified, auto_save_at, archived, deleted, version, ouuid, start_time, end_time, draft, finalized_by, finalized_date, archived_by, deleted_by, lock_by, auto_save_by, lock_until, raw_data, auto_save, circles, labelField, sha1, version_uuid, version_tag, task_planned_ids, task_approved_ids, owner FROM __temp__revision');
        $this->addSql('DROP TABLE __temp__revision');
        $this->addSql('CREATE INDEX IDX_6D6315CC1A445520 ON revision (content_type_id)');
        $this->addSql('CREATE INDEX IDX_6D6315CCE99931F3 ON revision (task_current_id)');
        $this->addSql('CREATE UNIQUE INDEX tuple_index ON revision (end_time, ouuid)');
    }
}
