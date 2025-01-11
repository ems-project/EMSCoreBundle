<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230206193853 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('CREATE TABLE form (id UUID NOT NULL, field_types_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, label VARCHAR(255) NOT NULL, order_key INT NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5288FD4F5E237E06 ON form (name)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5288FD4F588AB49A ON form (field_types_id)');
        $this->addSql('COMMENT ON COLUMN form.id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE form ADD CONSTRAINT FK_5288FD4F588AB49A FOREIGN KEY (field_types_id) REFERENCES field_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE form DROP CONSTRAINT FK_5288FD4F588AB49A');
        $this->addSql('DROP TABLE form');
    }
}
