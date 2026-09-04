<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903201601 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Delete WYSIWYG profile set the profile on null in table user';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT fk_8d93d649a282f7ea');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_8D93D649A282F7EA FOREIGN KEY (wysiwyg_profile_id) REFERENCES wysiwyg_profile (id) ON DELETE SET NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D649A282F7EA');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT fk_8d93d649a282f7ea FOREIGN KEY (wysiwyg_profile_id) REFERENCES wysiwyg_profile (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
