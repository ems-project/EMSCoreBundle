<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210210204834 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE wysiwyg_styles_set ADD format_tags VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE wysiwyg_styles_set ADD content_css VARCHAR(2048) DEFAULT NULL');
        $this->addSql('ALTER TABLE wysiwyg_styles_set ADD assets JSON DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN wysiwyg_styles_set.assets IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE wysiwyg_styles_set ADD save_dir VARCHAR(2048) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE wysiwyg_styles_set DROP format_tags');
        $this->addSql('ALTER TABLE wysiwyg_styles_set DROP content_css');
        $this->addSql('ALTER TABLE wysiwyg_styles_set DROP assets');
        $this->addSql('ALTER TABLE wysiwyg_styles_set DROP save_dir');
    }
}
