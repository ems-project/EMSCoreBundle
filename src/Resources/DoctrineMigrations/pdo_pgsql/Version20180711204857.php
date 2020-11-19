<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20180711204857 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE content_type ADD auto_publish BOOLEAN DEFAULT \'false\' NOT NULL');
        $this->addSql('COMMENT ON COLUMN cache_asset_extractor.data IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE job ALTER status TYPE TEXT');
        $this->addSql('ALTER TABLE job ALTER status DROP DEFAULT');
        $this->addSql('ALTER TABLE job ALTER status DROP NOT NULL');
        $this->addSql('ALTER TABLE job ALTER status TYPE TEXT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('COMMENT ON COLUMN cache_asset_extractor.data IS NULL');
        $this->addSql('ALTER TABLE job ALTER status TYPE VARCHAR(2048)');
        $this->addSql('ALTER TABLE job ALTER status DROP DEFAULT');
        $this->addSql('ALTER TABLE job ALTER status SET NOT NULL');
        $this->addSql('ALTER TABLE content_type DROP auto_publish');
    }
}
