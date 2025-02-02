<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20200911073341 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE form_submission ADD label VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE form_submission ADD deadline_date VARCHAR(255) DEFAULT NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );
    }
}
