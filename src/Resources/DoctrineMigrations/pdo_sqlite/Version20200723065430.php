<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200723065430 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP TABLE session');
        $this->addSql('CREATE TABLE session (id VARCHAR(128) NOT NULL, data BLOB NOT NULL, time INTEGER UNSIGNED NOT NULL, lifetime INTEGER NOT NULL, PRIMARY KEY(id))');
        $this->addSql('DROP INDEX tuple_index');
        $this->addSql('DROP INDEX IDX_6D6315CC1A445520');
        $this->addSql('CREATE TEMPORARY TABLE __temp__revision AS SELECT id, content_type_id, created, modified, auto_save_at, deleted, version, start_time, end_time, draft, lock_by, auto_save_by, lock_until, labelField, finalized_by, sha1, deleted_by, finalized_date, raw_data, auto_save, circles, ouuid FROM revision');
        $this->addSql('DROP TABLE revision');
        $this->addSql('CREATE TABLE revision (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, auto_save_at DATETIME DEFAULT NULL, deleted BOOLEAN NOT NULL, version INTEGER DEFAULT 1 NOT NULL, start_time DATETIME NOT NULL, end_time DATETIME DEFAULT NULL, draft BOOLEAN NOT NULL, lock_by VARCHAR(255) DEFAULT NULL COLLATE BINARY, auto_save_by VARCHAR(255) DEFAULT NULL COLLATE BINARY, lock_until DATETIME DEFAULT NULL, labelField CLOB DEFAULT NULL COLLATE BINARY, finalized_by VARCHAR(255) DEFAULT NULL COLLATE BINARY, sha1 VARCHAR(255) DEFAULT NULL COLLATE BINARY, deleted_by VARCHAR(255) DEFAULT NULL COLLATE BINARY, finalized_date DATETIME DEFAULT NULL, raw_data CLOB DEFAULT NULL COLLATE BINARY --(DC2Type:json_array)
        , auto_save CLOB DEFAULT NULL COLLATE BINARY --(DC2Type:json_array)
        , circles CLOB DEFAULT NULL COLLATE BINARY --(DC2Type:simple_array)
        , ouuid VARCHAR(255) DEFAULT NULL COLLATE BINARY, CONSTRAINT FK_6D6315CC1A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO revision (id, content_type_id, created, modified, auto_save_at, deleted, version, start_time, end_time, draft, lock_by, auto_save_by, lock_until, labelField, finalized_by, sha1, deleted_by, finalized_date, raw_data, auto_save, circles, ouuid) SELECT id, content_type_id, created, modified, auto_save_at, deleted, version, start_time, end_time, draft, lock_by, auto_save_by, lock_until, labelField, finalized_by, sha1, deleted_by, finalized_date, raw_data, auto_save, circles, ouuid FROM __temp__revision');
        $this->addSql('DROP TABLE __temp__revision');
        $this->addSql('CREATE UNIQUE INDEX tuple_index ON revision (end_time, ouuid)');
        $this->addSql('CREATE INDEX IDX_6D6315CC1A445520 ON revision (content_type_id)');
        $this->addSql('DROP INDEX IDX_895F7B701DFA7C8F');
        $this->addSql('DROP INDEX IDX_895F7B70903E3A94');
        $this->addSql('CREATE TEMPORARY TABLE __temp__environment_revision AS SELECT revision_id, environment_id FROM environment_revision');
        $this->addSql('DROP TABLE environment_revision');
        $this->addSql('CREATE TABLE environment_revision (revision_id INTEGER NOT NULL, environment_id INTEGER NOT NULL, PRIMARY KEY(revision_id, environment_id), CONSTRAINT FK_895F7B701DFA7C8F FOREIGN KEY (revision_id) REFERENCES revision (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_895F7B70903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO environment_revision (revision_id, environment_id) SELECT revision_id, environment_id FROM __temp__environment_revision');
        $this->addSql('DROP TABLE __temp__environment_revision');
        $this->addSql('CREATE INDEX IDX_895F7B701DFA7C8F ON environment_revision (revision_id)');
        $this->addSql('CREATE INDEX IDX_895F7B70903E3A94 ON environment_revision (environment_id)');
        $this->addSql('DROP INDEX UNIQ_41BCBAEC588AB49A');
        $this->addSql('DROP INDEX IDX_41BCBAEC903E3A94');
        $this->addSql('CREATE TEMPORARY TABLE __temp__content_type AS SELECT id, field_types_id, environment_id, created, modified, name, pluralName, singularName, icon, description, indexTwig, extra, lockBy, lockUntil, circles_field, deleted, have_pipelines, ask_for_ouuid, dirty, color, labelField, color_field, parentField, userField, dateField, startDateField, endDateField, locationField, referer_field_name, category_field, ouuidField, imageField, videoField, email_field, asset_field, order_field, sort_by, create_role, edit_role, view_role, orderKey, rootContentType, edit_twig_with_wysiwyg, active, publish_role, trash_role, sort_order, web_content, default_value, auto_publish, business_id_field, translationField, localeField, searchLinkDisplayRole, createLinkDisplayRole FROM content_type');
        $this->addSql('DROP TABLE content_type');
        $this->addSql('CREATE TABLE content_type (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, field_types_id INTEGER DEFAULT NULL, environment_id INTEGER DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(100) NOT NULL COLLATE BINARY, pluralName VARCHAR(100) NOT NULL COLLATE BINARY, singularName VARCHAR(100) NOT NULL COLLATE BINARY, icon VARCHAR(100) DEFAULT NULL COLLATE BINARY, description CLOB DEFAULT NULL COLLATE BINARY, indexTwig CLOB DEFAULT NULL COLLATE BINARY, extra CLOB DEFAULT NULL COLLATE BINARY, lockBy VARCHAR(100) DEFAULT NULL COLLATE BINARY, lockUntil DATETIME DEFAULT NULL, circles_field VARCHAR(100) DEFAULT NULL COLLATE BINARY, deleted BOOLEAN NOT NULL, have_pipelines BOOLEAN DEFAULT NULL, ask_for_ouuid BOOLEAN NOT NULL, dirty BOOLEAN NOT NULL, color VARCHAR(50) DEFAULT NULL COLLATE BINARY, labelField VARCHAR(100) DEFAULT NULL COLLATE BINARY, color_field VARCHAR(100) DEFAULT NULL COLLATE BINARY, parentField VARCHAR(100) DEFAULT NULL COLLATE BINARY, userField VARCHAR(100) DEFAULT NULL COLLATE BINARY, dateField VARCHAR(100) DEFAULT NULL COLLATE BINARY, startDateField VARCHAR(100) DEFAULT NULL COLLATE BINARY, endDateField VARCHAR(100) DEFAULT NULL COLLATE BINARY, locationField VARCHAR(100) DEFAULT NULL COLLATE BINARY, referer_field_name VARCHAR(100) DEFAULT NULL COLLATE BINARY, category_field VARCHAR(100) DEFAULT NULL COLLATE BINARY, ouuidField VARCHAR(100) DEFAULT NULL COLLATE BINARY, imageField VARCHAR(100) DEFAULT NULL COLLATE BINARY, videoField VARCHAR(100) DEFAULT NULL COLLATE BINARY, email_field VARCHAR(100) DEFAULT NULL COLLATE BINARY, asset_field VARCHAR(100) DEFAULT NULL COLLATE BINARY, order_field VARCHAR(100) DEFAULT NULL COLLATE BINARY, sort_by VARCHAR(100) DEFAULT NULL COLLATE BINARY, create_role VARCHAR(100) DEFAULT NULL COLLATE BINARY, edit_role VARCHAR(100) DEFAULT NULL COLLATE BINARY, view_role VARCHAR(100) DEFAULT NULL COLLATE BINARY, orderKey INTEGER NOT NULL, rootContentType BOOLEAN NOT NULL, edit_twig_with_wysiwyg BOOLEAN NOT NULL, active BOOLEAN NOT NULL, publish_role VARCHAR(100) DEFAULT NULL COLLATE BINARY, trash_role VARCHAR(100) DEFAULT NULL COLLATE BINARY, sort_order VARCHAR(4) DEFAULT \'asc\' COLLATE BINARY, web_content BOOLEAN DEFAULT \'1\' NOT NULL, default_value CLOB DEFAULT NULL COLLATE BINARY, auto_publish BOOLEAN DEFAULT \'0\' NOT NULL, business_id_field VARCHAR(100) DEFAULT NULL COLLATE BINARY, translationField VARCHAR(100) DEFAULT NULL COLLATE BINARY, localeField VARCHAR(100) DEFAULT NULL COLLATE BINARY, searchLinkDisplayRole VARCHAR(255) DEFAULT \'ROLE_USER\' NOT NULL COLLATE BINARY, createLinkDisplayRole VARCHAR(255) DEFAULT \'ROLE_USER\' NOT NULL COLLATE BINARY, CONSTRAINT FK_41BCBAEC588AB49A FOREIGN KEY (field_types_id) REFERENCES field_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_41BCBAEC903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO content_type (id, field_types_id, environment_id, created, modified, name, pluralName, singularName, icon, description, indexTwig, extra, lockBy, lockUntil, circles_field, deleted, have_pipelines, ask_for_ouuid, dirty, color, labelField, color_field, parentField, userField, dateField, startDateField, endDateField, locationField, referer_field_name, category_field, ouuidField, imageField, videoField, email_field, asset_field, order_field, sort_by, create_role, edit_role, view_role, orderKey, rootContentType, edit_twig_with_wysiwyg, active, publish_role, trash_role, sort_order, web_content, default_value, auto_publish, business_id_field, translationField, localeField, searchLinkDisplayRole, createLinkDisplayRole) SELECT id, field_types_id, environment_id, created, modified, name, pluralName, singularName, icon, description, indexTwig, extra, lockBy, lockUntil, circles_field, deleted, have_pipelines, ask_for_ouuid, dirty, color, labelField, color_field, parentField, userField, dateField, startDateField, endDateField, locationField, referer_field_name, category_field, ouuidField, imageField, videoField, email_field, asset_field, order_field, sort_by, create_role, edit_role, view_role, orderKey, rootContentType, edit_twig_with_wysiwyg, active, publish_role, trash_role, sort_order, web_content, default_value, auto_publish, business_id_field, translationField, localeField, searchLinkDisplayRole, createLinkDisplayRole FROM __temp__content_type');
        $this->addSql('DROP TABLE __temp__content_type');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_41BCBAEC588AB49A ON content_type (field_types_id)');
        $this->addSql('CREATE INDEX IDX_41BCBAEC903E3A94 ON content_type (environment_id)');
        $this->addSql('DROP INDEX IDX_BF5476CA5DA0FB8');
        $this->addSql('DROP INDEX IDX_BF5476CA1DFA7C8F');
        $this->addSql('DROP INDEX IDX_BF5476CA903E3A94');
        $this->addSql('CREATE TEMPORARY TABLE __temp__notification AS SELECT id, template_id, revision_id, environment_id, created, modified, username, status, sent_timestamp, response_text, response_timestamp, response_by, emailed, response_emailed FROM notification');
        $this->addSql('DROP TABLE notification');
        $this->addSql('CREATE TABLE notification (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, template_id INTEGER DEFAULT NULL, revision_id INTEGER DEFAULT NULL, environment_id INTEGER DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, username VARCHAR(100) NOT NULL COLLATE BINARY, status VARCHAR(20) NOT NULL COLLATE BINARY, sent_timestamp DATETIME NOT NULL, response_text CLOB DEFAULT NULL COLLATE BINARY, response_timestamp DATETIME DEFAULT NULL, response_by VARCHAR(100) DEFAULT NULL COLLATE BINARY, emailed DATETIME DEFAULT NULL, response_emailed DATETIME DEFAULT NULL, CONSTRAINT FK_BF5476CA5DA0FB8 FOREIGN KEY (template_id) REFERENCES template (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BF5476CA1DFA7C8F FOREIGN KEY (revision_id) REFERENCES revision (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BF5476CA903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO notification (id, template_id, revision_id, environment_id, created, modified, username, status, sent_timestamp, response_text, response_timestamp, response_by, emailed, response_emailed) SELECT id, template_id, revision_id, environment_id, created, modified, username, status, sent_timestamp, response_text, response_timestamp, response_by, emailed, response_emailed FROM __temp__notification');
        $this->addSql('DROP TABLE __temp__notification');
        $this->addSql('CREATE INDEX IDX_BF5476CA5DA0FB8 ON notification (template_id)');
        $this->addSql('CREATE INDEX IDX_BF5476CA1DFA7C8F ON notification (revision_id)');
        $this->addSql('CREATE INDEX IDX_BF5476CA903E3A94 ON notification (environment_id)');
        $this->addSql('DROP INDEX UNIQ_9F123E931A445520');
        $this->addSql('DROP INDEX IDX_9F123E93727ACA70');
        $this->addSql('CREATE TEMPORARY TABLE __temp__field_type AS SELECT id, content_type_id, parent_id, created, modified, type, name, deleted, description, orderKey, options FROM field_type');
        $this->addSql('DROP TABLE field_type');
        $this->addSql('CREATE TABLE field_type (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, parent_id INTEGER DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, type VARCHAR(255) NOT NULL COLLATE BINARY, name VARCHAR(255) NOT NULL COLLATE BINARY, deleted BOOLEAN NOT NULL, description CLOB DEFAULT NULL COLLATE BINARY, orderKey INTEGER NOT NULL, options CLOB DEFAULT NULL COLLATE BINARY --(DC2Type:json_array)
        , CONSTRAINT FK_9F123E931A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_9F123E93727ACA70 FOREIGN KEY (parent_id) REFERENCES field_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO field_type (id, content_type_id, parent_id, created, modified, type, name, deleted, description, orderKey, options) SELECT id, content_type_id, parent_id, created, modified, type, name, deleted, description, orderKey, options FROM __temp__field_type');
        $this->addSql('DROP TABLE __temp__field_type');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9F123E931A445520 ON field_type (content_type_id)');
        $this->addSql('CREATE INDEX IDX_9F123E93727ACA70 ON field_type (parent_id)');
        $this->addSql('DROP INDEX IDX_97601F831A445520');
        $this->addSql('CREATE TEMPORARY TABLE __temp__template AS SELECT id, content_type_id, created, modified, name, icon, body, header, edit_with_wysiwyg, render_option, orderKey, accumulate_in_one_file, preview, mime_type, filename, extension, active, role, role_to, role_cc, response_template, email_content_type, orientation, size, public, allow_origin, disposition, circles_to FROM template');
        $this->addSql('DROP TABLE template');
        $this->addSql('CREATE TABLE template (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL COLLATE BINARY, icon VARCHAR(255) DEFAULT NULL COLLATE BINARY, body CLOB DEFAULT NULL COLLATE BINARY, header CLOB DEFAULT NULL COLLATE BINARY, edit_with_wysiwyg BOOLEAN NOT NULL, render_option VARCHAR(255) NOT NULL COLLATE BINARY, orderKey INTEGER NOT NULL, accumulate_in_one_file BOOLEAN NOT NULL, preview BOOLEAN NOT NULL, mime_type VARCHAR(255) DEFAULT NULL COLLATE BINARY, filename CLOB DEFAULT NULL COLLATE BINARY, extension VARCHAR(255) DEFAULT NULL COLLATE BINARY, active BOOLEAN NOT NULL, role VARCHAR(255) NOT NULL COLLATE BINARY, role_to VARCHAR(255) NOT NULL COLLATE BINARY, role_cc VARCHAR(255) NOT NULL COLLATE BINARY, response_template CLOB DEFAULT NULL COLLATE BINARY, email_content_type VARCHAR(255) DEFAULT NULL COLLATE BINARY, orientation VARCHAR(20) DEFAULT NULL COLLATE BINARY, size VARCHAR(20) DEFAULT NULL COLLATE BINARY, public BOOLEAN DEFAULT \'0\' NOT NULL, allow_origin VARCHAR(255) DEFAULT NULL COLLATE BINARY, disposition VARCHAR(20) DEFAULT NULL COLLATE BINARY, circles_to CLOB DEFAULT NULL COLLATE BINARY --(DC2Type:json_array)
        , CONSTRAINT FK_97601F831A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO template (id, content_type_id, created, modified, name, icon, body, header, edit_with_wysiwyg, render_option, orderKey, accumulate_in_one_file, preview, mime_type, filename, extension, active, role, role_to, role_cc, response_template, email_content_type, orientation, size, public, allow_origin, disposition, circles_to) SELECT id, content_type_id, created, modified, name, icon, body, header, edit_with_wysiwyg, render_option, orderKey, accumulate_in_one_file, preview, mime_type, filename, extension, active, role, role_to, role_cc, response_template, email_content_type, orientation, size, public, allow_origin, disposition, circles_to FROM __temp__template');
        $this->addSql('DROP TABLE __temp__template');
        $this->addSql('CREATE INDEX IDX_97601F831A445520 ON template (content_type_id)');
        $this->addSql('DROP INDEX IDX_735C713F5DA0FB8');
        $this->addSql('DROP INDEX IDX_735C713F903E3A94');
        $this->addSql('CREATE TEMPORARY TABLE __temp__environment_template AS SELECT template_id, environment_id FROM environment_template');
        $this->addSql('DROP TABLE environment_template');
        $this->addSql('CREATE TABLE environment_template (template_id INTEGER NOT NULL, environment_id INTEGER NOT NULL, PRIMARY KEY(template_id, environment_id), CONSTRAINT FK_735C713F5DA0FB8 FOREIGN KEY (template_id) REFERENCES template (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_735C713F903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO environment_template (template_id, environment_id) SELECT template_id, environment_id FROM __temp__environment_template');
        $this->addSql('DROP TABLE __temp__environment_template');
        $this->addSql('CREATE INDEX IDX_735C713F5DA0FB8 ON environment_template (template_id)');
        $this->addSql('CREATE INDEX IDX_735C713F903E3A94 ON environment_template (environment_id)');
        $this->addSql('DROP INDEX IDX_FEFDAB8E1A445520');
        $this->addSql('CREATE TEMPORARY TABLE __temp__view AS SELECT id, content_type_id, created, modified, name, type, icon, orderKey, public, options FROM "view"');
        $this->addSql('DROP TABLE "view"');
        $this->addSql('CREATE TABLE "view" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL COLLATE BINARY, type VARCHAR(255) NOT NULL COLLATE BINARY, icon VARCHAR(255) DEFAULT NULL COLLATE BINARY, orderKey INTEGER NOT NULL, public BOOLEAN DEFAULT \'0\' NOT NULL, options CLOB DEFAULT NULL COLLATE BINARY --(DC2Type:json_array)
        , CONSTRAINT FK_FEFDAB8E1A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "view" (id, content_type_id, created, modified, name, type, icon, orderKey, public, options) SELECT id, content_type_id, created, modified, name, type, icon, orderKey, public, options FROM __temp__view');
        $this->addSql('DROP TABLE __temp__view');
        $this->addSql('CREATE INDEX IDX_FEFDAB8E1A445520 ON "view" (content_type_id)');
        $this->addSql('DROP INDEX IDX_FEAD46B31A445520');
        $this->addSql('DROP INDEX IDX_FEAD46B3903E3A94');
        $this->addSql('CREATE TEMPORARY TABLE __temp__single_type_index AS SELECT id, content_type_id, environment_id, created, modified, name FROM single_type_index');
        $this->addSql('DROP TABLE single_type_index');
        $this->addSql('CREATE TABLE single_type_index (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, environment_id INTEGER DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL COLLATE BINARY, CONSTRAINT FK_FEAD46B31A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_FEAD46B3903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO single_type_index (id, content_type_id, environment_id, created, modified, name) SELECT id, content_type_id, environment_id, created, modified, name FROM __temp__single_type_index');
        $this->addSql('DROP TABLE __temp__single_type_index');
        $this->addSql('CREATE INDEX IDX_FEAD46B31A445520 ON single_type_index (content_type_id)');
        $this->addSql('CREATE INDEX IDX_FEAD46B3903E3A94 ON single_type_index (environment_id)');
        $this->addSql('DROP INDEX UNIQ_B4F0DBA71A445520');
        $this->addSql('CREATE TEMPORARY TABLE __temp__search AS SELECT id, content_type_id, username, contentTypes, name, sort_by, sort_order, default_search, environments, minimum_should_match FROM search');
        $this->addSql('DROP TABLE search');
        $this->addSql('CREATE TABLE search (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, username VARCHAR(100) NOT NULL COLLATE BINARY, contentTypes CLOB NOT NULL COLLATE BINARY --(DC2Type:json_array)
        , name VARCHAR(100) NOT NULL COLLATE BINARY, sort_by VARCHAR(100) DEFAULT NULL COLLATE BINARY, sort_order VARCHAR(100) DEFAULT NULL COLLATE BINARY, default_search BOOLEAN DEFAULT \'0\' NOT NULL, environments CLOB NOT NULL COLLATE BINARY --(DC2Type:json_array)
        , minimum_should_match INTEGER DEFAULT 1 NOT NULL, CONSTRAINT FK_B4F0DBA71A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO search (id, content_type_id, username, contentTypes, name, sort_by, sort_order, default_search, environments, minimum_should_match) SELECT id, content_type_id, username, contentTypes, name, sort_by, sort_order, default_search, environments, minimum_should_match FROM __temp__search');
        $this->addSql('DROP TABLE __temp__search');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B4F0DBA71A445520 ON search (content_type_id)');
        $this->addSql('DROP INDEX IDX_A6263002650760A9');
        $this->addSql('CREATE TEMPORARY TABLE __temp__search_filter AS SELECT id, search_id, pattern, field, boolean_clause, operator, boost FROM search_filter');
        $this->addSql('DROP TABLE search_filter');
        $this->addSql('CREATE TABLE search_filter (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, search_id BIGINT DEFAULT NULL, pattern VARCHAR(200) DEFAULT NULL COLLATE BINARY, field VARCHAR(100) DEFAULT NULL COLLATE BINARY, boolean_clause VARCHAR(20) DEFAULT NULL COLLATE BINARY, operator VARCHAR(50) NOT NULL COLLATE BINARY, boost NUMERIC(10, 2) DEFAULT NULL, CONSTRAINT FK_A6263002650760A9 FOREIGN KEY (search_id) REFERENCES search (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO search_filter (id, search_id, pattern, field, boolean_clause, operator, boost) SELECT id, search_id, pattern, field, boolean_clause, operator, boost FROM __temp__search_filter');
        $this->addSql('DROP TABLE __temp__search_filter');
        $this->addSql('CREATE INDEX IDX_A6263002650760A9 ON search_filter (search_id)');
        $this->addSql('DROP INDEX UNIQ_8D93D64992FC23A8');
        $this->addSql('DROP INDEX UNIQ_8D93D649A0D96FBF');
        $this->addSql('DROP INDEX UNIQ_8D93D649C05FB297');
        $this->addSql('DROP INDEX IDX_8D93D649A282F7EA');
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, wysiwyg_profile_id, username, username_canonical, email, email_canonical, enabled, salt, password, last_login, confirmation_token, password_requested_at, created, modified, display_name, allowed_to_configure_wysiwyg, wysiwyg_options, layout_boxed, sidebar_mini, sidebar_collapse, email_notification, roles, circles FROM user');
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, wysiwyg_profile_id INTEGER DEFAULT NULL, username VARCHAR(180) NOT NULL COLLATE BINARY, username_canonical VARCHAR(180) NOT NULL COLLATE BINARY, email VARCHAR(180) NOT NULL COLLATE BINARY, email_canonical VARCHAR(180) NOT NULL COLLATE BINARY, enabled BOOLEAN NOT NULL, salt VARCHAR(255) DEFAULT NULL COLLATE BINARY, password VARCHAR(255) NOT NULL COLLATE BINARY, last_login DATETIME DEFAULT NULL, confirmation_token VARCHAR(180) DEFAULT NULL COLLATE BINARY, password_requested_at DATETIME DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, display_name VARCHAR(255) DEFAULT NULL COLLATE BINARY, allowed_to_configure_wysiwyg BOOLEAN DEFAULT NULL, wysiwyg_options CLOB DEFAULT NULL COLLATE BINARY, layout_boxed BOOLEAN NOT NULL, sidebar_mini BOOLEAN NOT NULL, sidebar_collapse BOOLEAN NOT NULL, email_notification BOOLEAN NOT NULL, roles CLOB NOT NULL COLLATE BINARY --(DC2Type:array)
        , circles CLOB DEFAULT NULL COLLATE BINARY --(DC2Type:json_array)
        , CONSTRAINT FK_8D93D649A282F7EA FOREIGN KEY (wysiwyg_profile_id) REFERENCES wysiwyg_profile (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO user (id, wysiwyg_profile_id, username, username_canonical, email, email_canonical, enabled, salt, password, last_login, confirmation_token, password_requested_at, created, modified, display_name, allowed_to_configure_wysiwyg, wysiwyg_options, layout_boxed, sidebar_mini, sidebar_collapse, email_notification, roles, circles) SELECT id, wysiwyg_profile_id, username, username_canonical, email, email_canonical, enabled, salt, password, last_login, confirmation_token, password_requested_at, created, modified, display_name, allowed_to_configure_wysiwyg, wysiwyg_options, layout_boxed, sidebar_mini, sidebar_collapse, email_notification, roles, circles FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64992FC23A8 ON user (username_canonical)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649A0D96FBF ON user (email_canonical)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649C05FB297 ON user (confirmation_token)');
        $this->addSql('CREATE INDEX IDX_8D93D649A282F7EA ON user (wysiwyg_profile_id)');
        $this->addSql('DROP INDEX IDX_AEFF00A6422B0E0C');
        $this->addSql('CREATE TEMPORARY TABLE __temp__form_submission_file AS SELECT id, form_submission_id, created, modified, file, filename, form_field, mime_type, size FROM form_submission_file');
        $this->addSql('DROP TABLE form_submission_file');
        $this->addSql('CREATE TABLE form_submission_file (id CHAR(36) NOT NULL COLLATE BINARY --(DC2Type:uuid)
        , form_submission_id CHAR(36) DEFAULT NULL COLLATE BINARY --(DC2Type:uuid)
        , created DATETIME NOT NULL, modified DATETIME NOT NULL, file BLOB NOT NULL, filename VARCHAR(255) NOT NULL COLLATE BINARY, form_field VARCHAR(255) NOT NULL COLLATE BINARY, mime_type VARCHAR(1024) NOT NULL COLLATE BINARY, size BIGINT NOT NULL, PRIMARY KEY(id), CONSTRAINT FK_AEFF00A6422B0E0C FOREIGN KEY (form_submission_id) REFERENCES form_submission (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO form_submission_file (id, form_submission_id, created, modified, file, filename, form_field, mime_type, size) SELECT id, form_submission_id, created, modified, file, filename, form_field, mime_type, size FROM __temp__form_submission_file');
        $this->addSql('DROP TABLE __temp__form_submission_file');
        $this->addSql('CREATE INDEX IDX_AEFF00A6422B0E0C ON form_submission_file (form_submission_id)');
        $this->addSql('DROP INDEX auth_tokens_value_unique');
        $this->addSql('DROP INDEX IDX_8AF9B66CA76ED395');
        $this->addSql('CREATE TEMPORARY TABLE __temp__auth_tokens AS SELECT id, user_id, value, created, modified FROM auth_tokens');
        $this->addSql('DROP TABLE auth_tokens');
        $this->addSql('CREATE TABLE auth_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER DEFAULT NULL, value VARCHAR(255) NOT NULL COLLATE BINARY, created DATETIME NOT NULL, modified DATETIME NOT NULL, CONSTRAINT FK_8AF9B66CA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO auth_tokens (id, user_id, value, created, modified) SELECT id, user_id, value, created, modified FROM __temp__auth_tokens');
        $this->addSql('DROP TABLE __temp__auth_tokens');
        $this->addSql('CREATE UNIQUE INDEX auth_tokens_value_unique ON auth_tokens (value)');
        $this->addSql('CREATE INDEX IDX_8AF9B66CA76ED395 ON auth_tokens (user_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__form_submission AS SELECT id, created, modified, name, instance, locale, data, process_try_counter, process_id FROM form_submission');
        $this->addSql('DROP TABLE form_submission');
        $this->addSql('CREATE TABLE form_submission (id CHAR(36) NOT NULL COLLATE BINARY --(DC2Type:uuid)
        , created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL COLLATE BINARY, instance VARCHAR(255) NOT NULL COLLATE BINARY, locale VARCHAR(2) NOT NULL COLLATE BINARY, data CLOB NOT NULL COLLATE BINARY --(DC2Type:json_array)
        , process_id VARCHAR(255) DEFAULT NULL COLLATE BINARY, process_try_counter INTEGER DEFAULT 0 NOT NULL, PRIMARY KEY(id))');
        $this->addSql('INSERT INTO form_submission (id, created, modified, name, instance, locale, data, process_try_counter, process_id) SELECT id, created, modified, name, instance, locale, data, process_try_counter, process_id FROM __temp__form_submission');
        $this->addSql('DROP TABLE __temp__form_submission');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP TABLE session');
        $this->addSql('DROP INDEX IDX_8AF9B66CA76ED395');
        $this->addSql('DROP INDEX auth_tokens_value_unique');
        $this->addSql('CREATE TEMPORARY TABLE __temp__auth_tokens AS SELECT id, user_id, value, created, modified FROM auth_tokens');
        $this->addSql('DROP TABLE auth_tokens');
        $this->addSql('CREATE TABLE auth_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER DEFAULT NULL, value VARCHAR(255) NOT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL)');
        $this->addSql('INSERT INTO auth_tokens (id, user_id, value, created, modified) SELECT id, user_id, value, created, modified FROM __temp__auth_tokens');
        $this->addSql('DROP TABLE __temp__auth_tokens');
        $this->addSql('CREATE INDEX IDX_8AF9B66CA76ED395 ON auth_tokens (user_id)');
        $this->addSql('CREATE UNIQUE INDEX auth_tokens_value_unique ON auth_tokens (value)');
        $this->addSql('DROP INDEX UNIQ_41BCBAEC588AB49A');
        $this->addSql('DROP INDEX IDX_41BCBAEC903E3A94');
        $this->addSql('CREATE TEMPORARY TABLE __temp__content_type AS SELECT id, field_types_id, environment_id, created, modified, name, pluralName, singularName, icon, description, indexTwig, extra, lockBy, lockUntil, circles_field, business_id_field, deleted, have_pipelines, ask_for_ouuid, dirty, color, labelField, color_field, parentField, userField, dateField, startDateField, endDateField, locationField, referer_field_name, category_field, ouuidField, imageField, videoField, email_field, asset_field, order_field, sort_by, sort_order, create_role, edit_role, view_role, publish_role, trash_role, orderKey, rootContentType, edit_twig_with_wysiwyg, web_content, auto_publish, active, default_value, translationField, localeField, searchLinkDisplayRole, createLinkDisplayRole FROM content_type');
        $this->addSql('DROP TABLE content_type');
        $this->addSql('CREATE TABLE content_type (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, field_types_id INTEGER DEFAULT NULL, environment_id INTEGER DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(100) NOT NULL, pluralName VARCHAR(100) NOT NULL, singularName VARCHAR(100) NOT NULL, icon VARCHAR(100) DEFAULT NULL, description CLOB DEFAULT NULL, indexTwig CLOB DEFAULT NULL, extra CLOB DEFAULT NULL, lockBy VARCHAR(100) DEFAULT NULL, lockUntil DATETIME DEFAULT NULL, circles_field VARCHAR(100) DEFAULT NULL, business_id_field VARCHAR(100) DEFAULT NULL, deleted BOOLEAN NOT NULL, have_pipelines BOOLEAN DEFAULT NULL, ask_for_ouuid BOOLEAN NOT NULL, dirty BOOLEAN NOT NULL, color VARCHAR(50) DEFAULT NULL, labelField VARCHAR(100) DEFAULT NULL, color_field VARCHAR(100) DEFAULT NULL, parentField VARCHAR(100) DEFAULT NULL, userField VARCHAR(100) DEFAULT NULL, dateField VARCHAR(100) DEFAULT NULL, startDateField VARCHAR(100) DEFAULT NULL, endDateField VARCHAR(100) DEFAULT NULL, locationField VARCHAR(100) DEFAULT NULL, referer_field_name VARCHAR(100) DEFAULT NULL, category_field VARCHAR(100) DEFAULT NULL, ouuidField VARCHAR(100) DEFAULT NULL, imageField VARCHAR(100) DEFAULT NULL, videoField VARCHAR(100) DEFAULT NULL, email_field VARCHAR(100) DEFAULT NULL, asset_field VARCHAR(100) DEFAULT NULL, order_field VARCHAR(100) DEFAULT NULL, sort_by VARCHAR(100) DEFAULT NULL, sort_order VARCHAR(4) DEFAULT \'asc\', create_role VARCHAR(100) DEFAULT NULL, edit_role VARCHAR(100) DEFAULT NULL, view_role VARCHAR(100) DEFAULT NULL, publish_role VARCHAR(100) DEFAULT NULL, trash_role VARCHAR(100) DEFAULT NULL, orderKey INTEGER NOT NULL, rootContentType BOOLEAN NOT NULL, edit_twig_with_wysiwyg BOOLEAN NOT NULL, web_content BOOLEAN DEFAULT \'1\' NOT NULL, auto_publish BOOLEAN DEFAULT \'0\' NOT NULL, active BOOLEAN NOT NULL, default_value CLOB DEFAULT NULL, translationField VARCHAR(100) DEFAULT NULL, localeField VARCHAR(100) DEFAULT NULL, searchLinkDisplayRole VARCHAR(255) DEFAULT \'ROLE_USER\' NOT NULL, createLinkDisplayRole VARCHAR(255) DEFAULT \'ROLE_USER\' NOT NULL)');
        $this->addSql('INSERT INTO content_type (id, field_types_id, environment_id, created, modified, name, pluralName, singularName, icon, description, indexTwig, extra, lockBy, lockUntil, circles_field, business_id_field, deleted, have_pipelines, ask_for_ouuid, dirty, color, labelField, color_field, parentField, userField, dateField, startDateField, endDateField, locationField, referer_field_name, category_field, ouuidField, imageField, videoField, email_field, asset_field, order_field, sort_by, sort_order, create_role, edit_role, view_role, publish_role, trash_role, orderKey, rootContentType, edit_twig_with_wysiwyg, web_content, auto_publish, active, default_value, translationField, localeField, searchLinkDisplayRole, createLinkDisplayRole) SELECT id, field_types_id, environment_id, created, modified, name, pluralName, singularName, icon, description, indexTwig, extra, lockBy, lockUntil, circles_field, business_id_field, deleted, have_pipelines, ask_for_ouuid, dirty, color, labelField, color_field, parentField, userField, dateField, startDateField, endDateField, locationField, referer_field_name, category_field, ouuidField, imageField, videoField, email_field, asset_field, order_field, sort_by, sort_order, create_role, edit_role, view_role, publish_role, trash_role, orderKey, rootContentType, edit_twig_with_wysiwyg, web_content, auto_publish, active, default_value, translationField, localeField, searchLinkDisplayRole, createLinkDisplayRole FROM __temp__content_type');
        $this->addSql('DROP TABLE __temp__content_type');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_41BCBAEC588AB49A ON content_type (field_types_id)');
        $this->addSql('CREATE INDEX IDX_41BCBAEC903E3A94 ON content_type (environment_id)');
        $this->addSql('DROP INDEX IDX_895F7B701DFA7C8F');
        $this->addSql('DROP INDEX IDX_895F7B70903E3A94');
        $this->addSql('CREATE TEMPORARY TABLE __temp__environment_revision AS SELECT revision_id, environment_id FROM environment_revision');
        $this->addSql('DROP TABLE environment_revision');
        $this->addSql('CREATE TABLE environment_revision (revision_id INTEGER NOT NULL, environment_id INTEGER NOT NULL, PRIMARY KEY(revision_id, environment_id))');
        $this->addSql('INSERT INTO environment_revision (revision_id, environment_id) SELECT revision_id, environment_id FROM __temp__environment_revision');
        $this->addSql('DROP TABLE __temp__environment_revision');
        $this->addSql('CREATE INDEX IDX_895F7B701DFA7C8F ON environment_revision (revision_id)');
        $this->addSql('CREATE INDEX IDX_895F7B70903E3A94 ON environment_revision (environment_id)');
        $this->addSql('DROP INDEX IDX_735C713F5DA0FB8');
        $this->addSql('DROP INDEX IDX_735C713F903E3A94');
        $this->addSql('CREATE TEMPORARY TABLE __temp__environment_template AS SELECT template_id, environment_id FROM environment_template');
        $this->addSql('DROP TABLE environment_template');
        $this->addSql('CREATE TABLE environment_template (template_id INTEGER NOT NULL, environment_id INTEGER NOT NULL, PRIMARY KEY(template_id, environment_id))');
        $this->addSql('INSERT INTO environment_template (template_id, environment_id) SELECT template_id, environment_id FROM __temp__environment_template');
        $this->addSql('DROP TABLE __temp__environment_template');
        $this->addSql('CREATE INDEX IDX_735C713F5DA0FB8 ON environment_template (template_id)');
        $this->addSql('CREATE INDEX IDX_735C713F903E3A94 ON environment_template (environment_id)');
        $this->addSql('DROP INDEX UNIQ_9F123E931A445520');
        $this->addSql('DROP INDEX IDX_9F123E93727ACA70');
        $this->addSql('CREATE TEMPORARY TABLE __temp__field_type AS SELECT id, content_type_id, parent_id, created, modified, type, name, deleted, description, options, orderKey FROM field_type');
        $this->addSql('DROP TABLE field_type');
        $this->addSql('CREATE TABLE field_type (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, parent_id INTEGER DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, type VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, deleted BOOLEAN NOT NULL, description CLOB DEFAULT NULL, options CLOB DEFAULT NULL --(DC2Type:json_array)
        , orderKey INTEGER NOT NULL)');
        $this->addSql('INSERT INTO field_type (id, content_type_id, parent_id, created, modified, type, name, deleted, description, options, orderKey) SELECT id, content_type_id, parent_id, created, modified, type, name, deleted, description, options, orderKey FROM __temp__field_type');
        $this->addSql('DROP TABLE __temp__field_type');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9F123E931A445520 ON field_type (content_type_id)');
        $this->addSql('CREATE INDEX IDX_9F123E93727ACA70 ON field_type (parent_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__form_submission AS SELECT id, created, modified, name, instance, locale, data, process_try_counter, process_id FROM form_submission');
        $this->addSql('DROP TABLE form_submission');
        $this->addSql('CREATE TABLE form_submission (id CHAR(36) NOT NULL --(DC2Type:uuid)
        , created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL, instance VARCHAR(255) NOT NULL, locale VARCHAR(2) NOT NULL, data CLOB NOT NULL --(DC2Type:json_array)
        , process_id VARCHAR(255) DEFAULT NULL, process_try_counter INTEGER DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('INSERT INTO form_submission (id, created, modified, name, instance, locale, data, process_try_counter, process_id) SELECT id, created, modified, name, instance, locale, data, process_try_counter, process_id FROM __temp__form_submission');
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
        $this->addSql('DROP INDEX IDX_BF5476CA5DA0FB8');
        $this->addSql('DROP INDEX IDX_BF5476CA1DFA7C8F');
        $this->addSql('DROP INDEX IDX_BF5476CA903E3A94');
        $this->addSql('CREATE TEMPORARY TABLE __temp__notification AS SELECT id, template_id, revision_id, environment_id, created, modified, username, status, sent_timestamp, response_text, response_timestamp, response_by, emailed, response_emailed FROM notification');
        $this->addSql('DROP TABLE notification');
        $this->addSql('CREATE TABLE notification (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, template_id INTEGER DEFAULT NULL, revision_id INTEGER DEFAULT NULL, environment_id INTEGER DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, username VARCHAR(100) NOT NULL, status VARCHAR(20) NOT NULL, sent_timestamp DATETIME NOT NULL, response_text CLOB DEFAULT NULL, response_timestamp DATETIME DEFAULT NULL, response_by VARCHAR(100) DEFAULT NULL, emailed DATETIME DEFAULT NULL, response_emailed DATETIME DEFAULT NULL)');
        $this->addSql('INSERT INTO notification (id, template_id, revision_id, environment_id, created, modified, username, status, sent_timestamp, response_text, response_timestamp, response_by, emailed, response_emailed) SELECT id, template_id, revision_id, environment_id, created, modified, username, status, sent_timestamp, response_text, response_timestamp, response_by, emailed, response_emailed FROM __temp__notification');
        $this->addSql('DROP TABLE __temp__notification');
        $this->addSql('CREATE INDEX IDX_BF5476CA5DA0FB8 ON notification (template_id)');
        $this->addSql('CREATE INDEX IDX_BF5476CA1DFA7C8F ON notification (revision_id)');
        $this->addSql('CREATE INDEX IDX_BF5476CA903E3A94 ON notification (environment_id)');
        $this->addSql('DROP INDEX IDX_6D6315CC1A445520');
        $this->addSql('DROP INDEX tuple_index');
        $this->addSql('CREATE TEMPORARY TABLE __temp__revision AS SELECT id, content_type_id, created, modified, auto_save_at, deleted, version, ouuid, start_time, end_time, draft, finalized_by, finalized_date, deleted_by, lock_by, auto_save_by, lock_until, raw_data, auto_save, circles, labelField, sha1 FROM revision');
        $this->addSql('DROP TABLE revision');
        $this->addSql('CREATE TABLE revision (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, auto_save_at DATETIME DEFAULT NULL, deleted BOOLEAN NOT NULL, version INTEGER DEFAULT 1 NOT NULL, start_time DATETIME NOT NULL, end_time DATETIME DEFAULT NULL, draft BOOLEAN NOT NULL, finalized_by VARCHAR(255) DEFAULT NULL, finalized_date DATETIME DEFAULT NULL, deleted_by VARCHAR(255) DEFAULT NULL, lock_by VARCHAR(255) DEFAULT NULL, auto_save_by VARCHAR(255) DEFAULT NULL, lock_until DATETIME DEFAULT NULL, raw_data CLOB DEFAULT NULL --(DC2Type:json_array)
        , auto_save CLOB DEFAULT NULL --(DC2Type:json_array)
        , circles CLOB DEFAULT NULL --(DC2Type:simple_array)
        , labelField CLOB DEFAULT NULL, sha1 VARCHAR(255) DEFAULT NULL, ouuid VARCHAR(255) DEFAULT NULL COLLATE BINARY)');
        $this->addSql('INSERT INTO revision (id, content_type_id, created, modified, auto_save_at, deleted, version, ouuid, start_time, end_time, draft, finalized_by, finalized_date, deleted_by, lock_by, auto_save_by, lock_until, raw_data, auto_save, circles, labelField, sha1) SELECT id, content_type_id, created, modified, auto_save_at, deleted, version, ouuid, start_time, end_time, draft, finalized_by, finalized_date, deleted_by, lock_by, auto_save_by, lock_until, raw_data, auto_save, circles, labelField, sha1 FROM __temp__revision');
        $this->addSql('DROP TABLE __temp__revision');
        $this->addSql('CREATE INDEX IDX_6D6315CC1A445520 ON revision (content_type_id)');
        $this->addSql('CREATE UNIQUE INDEX tuple_index ON revision (end_time, ouuid)');
        $this->addSql('DROP INDEX UNIQ_B4F0DBA71A445520');
        $this->addSql('CREATE TEMPORARY TABLE __temp__search AS SELECT id, content_type_id, username, environments, contentTypes, name, default_search, sort_by, sort_order, minimum_should_match FROM search');
        $this->addSql('DROP TABLE search');
        $this->addSql('CREATE TABLE search (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, username VARCHAR(100) NOT NULL, environments CLOB NOT NULL --(DC2Type:json_array)
        , contentTypes CLOB NOT NULL --(DC2Type:json_array)
        , name VARCHAR(100) NOT NULL, default_search BOOLEAN DEFAULT \'0\' NOT NULL, sort_by VARCHAR(100) DEFAULT NULL, sort_order VARCHAR(100) DEFAULT NULL, minimum_should_match INTEGER DEFAULT 1 NOT NULL)');
        $this->addSql('INSERT INTO search (id, content_type_id, username, environments, contentTypes, name, default_search, sort_by, sort_order, minimum_should_match) SELECT id, content_type_id, username, environments, contentTypes, name, default_search, sort_by, sort_order, minimum_should_match FROM __temp__search');
        $this->addSql('DROP TABLE __temp__search');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B4F0DBA71A445520 ON search (content_type_id)');
        $this->addSql('DROP INDEX IDX_A6263002650760A9');
        $this->addSql('CREATE TEMPORARY TABLE __temp__search_filter AS SELECT id, search_id, pattern, field, boolean_clause, operator, boost FROM search_filter');
        $this->addSql('DROP TABLE search_filter');
        $this->addSql('CREATE TABLE search_filter (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, search_id BIGINT DEFAULT NULL, pattern VARCHAR(200) DEFAULT NULL, field VARCHAR(100) DEFAULT NULL, boolean_clause VARCHAR(20) DEFAULT NULL, operator VARCHAR(50) NOT NULL, boost NUMERIC(10, 2) DEFAULT NULL)');
        $this->addSql('INSERT INTO search_filter (id, search_id, pattern, field, boolean_clause, operator, boost) SELECT id, search_id, pattern, field, boolean_clause, operator, boost FROM __temp__search_filter');
        $this->addSql('DROP TABLE __temp__search_filter');
        $this->addSql('CREATE INDEX IDX_A6263002650760A9 ON search_filter (search_id)');
        $this->addSql('DROP INDEX IDX_FEAD46B31A445520');
        $this->addSql('DROP INDEX IDX_FEAD46B3903E3A94');
        $this->addSql('CREATE TEMPORARY TABLE __temp__single_type_index AS SELECT id, content_type_id, environment_id, created, modified, name FROM single_type_index');
        $this->addSql('DROP TABLE single_type_index');
        $this->addSql('CREATE TABLE single_type_index (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, environment_id INTEGER DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL)');
        $this->addSql('INSERT INTO single_type_index (id, content_type_id, environment_id, created, modified, name) SELECT id, content_type_id, environment_id, created, modified, name FROM __temp__single_type_index');
        $this->addSql('DROP TABLE __temp__single_type_index');
        $this->addSql('CREATE INDEX IDX_FEAD46B31A445520 ON single_type_index (content_type_id)');
        $this->addSql('CREATE INDEX IDX_FEAD46B3903E3A94 ON single_type_index (environment_id)');
        $this->addSql('DROP INDEX IDX_97601F831A445520');
        $this->addSql('CREATE TEMPORARY TABLE __temp__template AS SELECT id, content_type_id, created, modified, name, icon, body, header, edit_with_wysiwyg, render_option, orderKey, accumulate_in_one_file, preview, mime_type, filename, extension, active, role, role_to, role_cc, circles_to, response_template, email_content_type, allow_origin, disposition, orientation, size, public FROM template');
        $this->addSql('DROP TABLE template');
        $this->addSql('CREATE TABLE template (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL, icon VARCHAR(255) DEFAULT NULL, body CLOB DEFAULT NULL, header CLOB DEFAULT NULL, edit_with_wysiwyg BOOLEAN NOT NULL, render_option VARCHAR(255) NOT NULL, orderKey INTEGER NOT NULL, accumulate_in_one_file BOOLEAN NOT NULL, preview BOOLEAN NOT NULL, mime_type VARCHAR(255) DEFAULT NULL, filename CLOB DEFAULT NULL, extension VARCHAR(255) DEFAULT NULL, active BOOLEAN NOT NULL, role VARCHAR(255) NOT NULL, role_to VARCHAR(255) NOT NULL, role_cc VARCHAR(255) NOT NULL, circles_to CLOB DEFAULT NULL --(DC2Type:json_array)
        , response_template CLOB DEFAULT NULL, email_content_type VARCHAR(255) DEFAULT NULL, allow_origin VARCHAR(255) DEFAULT NULL, disposition VARCHAR(20) DEFAULT NULL, orientation VARCHAR(20) DEFAULT NULL, size VARCHAR(20) DEFAULT NULL, public BOOLEAN DEFAULT \'0\' NOT NULL)');
        $this->addSql('INSERT INTO template (id, content_type_id, created, modified, name, icon, body, header, edit_with_wysiwyg, render_option, orderKey, accumulate_in_one_file, preview, mime_type, filename, extension, active, role, role_to, role_cc, circles_to, response_template, email_content_type, allow_origin, disposition, orientation, size, public) SELECT id, content_type_id, created, modified, name, icon, body, header, edit_with_wysiwyg, render_option, orderKey, accumulate_in_one_file, preview, mime_type, filename, extension, active, role, role_to, role_cc, circles_to, response_template, email_content_type, allow_origin, disposition, orientation, size, public FROM __temp__template');
        $this->addSql('DROP TABLE __temp__template');
        $this->addSql('CREATE INDEX IDX_97601F831A445520 ON template (content_type_id)');
        $this->addSql('DROP INDEX UNIQ_8D93D64992FC23A8');
        $this->addSql('DROP INDEX UNIQ_8D93D649A0D96FBF');
        $this->addSql('DROP INDEX UNIQ_8D93D649C05FB297');
        $this->addSql('DROP INDEX IDX_8D93D649A282F7EA');
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, wysiwyg_profile_id, username, username_canonical, email, email_canonical, enabled, salt, password, last_login, confirmation_token, password_requested_at, roles, created, modified, circles, display_name, allowed_to_configure_wysiwyg, wysiwyg_options, layout_boxed, email_notification, sidebar_mini, sidebar_collapse FROM "user"');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('CREATE TABLE "user" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, wysiwyg_profile_id INTEGER DEFAULT NULL, username VARCHAR(180) NOT NULL, username_canonical VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, email_canonical VARCHAR(180) NOT NULL, enabled BOOLEAN NOT NULL, salt VARCHAR(255) DEFAULT NULL, password VARCHAR(255) NOT NULL, last_login DATETIME DEFAULT NULL, confirmation_token VARCHAR(180) DEFAULT NULL, password_requested_at DATETIME DEFAULT NULL, roles CLOB NOT NULL --(DC2Type:array)
        , created DATETIME NOT NULL, modified DATETIME NOT NULL, circles CLOB DEFAULT NULL --(DC2Type:json_array)
        , display_name VARCHAR(255) DEFAULT NULL, allowed_to_configure_wysiwyg BOOLEAN DEFAULT NULL, wysiwyg_options CLOB DEFAULT NULL, layout_boxed BOOLEAN NOT NULL, email_notification BOOLEAN NOT NULL, sidebar_mini BOOLEAN NOT NULL, sidebar_collapse BOOLEAN NOT NULL)');
        $this->addSql('INSERT INTO "user" (id, wysiwyg_profile_id, username, username_canonical, email, email_canonical, enabled, salt, password, last_login, confirmation_token, password_requested_at, roles, created, modified, circles, display_name, allowed_to_configure_wysiwyg, wysiwyg_options, layout_boxed, email_notification, sidebar_mini, sidebar_collapse) SELECT id, wysiwyg_profile_id, username, username_canonical, email, email_canonical, enabled, salt, password, last_login, confirmation_token, password_requested_at, roles, created, modified, circles, display_name, allowed_to_configure_wysiwyg, wysiwyg_options, layout_boxed, email_notification, sidebar_mini, sidebar_collapse FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64992FC23A8 ON "user" (username_canonical)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649A0D96FBF ON "user" (email_canonical)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649C05FB297 ON "user" (confirmation_token)');
        $this->addSql('CREATE INDEX IDX_8D93D649A282F7EA ON "user" (wysiwyg_profile_id)');
        $this->addSql('DROP INDEX IDX_FEFDAB8E1A445520');
        $this->addSql('CREATE TEMPORARY TABLE __temp__view AS SELECT id, content_type_id, created, modified, name, type, icon, options, orderKey, public FROM "view"');
        $this->addSql('DROP TABLE "view"');
        $this->addSql('CREATE TABLE "view" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, icon VARCHAR(255) DEFAULT NULL, options CLOB DEFAULT NULL --(DC2Type:json_array)
        , orderKey INTEGER NOT NULL, public BOOLEAN DEFAULT \'0\' NOT NULL)');
        $this->addSql('INSERT INTO "view" (id, content_type_id, created, modified, name, type, icon, options, orderKey, public) SELECT id, content_type_id, created, modified, name, type, icon, options, orderKey, public FROM __temp__view');
        $this->addSql('DROP TABLE __temp__view');
        $this->addSql('CREATE INDEX IDX_FEFDAB8E1A445520 ON "view" (content_type_id)');
    }
}
