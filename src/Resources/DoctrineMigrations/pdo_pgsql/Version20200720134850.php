<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20200720134850 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE form_submission ADD process_try_counter INT DEFAULT NULL');
        $this->addSql('ALTER TABLE form_submission ADD process_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN form_submission.data IS \'(DC2Type:json_array)\'');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE form_submission DROP process_try_counter');
        $this->addSql('ALTER TABLE form_submission DROP process_id');
        $this->addSql('COMMENT ON COLUMN form_submission.data IS NULL');
    }
}
