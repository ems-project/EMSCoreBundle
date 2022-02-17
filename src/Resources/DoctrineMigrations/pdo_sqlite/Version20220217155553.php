<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220217155553 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, wysiwyg_profile_id, username, username_canonical, email, email_canonical, enabled, salt, password, last_login, confirmation_token, password_requested_at, roles, created, modified, circles, display_name, allowed_to_configure_wysiwyg, wysiwyg_options, layout_boxed, email_notification, sidebar_mini, sidebar_collapse FROM user');
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, wysiwyg_profile_id INTEGER DEFAULT NULL, username VARCHAR(180) NOT NULL COLLATE BINARY, username_canonical VARCHAR(180) NOT NULL COLLATE BINARY, email VARCHAR(180) NOT NULL COLLATE BINARY, email_canonical VARCHAR(180) NOT NULL COLLATE BINARY, enabled BOOLEAN NOT NULL, salt VARCHAR(255) DEFAULT NULL COLLATE BINARY, password VARCHAR(255) NOT NULL COLLATE BINARY, last_login DATETIME DEFAULT NULL, confirmation_token VARCHAR(180) DEFAULT NULL COLLATE BINARY, password_requested_at DATETIME DEFAULT NULL, roles CLOB NOT NULL COLLATE BINARY --(DC2Type:array)
        , created DATETIME NOT NULL, modified DATETIME NOT NULL, circles CLOB DEFAULT NULL COLLATE BINARY --(DC2Type:json_array)
        , display_name VARCHAR(255) DEFAULT NULL COLLATE BINARY, allowed_to_configure_wysiwyg BOOLEAN DEFAULT NULL, wysiwyg_options CLOB DEFAULT NULL COLLATE BINARY, layout_boxed BOOLEAN NOT NULL, email_notification BOOLEAN NOT NULL, sidebar_mini BOOLEAN NOT NULL, sidebar_collapse BOOLEAN NOT NULL, locale VARCHAR(255) NOT NULL, locale_preferred VARCHAR(255) DEFAULT NULL, CONSTRAINT FK_8D93D649A282F7EA FOREIGN KEY (wysiwyg_profile_id) REFERENCES wysiwyg_profile (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO user (id, wysiwyg_profile_id, username, username_canonical, email, email_canonical, enabled, salt, password, last_login, confirmation_token, password_requested_at, roles, created, modified, circles, display_name, allowed_to_configure_wysiwyg, wysiwyg_options, layout_boxed, email_notification, sidebar_mini, sidebar_collapse) SELECT id, wysiwyg_profile_id, username, username_canonical, email, email_canonical, enabled, salt, password, last_login, confirmation_token, password_requested_at, roles, created, modified, circles, display_name, allowed_to_configure_wysiwyg, wysiwyg_options, layout_boxed, email_notification, sidebar_mini, sidebar_collapse FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

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
    }
}
