<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220104223904 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE release RENAME TO release_entity');
        $this->addSql('ALTER SEQUENCE release_id_seq RENAME TO release_entity_id_seq');
        $this->addSql('ALTER INDEX idx_9e47031d570fdfa8 RENAME TO IDX_C0CA3E85570FDFA8');
        $this->addSql('ALTER INDEX idx_9e47031dd7bdc8af RENAME TO IDX_C0CA3E85D7BDC8AF');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE release_entity RENAME TO release');
        $this->addSql('ALTER SEQUENCE release_entity_id_seq RENAME TO release_id_seq');
        $this->addSql('ALTER INDEX idx_c0ca3e85d7bdc8af RENAME TO idx_9e47031dd7bdc8af');
        $this->addSql('ALTER INDEX idx_c0ca3e85570fdfa8 RENAME TO idx_9e47031d570fdfa8');
    }
}
