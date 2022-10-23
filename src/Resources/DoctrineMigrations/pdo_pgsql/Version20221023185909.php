<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20221023185909 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE content_type DROP userfield');
        $this->addSql('ALTER TABLE content_type DROP datefield');
        $this->addSql('ALTER TABLE content_type DROP startdatefield');
        $this->addSql('ALTER TABLE content_type DROP enddatefield');
        $this->addSql('ALTER TABLE content_type DROP locationfield');
        $this->addSql('ALTER TABLE content_type DROP ouuidfield');
        $this->addSql('ALTER TABLE content_type DROP videofield');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE content_type ADD userfield VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD datefield VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD startdatefield VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD enddatefield VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD locationfield VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD ouuidfield VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD videofield VARCHAR(100) DEFAULT NULL');
    }
}
