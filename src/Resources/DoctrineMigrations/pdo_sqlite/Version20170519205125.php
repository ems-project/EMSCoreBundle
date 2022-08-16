<?php

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170519205125 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TABLE revision (id INTEGER NOT NULL, content_type_id BIGINT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, auto_save_at DATETIME DEFAULT NULL, deleted BOOLEAN NOT NULL, version INTEGER DEFAULT 1 NOT NULL, ouuid VARCHAR(255) DEFAULT NULL, start_time DATETIME NOT NULL, end_time DATETIME DEFAULT NULL, draft BOOLEAN NOT NULL, lock_by VARCHAR(255) DEFAULT NULL, auto_save_by VARCHAR(255) DEFAULT NULL, lock_until DATETIME DEFAULT NULL, raw_data CLOB DEFAULT NULL, auto_save CLOB DEFAULT NULL, circles CLOB DEFAULT NULL, labelField VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6D6315CC1A445520 ON revision (content_type_id)');
        $this->addSql('CREATE UNIQUE INDEX tuple_index ON revision (end_time, ouuid)');
        $this->addSql('CREATE TABLE environment_revision (revision_id INTEGER NOT NULL, environment_id INTEGER NOT NULL, PRIMARY KEY(revision_id, environment_id))');
        $this->addSql('CREATE INDEX IDX_895F7B701DFA7C8F ON environment_revision (revision_id)');
        $this->addSql('CREATE INDEX IDX_895F7B70903E3A94 ON environment_revision (environment_id)');
        $this->addSql('CREATE TABLE content_type (id INTEGER NOT NULL, field_types_id INTEGER DEFAULT NULL, environment_id INTEGER DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(100) NOT NULL, pluralName VARCHAR(100) NOT NULL, singularName VARCHAR(100) NOT NULL, icon VARCHAR(100) DEFAULT NULL, description CLOB DEFAULT NULL, indexTwig CLOB DEFAULT NULL, extra CLOB DEFAULT NULL, lockBy VARCHAR(100) DEFAULT NULL, lockUntil DATETIME DEFAULT NULL, circles_field VARCHAR(100) DEFAULT NULL, deleted BOOLEAN NOT NULL, have_pipelines BOOLEAN DEFAULT NULL, ask_for_ouuid BOOLEAN NOT NULL, dirty BOOLEAN NOT NULL, color VARCHAR(50) DEFAULT NULL, labelField VARCHAR(100) DEFAULT NULL, color_field VARCHAR(100) DEFAULT NULL, parentField VARCHAR(100) DEFAULT NULL, userField VARCHAR(100) DEFAULT NULL, dateField VARCHAR(100) DEFAULT NULL, startDateField VARCHAR(100) DEFAULT NULL, endDateField VARCHAR(100) DEFAULT NULL, locationField VARCHAR(100) DEFAULT NULL, referer_field_name VARCHAR(100) DEFAULT NULL, category_field VARCHAR(100) DEFAULT NULL, ouuidField VARCHAR(100) DEFAULT NULL, imageField VARCHAR(100) DEFAULT NULL, videoField VARCHAR(100) DEFAULT NULL, email_field VARCHAR(100) DEFAULT NULL, asset_field VARCHAR(100) DEFAULT NULL, order_field VARCHAR(100) DEFAULT NULL, sort_by VARCHAR(100) DEFAULT NULL, create_role VARCHAR(100) DEFAULT NULL, edit_role VARCHAR(100) DEFAULT NULL, view_role VARCHAR(100) DEFAULT NULL, orderKey INTEGER NOT NULL, rootContentType BOOLEAN NOT NULL, edit_twig_with_wysiwyg BOOLEAN NOT NULL, active BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_41BCBAEC588AB49A ON content_type (field_types_id)');
        $this->addSql('CREATE INDEX IDX_41BCBAEC903E3A94 ON content_type (environment_id)');
        $this->addSql('CREATE TABLE environment (id INTEGER NOT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL, alias VARCHAR(255) NOT NULL, color VARCHAR(50) DEFAULT NULL, baseUrl VARCHAR(1024) DEFAULT NULL, managed BOOLEAN NOT NULL, circles CLOB DEFAULT NULL, in_default_search BOOLEAN DEFAULT NULL, extra CLOB DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4626DE225E237E06 ON environment (name)');
        $this->addSql('CREATE TABLE notification (id INTEGER NOT NULL, template_id INTEGER DEFAULT NULL, revision_id INTEGER DEFAULT NULL, environment_id INTEGER DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, username VARCHAR(100) NOT NULL, status VARCHAR(20) NOT NULL, sent_timestamp DATETIME NOT NULL, response_text CLOB DEFAULT NULL, response_timestamp DATETIME DEFAULT NULL, response_by VARCHAR(100) DEFAULT NULL, emailed DATETIME DEFAULT NULL, response_emailed DATETIME DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BF5476CA5DA0FB8 ON notification (template_id)');
        $this->addSql('CREATE INDEX IDX_BF5476CA1DFA7C8F ON notification (revision_id)');
        $this->addSql('CREATE INDEX IDX_BF5476CA903E3A94 ON notification (environment_id)');
        $this->addSql('CREATE TABLE audit (id INTEGER NOT NULL, date DATETIME NOT NULL, "action" VARCHAR(255) NOT NULL, raw_data CLOB DEFAULT NULL, username VARCHAR(255) NOT NULL, environment VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE auth_tokens (id INTEGER NOT NULL, user_id INTEGER DEFAULT NULL, value VARCHAR(255) NOT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8AF9B66CA76ED395 ON auth_tokens (user_id)');
        $this->addSql('CREATE UNIQUE INDEX auth_tokens_value_unique ON auth_tokens (value)');
        $this->addSql('CREATE TABLE field_type (id INTEGER NOT NULL, content_type_id BIGINT DEFAULT NULL, parent_id INTEGER DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, type VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, deleted BOOLEAN NOT NULL, description CLOB DEFAULT NULL, options CLOB DEFAULT NULL, orderKey INTEGER NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9F123E931A445520 ON field_type (content_type_id)');
        $this->addSql('CREATE INDEX IDX_9F123E93727ACA70 ON field_type (parent_id)');
        $this->addSql('CREATE TABLE search (id INTEGER NOT NULL, username VARCHAR(100) NOT NULL, environments CLOB NOT NULL, contentTypes CLOB NOT NULL, name VARCHAR(100) NOT NULL, sort_by VARCHAR(100) DEFAULT NULL, sort_order VARCHAR(100) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE search_filter (id INTEGER NOT NULL, search_id BIGINT DEFAULT NULL, pattern VARCHAR(200) DEFAULT NULL, field VARCHAR(100) DEFAULT NULL, boolean_clause VARCHAR(20) DEFAULT NULL, operator VARCHAR(50) NOT NULL, boost NUMERIC(10, 2) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A6263002650760A9 ON search_filter (search_id)');
        $this->addSql('CREATE TABLE i18n (id INTEGER NOT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, identifier VARCHAR(200) NOT NULL, content CLOB NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FF561896772E836A ON i18n (identifier)');
        $this->addSql('CREATE TABLE job (id INTEGER NOT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, status VARCHAR(2048) NOT NULL, output CLOB DEFAULT NULL, done BOOLEAN NOT NULL, started BOOLEAN NOT NULL, progress INTEGER NOT NULL, arguments CLOB DEFAULT NULL, username VARCHAR(255) DEFAULT NULL, service VARCHAR(255) DEFAULT NULL, command VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE template (id INTEGER NOT NULL, content_type_id BIGINT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL, icon VARCHAR(255) DEFAULT NULL, body CLOB DEFAULT NULL, header CLOB DEFAULT NULL, edit_with_wysiwyg BOOLEAN NOT NULL, render_option VARCHAR(255) NOT NULL, orderKey INTEGER NOT NULL, accumulate_in_one_file BOOLEAN NOT NULL, preview BOOLEAN NOT NULL, mime_type VARCHAR(255) DEFAULT NULL, filename CLOB DEFAULT NULL, extension VARCHAR(255) DEFAULT NULL, active BOOLEAN NOT NULL, role VARCHAR(255) NOT NULL, role_to VARCHAR(255) NOT NULL, role_cc VARCHAR(255) NOT NULL, circles_to CLOB DEFAULT NULL, response_template CLOB DEFAULT NULL, email_content_type VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_97601F831A445520 ON template (content_type_id)');
        $this->addSql('CREATE TABLE environment_template (template_id INTEGER NOT NULL, environment_id INTEGER NOT NULL, PRIMARY KEY(template_id, environment_id))');
        $this->addSql('CREATE INDEX IDX_735C713F5DA0FB8 ON environment_template (template_id)');
        $this->addSql('CREATE INDEX IDX_735C713F903E3A94 ON environment_template (environment_id)');
        $this->addSql('CREATE TABLE uploaded_asset (id INTEGER NOT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, status VARCHAR(64) DEFAULT NULL, sha1 VARCHAR(40) NOT NULL, name VARCHAR(1024) NOT NULL, type VARCHAR(1024) NOT NULL, username VARCHAR(255) NOT NULL, available BOOLEAN NOT NULL, size INTEGER NOT NULL, uploaded INTEGER NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE "view" (id INTEGER NOT NULL, content_type_id BIGINT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, icon VARCHAR(255) DEFAULT NULL, options CLOB DEFAULT NULL, orderKey INTEGER NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_FEFDAB8E1A445520 ON "view" (content_type_id)');
        $this->addSql('CREATE TABLE "user" (id INTEGER NOT NULL, username VARCHAR(180) NOT NULL, username_canonical VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, email_canonical VARCHAR(180) NOT NULL, enabled BOOLEAN NOT NULL, salt VARCHAR(255) DEFAULT NULL, password VARCHAR(255) NOT NULL, last_login DATETIME DEFAULT NULL, confirmation_token VARCHAR(180) DEFAULT NULL, password_requested_at DATETIME DEFAULT NULL, roles CLOB NOT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, circles CLOB DEFAULT NULL, display_name VARCHAR(255) DEFAULT NULL, allowed_to_configure_wysiwyg BOOLEAN DEFAULT NULL, wysiwyg_profile CLOB DEFAULT NULL, wysiwyg_options CLOB DEFAULT NULL, layout_boxed BOOLEAN NOT NULL, sidebar_mini BOOLEAN NOT NULL, sidebar_collapse BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64992FC23A8 ON "user" (username_canonical)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649A0D96FBF ON "user" (email_canonical)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649C05FB297 ON "user" (confirmation_token)');
        $my_json_var = '[{"locale":"en","text":"<div class=\"box\"><div class=\"box-header with-border\"><h3 class=\"box-title\">Based on Symfony 3, Bootstrap 3 and AdminLTE</h3> </div> <div class=\"box-body\" style=\"display: block;\"><p>Visit <a href=\"http://www.elasticms.eu/\">elasticms.eu</a></p></div></div>"}]';
        $this->addSql('INSERT INTO `i18n` (`id`, `created`, `modified`, `identifier`, `content`) VALUES (NULL, \'2017-05-19 21:04:48\', \'2017-05-19 21:21:27\', \'ems.documentation.body\', \''.$my_json_var.'\')');
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

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
        $this->addSql('DROP TABLE "view"');
        $this->addSql('DROP TABLE "user"');
    }
}
