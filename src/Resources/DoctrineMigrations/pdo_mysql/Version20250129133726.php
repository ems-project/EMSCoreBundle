<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250129133726 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Upgrade schema doctrine orm 3.3';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE analyzer CHANGE options options JSON NOT NULL');
        $this->addSql('ALTER TABLE cache_asset_extractor CHANGE data data JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE channel CHANGE id id CHAR(36) NOT NULL, CHANGE options options JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type CHANGE version_tags version_tags JSON DEFAULT NULL, CHANGE version_options version_options JSON DEFAULT NULL, CHANGE roles roles JSON DEFAULT NULL, CHANGE fields fields JSON DEFAULT NULL, CHANGE version_fields version_fields JSON DEFAULT NULL, CHANGE settings settings JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE dashboard CHANGE id id CHAR(36) NOT NULL, CHANGE options options JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE environment CHANGE circles circles JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE field_type CHANGE options options JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE filter CHANGE options options JSON NOT NULL');
        $this->addSql('ALTER TABLE form CHANGE id id CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE form_submission CHANGE id id CHAR(36) NOT NULL, CHANGE data data JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE form_submission_file CHANGE id id CHAR(36) NOT NULL, CHANGE form_submission_id form_submission_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE form_verification CHANGE id id CHAR(36) NOT NULL, CHANGE created created DATETIME NOT NULL, CHANGE expiration_date expiration_date DATETIME NOT NULL');
        $this->addSql('ALTER TABLE i18n CHANGE content content JSON NOT NULL');
        $this->addSql('ALTER TABLE log_message CHANGE id id CHAR(36) NOT NULL, CHANGE context context JSON NOT NULL, CHANGE extra extra JSON NOT NULL');
        $this->addSql('ALTER TABLE query_search CHANGE id id CHAR(36) NOT NULL, CHANGE options options JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE environment_query_search CHANGE query_search_id query_search_id CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE revision CHANGE raw_data raw_data JSON DEFAULT NULL, CHANGE auto_save auto_save JSON DEFAULT NULL, CHANGE circles circles LONGTEXT DEFAULT NULL, CHANGE version_uuid version_uuid CHAR(36) DEFAULT NULL, CHANGE task_current_id task_current_id CHAR(36) DEFAULT NULL, CHANGE task_planned_ids task_planned_ids JSON DEFAULT NULL, CHANGE task_approved_ids task_approved_ids JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE schedule CHANGE id id CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE search CHANGE environments environments JSON NOT NULL, CHANGE contenttypes contenttypes JSON NOT NULL');
        $this->addSql('ALTER TABLE search_field_option CHANGE contenttypes contenttypes JSON NOT NULL, CHANGE operators operators JSON NOT NULL');
        $this->addSql('ALTER TABLE store_data CHANGE id id CHAR(36) NOT NULL, CHANGE data data JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE task CHANGE id id CHAR(36) NOT NULL, CHANGE deadline deadline DATETIME DEFAULT NULL, CHANGE logs logs JSON NOT NULL');
        $this->addSql('ALTER TABLE template CHANGE circles_to circles_to JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE uploaded_asset CHANGE head_in head_in JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL, CHANGE circles circles JSON DEFAULT NULL, CHANGE user_options user_options JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE view CHANGE options options JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE wysiwyg_styles_set CHANGE assets assets JSON DEFAULT NULL');
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
        $this->addSql('ALTER TABLE channel CHANGE options options JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE content_type CHANGE version_tags version_tags JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE version_options version_options JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE version_fields version_fields JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE roles roles JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE fields fields JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE settings settings JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE dashboard CHANGE options options JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE environment CHANGE circles circles JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE environment_query_search CHANGE query_search_id query_search_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE field_type CHANGE options options JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE filter CHANGE options options JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE form CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE form_submission CHANGE data data JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE form_submission_file CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE form_submission_id form_submission_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE form_verification CHANGE created created DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE expiration_date expiration_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE i18n CHANGE content content JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE log_message CHANGE context context JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE extra extra JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE query_search CHANGE options options JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE revision CHANGE raw_data raw_data JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE auto_save auto_save JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE circles circles LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:simple_array)\', CHANGE version_uuid version_uuid CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE task_planned_ids task_planned_ids JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE task_approved_ids task_approved_ids JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE task_current_id task_current_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE schedule CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE search CHANGE environments environments JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE contenttypes contenttypes JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE search_field_option CHANGE contenttypes contenttypes JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE operators operators JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE store_data CHANGE data data JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE task CHANGE deadline deadline DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE logs logs JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE template CHANGE circles_to circles_to JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE uploaded_asset CHANGE head_in head_in JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE `user` CHANGE circles circles JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE roles roles JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE user_options user_options JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE view CHANGE options options JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE wysiwyg_styles_set CHANGE assets assets JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
    }
}
