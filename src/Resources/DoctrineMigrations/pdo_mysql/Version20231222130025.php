<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231222130025 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'upgrade mysql use json types';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE analyzer CHANGE options options JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE cache_asset_extractor CHANGE data data JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE channel CHANGE options options JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE content_type CHANGE version_tags version_tags JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE version_options version_options JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE roles roles JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE fields fields JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE version_fields version_fields JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE settings settings JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE dashboard CHANGE options options JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE environment CHANGE circles circles JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE snapshot snapshot TINYINT(1) DEFAULT false NOT NULL, CHANGE update_referrers update_referrers TINYINT(1) DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE field_type CHANGE options options JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE filter CHANGE options options JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE form_submission CHANGE data data JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE i18n CHANGE content content JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE log_message CHANGE context context JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE extra extra JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE query_search CHANGE options options JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE revision CHANGE ouuid ouuid VARCHAR(255) DEFAULT NULL, CHANGE raw_data raw_data JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE auto_save auto_save JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE archived archived TINYINT(1) DEFAULT false NOT NULL, CHANGE task_planned_ids task_planned_ids JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE task_approved_ids task_approved_ids JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE search CHANGE environments environments JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE contentTypes contenttypes JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE default_search default_search TINYINT(1) DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE search_field_option CHANGE contentTypes contenttypes JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE operators operators JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE store_data CHANGE data data JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE task CHANGE logs logs JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE template CHANGE circles_to circles_to JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE spreadsheet spreadsheet TINYINT(1) DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE circles circles JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE user_options user_options JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE view CHANGE options options JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE wysiwyg_styles_set CHANGE assets assets JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE analyzer CHANGE options options JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE cache_asset_extractor CHANGE data data JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE channel CHANGE options options JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE content_type CHANGE version_tags version_tags JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE version_options version_options JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE version_fields version_fields JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE roles roles JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE fields fields JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE settings settings JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE dashboard CHANGE options options JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE environment CHANGE snapshot snapshot TINYINT(1) DEFAULT 0 NOT NULL, CHANGE circles circles JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE update_referrers update_referrers TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE field_type CHANGE options options JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE filter CHANGE options options JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE form_submission CHANGE data data JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE i18n CHANGE content content JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE log_message CHANGE context context JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE extra extra JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE query_search CHANGE options options JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE revision CHANGE archived archived TINYINT(1) DEFAULT 0 NOT NULL, CHANGE ouuid ouuid VARCHAR(255) DEFAULT NULL COLLATE `utf8mb3_bin`, CHANGE raw_data raw_data JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE auto_save auto_save JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE task_planned_ids task_planned_ids JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE task_approved_ids task_approved_ids JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE search CHANGE environments environments JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE contenttypes contentTypes JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE default_search default_search TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE search_field_option CHANGE contenttypes contentTypes JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE operators operators JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE store_data CHANGE data data JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE task CHANGE logs logs JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE template CHANGE circles_to circles_to JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE spreadsheet spreadsheet TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE `user` CHANGE circles circles JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE user_options user_options JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE view CHANGE options options JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE wysiwyg_styles_set CHANGE assets assets JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
    }
}
