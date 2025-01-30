<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use EMS\CoreBundle\Entity\Dashboard;

final class Version20230214132743 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE dashboard ADD definition VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX definition_uniq ON dashboard (definition)');

        $this->addSql('UPDATE dashboard SET definition = :def WHERE quick_search = true', ['def' => Dashboard::DEFINITION_QUICK_SEARCH]);
        $this->addSql('UPDATE dashboard SET definition = :def WHERE landing_page = true', ['def' => Dashboard::DEFINITION_LANDING_PAGE]);

        $this->addSql('ALTER TABLE dashboard DROP landing_page, DROP quick_search');

        $this->addSql('ALTER TABLE user DROP allowed_to_configure_wysiwyg, DROP wysiwyg_options');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE dashboard ADD landing_page TINYINT(1) DEFAULT 0 NOT NULL, ADD quick_search TINYINT(1) DEFAULT 0 NOT NULL');

        $this->addSql('UPDATE dashboard SET quick_search = true WHERE definition = :def', ['def' => Dashboard::DEFINITION_QUICK_SEARCH]);
        $this->addSql('UPDATE dashboard SET landing_page = true WHERE definition = :def', ['def' => Dashboard::DEFINITION_LANDING_PAGE]);

        $this->addSql('DROP INDEX definition_uniq ON dashboard');
        $this->addSql('ALTER TABLE dashboard DROP definition');

        $this->addSql('ALTER TABLE `user` ADD allowed_to_configure_wysiwyg TINYINT(1) DEFAULT NULL, ADD wysiwyg_options LONGTEXT DEFAULT NULL');
    }
}
