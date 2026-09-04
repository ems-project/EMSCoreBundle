<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260904055720 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Delete WYSIWYG profile set the profile on null in table user';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE user DROP FOREIGN KEY `FK_8D93D649A282F7EA`');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649A282F7EA FOREIGN KEY (wysiwyg_profile_id) REFERENCES wysiwyg_profile (id) ON DELETE SET NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649A282F7EA');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT `FK_8D93D649A282F7EA` FOREIGN KEY (wysiwyg_profile_id) REFERENCES wysiwyg_profile (id)');
    }
}
