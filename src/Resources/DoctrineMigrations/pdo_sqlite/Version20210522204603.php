<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20210522204603 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_C164B8C35E237E06 ON query_search (name)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP INDEX UNIQ_C164B8C35E237E06');
    }
}
