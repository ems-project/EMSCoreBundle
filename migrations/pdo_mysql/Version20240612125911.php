<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240612125911 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Release revision add type and rename column';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE release_revision DROP FOREIGN KEY FK_3663CEADFBFE9068');
        $this->addSql('DROP INDEX IDX_3663CEADFBFE9068 ON release_revision');
        $this->addSql('ALTER TABLE release_revision CHANGE revision_id revision_id INT NOT NULL, CHANGE revision_before_publish_id rollback_revision_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE release_revision ADD CONSTRAINT FK_3663CEAD5B35C530 FOREIGN KEY (rollback_revision_id) REFERENCES revision (id)');
        $this->addSql('CREATE INDEX IDX_3663CEAD5B35C530 ON release_revision (rollback_revision_id)');

        $this->addSql('ALTER TABLE release_revision ADD type LONGTEXT DEFAULT NULL');
        $this->addSql('UPDATE release_revision SET type = \'publish\' WHERE revision_id IS NOT NULL');
        $this->addSql('UPDATE release_revision SET type = \'unpublish\' WHERE revision_id IS NULL');
        $this->addSql('ALTER TABLE release_revision CHANGE type type LONGTEXT NOT NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE release_revision DROP FOREIGN KEY FK_3663CEAD5B35C530');
        $this->addSql('DROP INDEX IDX_3663CEAD5B35C530 ON release_revision');
        $this->addSql('ALTER TABLE release_revision DROP type, CHANGE revision_id revision_id INT DEFAULT NULL, CHANGE rollback_revision_id revision_before_publish_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE release_revision ADD CONSTRAINT FK_3663CEADFBFE9068 FOREIGN KEY (revision_before_publish_id) REFERENCES revision (id)');
        $this->addSql('CREATE INDEX IDX_3663CEADFBFE9068 ON release_revision (revision_before_publish_id)');
    }
}
