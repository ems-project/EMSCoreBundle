<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250129125411 extends AbstractMigration
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
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE form_submission ALTER COLUMN data TYPE JSON USING data::json');

        $this->addSql('COMMENT ON COLUMN analyzer.options IS \'\'');
        $this->addSql('COMMENT ON COLUMN cache_asset_extractor.data IS \'\'');
        $this->addSql('COMMENT ON COLUMN channel.id IS \'\'');
        $this->addSql('COMMENT ON COLUMN content_type.version_tags IS \'\'');
        $this->addSql('COMMENT ON COLUMN dashboard.id IS \'\'');
        $this->addSql('COMMENT ON COLUMN environment.circles IS \'\'');
        $this->addSql('COMMENT ON COLUMN field_type.options IS \'\'');
        $this->addSql('COMMENT ON COLUMN filter.options IS \'\'');
        $this->addSql('COMMENT ON COLUMN form.id IS \'\'');
        $this->addSql('COMMENT ON COLUMN form_submission.id IS \'\'');
        $this->addSql('COMMENT ON COLUMN form_submission.data IS \'\'');
        $this->addSql('COMMENT ON COLUMN form_submission_file.id IS \'\'');
        $this->addSql('COMMENT ON COLUMN form_submission_file.form_submission_id IS \'\'');
        $this->addSql('COMMENT ON COLUMN form_verification.id IS \'\'');
        $this->addSql('COMMENT ON COLUMN form_verification.created IS \'\'');
        $this->addSql('COMMENT ON COLUMN form_verification.expiration_date IS \'\'');
        $this->addSql('COMMENT ON COLUMN i18n.content IS \'\'');
        $this->addSql('COMMENT ON COLUMN log_message.id IS \'\'');
        $this->addSql('COMMENT ON COLUMN query_search.id IS \'\'');
        $this->addSql('COMMENT ON COLUMN environment_query_search.query_search_id IS \'\'');
        $this->addSql('COMMENT ON COLUMN revision.raw_data IS \'\'');
        $this->addSql('COMMENT ON COLUMN revision.auto_save IS \'\'');
        $this->addSql('COMMENT ON COLUMN revision.circles IS \'\'');
        $this->addSql('COMMENT ON COLUMN revision.version_uuid IS \'\'');
        $this->addSql('COMMENT ON COLUMN revision.task_current_id IS \'\'');
        $this->addSql('COMMENT ON COLUMN schedule.id IS \'\'');
        $this->addSql('COMMENT ON COLUMN search.environments IS \'\'');
        $this->addSql('COMMENT ON COLUMN search.contenttypes IS \'\'');
        $this->addSql('COMMENT ON COLUMN search_field_option.contenttypes IS \'\'');
        $this->addSql('COMMENT ON COLUMN search_field_option.operators IS \'\'');
        $this->addSql('COMMENT ON COLUMN store_data.id IS \'\'');
        $this->addSql('COMMENT ON COLUMN task.id IS \'\'');
        $this->addSql('COMMENT ON COLUMN task.deadline IS \'\'');
        $this->addSql('COMMENT ON COLUMN template.circles_to IS \'\'');
        $this->addSql('COMMENT ON COLUMN "user".circles IS \'\'');
        $this->addSql('COMMENT ON COLUMN view.options IS \'\'');
        $this->addSql('COMMENT ON COLUMN wysiwyg_styles_set.assets IS \'\'');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE form_submission ALTER data TYPE TEXT');

        $this->addSql('COMMENT ON COLUMN task.deadline IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN task.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN cache_asset_extractor.data IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN "user".circles IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN search_field_option.contenttypes IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN search_field_option.operators IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN schedule.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN filter.options IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN dashboard.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN store_data.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN environment.circles IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN wysiwyg_styles_set.assets IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN form_submission_file.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN form_submission_file.form_submission_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN i18n.content IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN revision.raw_data IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN revision.auto_save IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN revision.circles IS \'(DC2Type:simple_array)\'');
        $this->addSql('COMMENT ON COLUMN revision.version_uuid IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN revision.task_current_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN template.circles_to IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN form_verification.created IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN form_verification.expiration_date IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN form_verification.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN view.options IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN form.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN content_type.version_tags IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN search.environments IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN search.contenttypes IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN analyzer.options IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN log_message.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN field_type.options IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN channel.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN form_submission.data IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN form_submission.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN query_search.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN environment_query_search.query_search_id IS \'(DC2Type:uuid)\'');
    }
}
