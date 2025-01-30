<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20160630134327 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE notification DROP INDEX UNIQ_BF5476CA5DA0FB8, ADD INDEX IDX_BF5476CA5DA0FB8 (template_id)');
        $this->addSql('ALTER TABLE notification DROP INDEX UNIQ_BF5476CA1DFA7C8F, ADD INDEX IDX_BF5476CA1DFA7C8F (revision_id)');
        $this->addSql('ALTER TABLE notification DROP INDEX UNIQ_BF5476CA903E3A94, ADD INDEX IDX_BF5476CA903E3A94 (environment_id)');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE notification DROP INDEX IDX_BF5476CA5DA0FB8, ADD UNIQUE INDEX UNIQ_BF5476CA5DA0FB8 (template_id)');
        $this->addSql('ALTER TABLE notification DROP INDEX IDX_BF5476CA1DFA7C8F, ADD UNIQUE INDEX UNIQ_BF5476CA1DFA7C8F (revision_id)');
        $this->addSql('ALTER TABLE notification DROP INDEX IDX_BF5476CA903E3A94, ADD UNIQUE INDEX UNIQ_BF5476CA903E3A94 (environment_id)');
    }
}
