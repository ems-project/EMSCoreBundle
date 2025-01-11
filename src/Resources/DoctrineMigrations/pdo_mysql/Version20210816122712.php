<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20210816122712 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE task (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', title VARCHAR(255) NOT NULL, status VARCHAR(25) NOT NULL, deadline DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', assignee LONGTEXT NOT NULL, description LONGTEXT NOT NULL, logs JSON NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE revision ADD task_current_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD task_planned_ids JSON DEFAULT NULL, ADD task_approved_ids JSON DEFAULT NULL, ADD owner LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE revision ADD CONSTRAINT FK_6D6315CCE99931F3 FOREIGN KEY (task_current_id) REFERENCES task (id)');
        $this->addSql('CREATE INDEX IDX_6D6315CCE99931F3 ON revision (task_current_id)');
        $this->addSql('ALTER TABLE content_type ADD owner_role VARCHAR(100) DEFAULT NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE revision DROP FOREIGN KEY FK_6D6315CCE99931F3');
        $this->addSql('DROP TABLE task');
        $this->addSql('ALTER TABLE content_type DROP owner_role');
        $this->addSql('DROP INDEX IDX_6D6315CCE99931F3 ON revision');
        $this->addSql('ALTER TABLE revision DROP task_current_id, DROP task_planned_ids, DROP task_approved_ids, DROP owner');
    }
}
