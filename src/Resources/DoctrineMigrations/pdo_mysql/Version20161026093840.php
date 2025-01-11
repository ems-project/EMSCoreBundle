<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20161026093840 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE user ADD allowed_to_configure_wysiwyg TINYINT(1) NOT NULL, ADD wysiwyg_profile TINYTEXT DEFAULT NULL, ADD wysiwyg_options LONGTEXT DEFAULT NULL');
        $this->addSql('update `user` set wysiwyg_profile = \'standard\'');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE `user` DROP allowed_to_configure_wysiwyg, DROP wysiwyg_profile, DROP wysiwyg_options');
    }
}
