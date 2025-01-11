<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20180711204857 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE content_type ADD auto_publish BOOLEAN DEFAULT \'false\' NOT NULL');
        $this->addSql('COMMENT ON COLUMN cache_asset_extractor.data IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE job ALTER status TYPE TEXT');
        $this->addSql('ALTER TABLE job ALTER status DROP DEFAULT');
        $this->addSql('ALTER TABLE job ALTER status DROP NOT NULL');
        $this->addSql('ALTER TABLE job ALTER status TYPE TEXT');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('COMMENT ON COLUMN cache_asset_extractor.data IS NULL');
        $this->addSql('ALTER TABLE job ALTER status TYPE VARCHAR(2048)');
        $this->addSql('ALTER TABLE job ALTER status DROP DEFAULT');
        $this->addSql('ALTER TABLE job ALTER status SET NOT NULL');
        $this->addSql('ALTER TABLE content_type DROP auto_publish');
    }
}
