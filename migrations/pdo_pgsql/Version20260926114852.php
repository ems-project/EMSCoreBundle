<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926114852 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add translations fields';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE content_type ADD plural_name_translations JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD singular_name_translations JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE dashboard ADD label_translations JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE environment ADD label_translations JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE template ADD label_translations JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE view ADD label_translations JSON DEFAULT NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE content_type DROP plural_name_translations');
        $this->addSql('ALTER TABLE content_type DROP singular_name_translations');
        $this->addSql('ALTER TABLE dashboard DROP label_translations');
        $this->addSql('ALTER TABLE environment DROP label_translations');
        $this->addSql('ALTER TABLE template DROP label_translations');
        $this->addSql('ALTER TABLE view DROP label_translations');
    }
}
