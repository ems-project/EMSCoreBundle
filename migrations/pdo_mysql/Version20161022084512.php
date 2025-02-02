<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20161022084512 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE search_filter CHANGE boolean_clause boolean_clause VARCHAR(20) DEFAULT NULL');
        $this->addSql('UPDATE search_filter SET boolean_clause = "must" where boolean_clause = "0"');
        $this->addSql('UPDATE search_filter SET boolean_clause = "must_not" where boolean_clause = "1"');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('UPDATE search_filter SET boolean_clause = "0" where boolean_clause = "must"');
        $this->addSql('UPDATE search_filter SET boolean_clause = "1" where boolean_clause = "must_not"');
        $this->addSql('ALTER TABLE search_filter CHANGE boolean_clause boolean_clause TINYINT(1) DEFAULT NULL');
    }
}
