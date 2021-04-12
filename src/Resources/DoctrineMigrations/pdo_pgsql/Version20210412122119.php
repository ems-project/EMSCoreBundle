<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210412122119 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE uploaded_asset ADD hidden BOOLEAN DEFAULT \'false\' NOT NULL');
        $this->addSql('ALTER TABLE uploaded_asset ADD head_last TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE uploaded_asset ADD head_in TEXT DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN uploaded_asset.head_in IS \'(DC2Type:array)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE uploaded_asset DROP hidden');
        $this->addSql('ALTER TABLE uploaded_asset DROP head_last');
        $this->addSql('ALTER TABLE uploaded_asset DROP head_in');
    }
}
