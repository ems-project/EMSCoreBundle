<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20170514085008 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('CREATE SEQUENCE revision_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE content_type_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE environment_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE notification_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE audit_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE auth_tokens_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE field_type_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE search_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE search_filter_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE i18n_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE job_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE template_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE uploaded_asset_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE view_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "user_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE revision (id INT NOT NULL, content_type_id BIGINT DEFAULT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, auto_save_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted BOOLEAN NOT NULL, version INT DEFAULT 1 NOT NULL, ouuid VARCHAR(255) DEFAULT NULL, start_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_time TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, draft BOOLEAN NOT NULL, lock_by VARCHAR(255) DEFAULT NULL, auto_save_by VARCHAR(255) DEFAULT NULL, lock_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, raw_data JSON DEFAULT NULL, auto_save JSON DEFAULT NULL, circles TEXT DEFAULT NULL, labelField VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6D6315CC1A445520 ON revision (content_type_id)');
        $this->addSql('CREATE UNIQUE INDEX tuple_index ON revision (end_time, ouuid)');
        $this->addSql('COMMENT ON COLUMN revision.circles IS \'(DC2Type:simple_array)\'');
        $this->addSql('CREATE TABLE environment_revision (revision_id INT NOT NULL, environment_id INT NOT NULL, PRIMARY KEY(revision_id, environment_id))');
        $this->addSql('CREATE INDEX IDX_895F7B701DFA7C8F ON environment_revision (revision_id)');
        $this->addSql('CREATE INDEX IDX_895F7B70903E3A94 ON environment_revision (environment_id)');
        $this->addSql('CREATE TABLE content_type (id BIGINT NOT NULL, field_types_id INT DEFAULT NULL, environment_id INT DEFAULT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, name VARCHAR(100) NOT NULL, pluralName VARCHAR(100) NOT NULL, singularName VARCHAR(100) NOT NULL, icon VARCHAR(100) DEFAULT NULL, description TEXT DEFAULT NULL, indexTwig TEXT DEFAULT NULL, extra TEXT DEFAULT NULL, lockBy VARCHAR(100) DEFAULT NULL, lockUntil TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, circles_field VARCHAR(100) DEFAULT NULL, deleted BOOLEAN NOT NULL, have_pipelines BOOLEAN DEFAULT NULL, ask_for_ouuid BOOLEAN NOT NULL, dirty BOOLEAN NOT NULL, color VARCHAR(50) DEFAULT NULL, labelField VARCHAR(100) DEFAULT NULL, color_field VARCHAR(100) DEFAULT NULL, parentField VARCHAR(100) DEFAULT NULL, userField VARCHAR(100) DEFAULT NULL, dateField VARCHAR(100) DEFAULT NULL, startDateField VARCHAR(100) DEFAULT NULL, endDateField VARCHAR(100) DEFAULT NULL, locationField VARCHAR(100) DEFAULT NULL, referer_field_name VARCHAR(100) DEFAULT NULL, category_field VARCHAR(100) DEFAULT NULL, ouuidField VARCHAR(100) DEFAULT NULL, imageField VARCHAR(100) DEFAULT NULL, videoField VARCHAR(100) DEFAULT NULL, email_field VARCHAR(100) DEFAULT NULL, asset_field VARCHAR(100) DEFAULT NULL, order_field VARCHAR(100) DEFAULT NULL, sort_by VARCHAR(100) DEFAULT NULL, create_role VARCHAR(100) DEFAULT NULL, edit_role VARCHAR(100) DEFAULT NULL, view_role VARCHAR(100) DEFAULT NULL, orderKey INT NOT NULL, rootContentType BOOLEAN NOT NULL, edit_twig_with_wysiwyg BOOLEAN NOT NULL, active BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_41BCBAEC588AB49A ON content_type (field_types_id)');
        $this->addSql('CREATE INDEX IDX_41BCBAEC903E3A94 ON content_type (environment_id)');
        $this->addSql('CREATE TABLE environment (id INT NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, name VARCHAR(255) NOT NULL, alias VARCHAR(255) NOT NULL, color VARCHAR(50) DEFAULT NULL, baseUrl VARCHAR(1024) DEFAULT NULL, managed BOOLEAN NOT NULL, circles JSON DEFAULT NULL, in_default_search BOOLEAN DEFAULT NULL, extra TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4626DE225E237E06 ON environment (name)');
        $this->addSql('CREATE TABLE notification (id INT NOT NULL, template_id INT DEFAULT NULL, revision_id INT DEFAULT NULL, environment_id INT DEFAULT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, username VARCHAR(100) NOT NULL, status VARCHAR(20) NOT NULL, sent_timestamp TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, response_text TEXT DEFAULT NULL, response_timestamp TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, response_by VARCHAR(100) DEFAULT NULL, emailed TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, response_emailed TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BF5476CA5DA0FB8 ON notification (template_id)');
        $this->addSql('CREATE INDEX IDX_BF5476CA1DFA7C8F ON notification (revision_id)');
        $this->addSql('CREATE INDEX IDX_BF5476CA903E3A94 ON notification (environment_id)');
        $this->addSql('CREATE TABLE audit (id INT NOT NULL, date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, action VARCHAR(255) NOT NULL, raw_data TEXT DEFAULT NULL, username VARCHAR(255) NOT NULL, environment VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE auth_tokens (id INT NOT NULL, user_id INT DEFAULT NULL, value VARCHAR(255) NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8AF9B66CA76ED395 ON auth_tokens (user_id)');
        $this->addSql('CREATE UNIQUE INDEX auth_tokens_value_unique ON auth_tokens (value)');
        $this->addSql('CREATE TABLE field_type (id INT NOT NULL, content_type_id BIGINT DEFAULT NULL, parent_id INT DEFAULT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, type VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, deleted BOOLEAN NOT NULL, description TEXT DEFAULT NULL, options JSON DEFAULT NULL, orderKey INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9F123E931A445520 ON field_type (content_type_id)');
        $this->addSql('CREATE INDEX IDX_9F123E93727ACA70 ON field_type (parent_id)');
        $this->addSql('CREATE TABLE search (id BIGINT NOT NULL, "user" VARCHAR(100) NOT NULL, environments JSON NOT NULL, contentTypes JSON NOT NULL, name VARCHAR(100) NOT NULL, sort_by VARCHAR(100) DEFAULT NULL, sort_order VARCHAR(100) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE search_filter (id BIGINT NOT NULL, search_id BIGINT DEFAULT NULL, pattern VARCHAR(200) DEFAULT NULL, field VARCHAR(100) DEFAULT NULL, boolean_clause VARCHAR(20) DEFAULT NULL, operator VARCHAR(50) NOT NULL, boost NUMERIC(10, 2) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A6263002650760A9 ON search_filter (search_id)');
        $this->addSql('CREATE TABLE i18n (id INT NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, identifier VARCHAR(200) NOT NULL, content JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE job (id INT NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(2048) NOT NULL, output TEXT DEFAULT NULL, done BOOLEAN NOT NULL, started BOOLEAN NOT NULL, progress INT NOT NULL, arguments JSON DEFAULT NULL, "user" VARCHAR(255) DEFAULT NULL, service VARCHAR(255) DEFAULT NULL, command VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE template (id INT NOT NULL, content_type_id BIGINT DEFAULT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, name VARCHAR(255) NOT NULL, icon VARCHAR(255) DEFAULT NULL, body TEXT DEFAULT NULL, header TEXT DEFAULT NULL, edit_with_wysiwyg BOOLEAN NOT NULL, render_option VARCHAR(255) NOT NULL, orderKey INT NOT NULL, accumulate_in_one_file BOOLEAN NOT NULL, preview BOOLEAN NOT NULL, mime_type VARCHAR(255) DEFAULT NULL, filename TEXT DEFAULT NULL, extension VARCHAR(255) DEFAULT NULL, active BOOLEAN NOT NULL, role VARCHAR(255) NOT NULL, role_to VARCHAR(255) NOT NULL, role_cc VARCHAR(255) NOT NULL, circles_to JSON DEFAULT NULL, response_template TEXT DEFAULT NULL, email_content_type VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_97601F831A445520 ON template (content_type_id)');
        $this->addSql('CREATE TABLE environment_template (template_id INT NOT NULL, environment_id INT NOT NULL, PRIMARY KEY(template_id, environment_id))');
        $this->addSql('CREATE INDEX IDX_735C713F5DA0FB8 ON environment_template (template_id)');
        $this->addSql('CREATE INDEX IDX_735C713F903E3A94 ON environment_template (environment_id)');
        $this->addSql('CREATE TABLE uploaded_asset (id INT NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(64) DEFAULT NULL, sha1 VARCHAR(40) NOT NULL, name VARCHAR(1024) NOT NULL, type VARCHAR(1024) NOT NULL, "user" VARCHAR(255) NOT NULL, available BOOLEAN NOT NULL, size INT NOT NULL, uploaded INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE view (id INT NOT NULL, content_type_id BIGINT DEFAULT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, icon VARCHAR(255) DEFAULT NULL, options JSON DEFAULT NULL, orderKey INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_FEFDAB8E1A445520 ON view (content_type_id)');
        $this->addSql('CREATE TABLE "user" (id INT NOT NULL, username VARCHAR(180) NOT NULL, username_canonical VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, email_canonical VARCHAR(180) NOT NULL, enabled BOOLEAN NOT NULL, salt VARCHAR(255) DEFAULT NULL, password VARCHAR(255) NOT NULL, last_login TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, confirmation_token VARCHAR(180) DEFAULT NULL, password_requested_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, roles TEXT NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, circles JSON DEFAULT NULL, display_name VARCHAR(255) DEFAULT NULL, allowed_to_configure_wysiwyg BOOLEAN DEFAULT NULL, wysiwyg_profile TEXT DEFAULT NULL, wysiwyg_options TEXT DEFAULT NULL, layout_boxed BOOLEAN NOT NULL, sidebar_mini BOOLEAN NOT NULL, sidebar_collapse BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64992FC23A8 ON "user" (username_canonical)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649A0D96FBF ON "user" (email_canonical)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649C05FB297 ON "user" (confirmation_token)');
        $this->addSql('COMMENT ON COLUMN "user".roles IS \'(DC2Type:array)\'');
        $this->addSql('ALTER TABLE revision ADD CONSTRAINT FK_6D6315CC1A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B701DFA7C8F FOREIGN KEY (revision_id) REFERENCES revision (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B70903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE content_type ADD CONSTRAINT FK_41BCBAEC588AB49A FOREIGN KEY (field_types_id) REFERENCES field_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE content_type ADD CONSTRAINT FK_41BCBAEC903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA5DA0FB8 FOREIGN KEY (template_id) REFERENCES template (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA1DFA7C8F FOREIGN KEY (revision_id) REFERENCES revision (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE auth_tokens ADD CONSTRAINT FK_8AF9B66CA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE field_type ADD CONSTRAINT FK_9F123E931A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE field_type ADD CONSTRAINT FK_9F123E93727ACA70 FOREIGN KEY (parent_id) REFERENCES field_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE search_filter ADD CONSTRAINT FK_A6263002650760A9 FOREIGN KEY (search_id) REFERENCES search (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE template ADD CONSTRAINT FK_97601F831A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE environment_template ADD CONSTRAINT FK_735C713F5DA0FB8 FOREIGN KEY (template_id) REFERENCES template (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE environment_template ADD CONSTRAINT FK_735C713F903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE view ADD CONSTRAINT FK_FEFDAB8E1A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE environment_revision DROP CONSTRAINT FK_895F7B701DFA7C8F');
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CA1DFA7C8F');
        $this->addSql('ALTER TABLE revision DROP CONSTRAINT FK_6D6315CC1A445520');
        $this->addSql('ALTER TABLE field_type DROP CONSTRAINT FK_9F123E931A445520');
        $this->addSql('ALTER TABLE template DROP CONSTRAINT FK_97601F831A445520');
        $this->addSql('ALTER TABLE view DROP CONSTRAINT FK_FEFDAB8E1A445520');
        $this->addSql('ALTER TABLE environment_revision DROP CONSTRAINT FK_895F7B70903E3A94');
        $this->addSql('ALTER TABLE content_type DROP CONSTRAINT FK_41BCBAEC903E3A94');
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CA903E3A94');
        $this->addSql('ALTER TABLE environment_template DROP CONSTRAINT FK_735C713F903E3A94');
        $this->addSql('ALTER TABLE content_type DROP CONSTRAINT FK_41BCBAEC588AB49A');
        $this->addSql('ALTER TABLE field_type DROP CONSTRAINT FK_9F123E93727ACA70');
        $this->addSql('ALTER TABLE search_filter DROP CONSTRAINT FK_A6263002650760A9');
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CA5DA0FB8');
        $this->addSql('ALTER TABLE environment_template DROP CONSTRAINT FK_735C713F5DA0FB8');
        $this->addSql('ALTER TABLE auth_tokens DROP CONSTRAINT FK_8AF9B66CA76ED395');
        $this->addSql('DROP SEQUENCE revision_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE content_type_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE environment_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE notification_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE audit_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE auth_tokens_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE field_type_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE search_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE search_filter_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE i18n_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE job_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE template_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE uploaded_asset_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE view_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE "user_id_seq" CASCADE');
        $this->addSql('DROP TABLE revision');
        $this->addSql('DROP TABLE environment_revision');
        $this->addSql('DROP TABLE content_type');
        $this->addSql('DROP TABLE environment');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE audit');
        $this->addSql('DROP TABLE auth_tokens');
        $this->addSql('DROP TABLE field_type');
        $this->addSql('DROP TABLE search');
        $this->addSql('DROP TABLE search_filter');
        $this->addSql('DROP TABLE i18n');
        $this->addSql('DROP TABLE job');
        $this->addSql('DROP TABLE template');
        $this->addSql('DROP TABLE environment_template');
        $this->addSql('DROP TABLE uploaded_asset');
        $this->addSql('DROP TABLE view');
        $this->addSql('DROP TABLE "user"');
    }
}
