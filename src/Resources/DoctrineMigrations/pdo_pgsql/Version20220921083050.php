<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20220921083050 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('COMMENT ON COLUMN analyzer.options IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN cache_asset_extractor.data IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN content_type.version_tags IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN environment.circles IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN field_type.options IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN filter.options IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN form_submission.data IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN i18n.content IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN "user".circles IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN revision.auto_save IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN revision.raw_data IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN search.environments IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN search.contentTypes IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN search_field_option.contentTypes IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN search_field_option.operators IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN template.circles_to IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN view.options IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN wysiwyg_styles_set.assets IS \'(DC2Type:json)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('COMMENT ON COLUMN analyzer.options IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN cache_asset_extractor.data IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN content_type.version_tags IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN environment.circles IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN field_type.options IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN filter.options IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN form_submission.data IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN i18n.content IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN "user".circles IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN revision.auto_save IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN revision.raw_data IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN search.environments IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN search.contentTypes IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN search_field_option.contentTypes IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN search_field_option.operators IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN template.circles_to IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN view.options IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN wysiwyg_styles_set.assets IS \'(DC2Type:json_array)\'');
    }
}
