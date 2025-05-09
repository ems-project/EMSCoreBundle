<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250509111207 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Fix environment revision cascade delete, when deleting revision';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );
        
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision DROP FOREIGN KEY FK_895F7B701DFA7C8F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B701DFA7C8F FOREIGN KEY (revision_id) REFERENCES revision (id) ON DELETE CASCADE
        SQL);
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );
        
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision DROP FOREIGN KEY FK_895F7B701DFA7C8F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B701DFA7C8F FOREIGN KEY (revision_id) REFERENCES revision (id)
        SQL);
    }
}
