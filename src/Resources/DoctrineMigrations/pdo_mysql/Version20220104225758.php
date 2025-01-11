<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220104225758 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE `release` RENAME TO release_entity');
        $this->addSql('ALTER TABLE release_entity DROP FOREIGN KEY FK_9E47031D570FDFA8');
        $this->addSql('ALTER TABLE release_entity DROP FOREIGN KEY FK_9E47031DD7BDC8AF');
        $this->addSql('DROP INDEX idx_9e47031d570fdfa8 ON release_entity');
        $this->addSql('CREATE INDEX IDX_C0CA3E85570FDFA8 ON release_entity (environment_source_id)');
        $this->addSql('DROP INDEX idx_9e47031dd7bdc8af ON release_entity');
        $this->addSql('CREATE INDEX IDX_C0CA3E85D7BDC8AF ON release_entity (environment_target_id)');
        $this->addSql('ALTER TABLE release_entity ADD CONSTRAINT FK_9E47031D570FDFA8 FOREIGN KEY (environment_source_id) REFERENCES environment (id)');
        $this->addSql('ALTER TABLE release_entity ADD CONSTRAINT FK_9E47031DD7BDC8AF FOREIGN KEY (environment_target_id) REFERENCES environment (id)');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE release_entity RENAME TO `release`');
        $this->addSql('ALTER TABLE release_entity DROP FOREIGN KEY FK_C0CA3E85570FDFA8');
        $this->addSql('ALTER TABLE release_entity DROP FOREIGN KEY FK_C0CA3E85D7BDC8AF');
        $this->addSql('DROP INDEX idx_c0ca3e85570fdfa8 ON release_entity');
        $this->addSql('CREATE INDEX IDX_9E47031D570FDFA8 ON release_entity (environment_source_id)');
        $this->addSql('DROP INDEX idx_c0ca3e85d7bdc8af ON release_entity');
        $this->addSql('CREATE INDEX IDX_9E47031DD7BDC8AF ON release_entity (environment_target_id)');
        $this->addSql('ALTER TABLE release_entity ADD CONSTRAINT FK_C0CA3E85570FDFA8 FOREIGN KEY (environment_source_id) REFERENCES environment (id)');
        $this->addSql('ALTER TABLE release_entity ADD CONSTRAINT FK_C0CA3E85D7BDC8AF FOREIGN KEY (environment_target_id) REFERENCES environment (id)');
    }
}
