<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use EMS\CoreBundle\Entity\WysiwygProfile;

final class Version20250125202317 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Add an editor field to the WYSIWYG profile entity';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );
        $this->addSql(\sprintf('ALTER TABLE wysiwyg_profile ADD editor VARCHAR(255) NOT NULL DEFAULT \'%s\'', WysiwygProfile::CKEDITOR4));
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );
        $this->addSql('ALTER TABLE wysiwyg_profile DROP editor');
    }
}