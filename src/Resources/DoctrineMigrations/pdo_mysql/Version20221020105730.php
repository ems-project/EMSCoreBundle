<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20221020105730 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE task CHANGE created created DATETIME NOT NULL');
        $this->addSql('ALTER TABLE task CHANGE created created DATETIME NOT NULL');

        $this->addSql('ALTER TABLE user ADD user_options LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\'');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE task CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE task CHANGE modified modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');

        $this->addSql('ALTER TABLE `user` DROP user_options');
    }
}
