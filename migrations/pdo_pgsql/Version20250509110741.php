<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250509110741 extends AbstractMigration
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
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );
        
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision DROP CONSTRAINT FK_895F7B701DFA7C8F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B701DFA7C8F FOREIGN KEY (revision_id) REFERENCES revision (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );
        
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision DROP CONSTRAINT fk_895f7b701dfa7c8f
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ADD CONSTRAINT fk_895f7b701dfa7c8f FOREIGN KEY (revision_id) REFERENCES revision (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }
}
