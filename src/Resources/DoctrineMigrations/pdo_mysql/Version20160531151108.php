<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20160531151108 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE user ADD circles LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE job ADD command VARCHAR(255) DEFAULT NULL, CHANGE service service VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE environment_revision DROP FOREIGN KEY FK_895F7B701DFA7C8F');
        $this->addSql('ALTER TABLE environment_revision DROP FOREIGN KEY FK_895F7B70903E3A94');
        $this->addSql('ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B701DFA7C8F FOREIGN KEY (revision_id) REFERENCES revision (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B70903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE environment_revision DROP FOREIGN KEY FK_895F7B701DFA7C8F');
        $this->addSql('ALTER TABLE environment_revision DROP FOREIGN KEY FK_895F7B70903E3A94');
        $this->addSql('ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B701DFA7C8F FOREIGN KEY (revision_id) REFERENCES revision (id)');
        $this->addSql('ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B70903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id)');
        $this->addSql('ALTER TABLE job DROP command, CHANGE service service VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE `user` DROP circles');
    }
}
