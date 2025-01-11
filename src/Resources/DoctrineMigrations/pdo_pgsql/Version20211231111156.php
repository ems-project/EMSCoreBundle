<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20211231111156 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('CREATE TABLE log_message (id UUID NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, message TEXT NOT NULL, context TEXT NOT NULL, level SMALLINT NOT NULL, level_name VARCHAR(50) NOT NULL, channel VARCHAR(255) NOT NULL, extra TEXT NOT NULL, formatted TEXT NOT NULL, username VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN log_message.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN log_message.context IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN log_message.extra IS \'(DC2Type:array)\'');
        $this->addSql('INSERT INTO schedule (id, created, modified, name, cron, command, next_run, order_key) VALUES (\'e0e77d35-f8b5-4bbe-a804-e513c404ab5a\', \'2022-01-01 12:24:57\', \'2022-01-01 12:24:57\', \'Clear logs\', \'0 2 * * 0\', \'ems:logs:clear\', \'2022-01-01 12:24:57\', 100)');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('DROP TABLE log_message');
        $this->addSql('DELETE FROM schedule WHERE id = \'e0e77d35-f8b5-4bbe-a804-e513c404ab5a\'');
    }
}
