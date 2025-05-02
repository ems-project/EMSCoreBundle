<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Ramsey\Uuid\Uuid;

final class Version20250430054415 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Converts the many-to-many relation between Environment and Revision by an associative Entity';
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
            ALTER TABLE environment_revision DROP FOREIGN KEY FK_895F7B70903E3A94
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision DROP FOREIGN KEY FK_895F7B701DFA7C8F
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX `primary` ON environment_revision
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ADD created DATETIME DEFAULT NULL, ADD created_by VARCHAR(180) DEFAULT NULL, ADD deleted DATETIME DEFAULT NULL, ADD deleted_by VARCHAR(180) DEFAULT NULL, ADD id CHAR(36) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE environment_revision SET created = NOW(), created_by = 'DOCTRINE_UPGRADE_SCRIPT'
        SQL);
        $rows = $this->connection->fetchAllAssociative('SELECT environment_id, revision_id FROM environment_revision');
        foreach ($rows as $row) {
            $this->addSql(<<<'SQL'
                UPDATE environment_revision SET id = :id WHERE environment_id = :environment_id AND revision_id = :revision_id
            SQL, [
                    'id' => Uuid::uuid4()->toString(),
                    'environment_id' => $row['environment_id'],
                    'revision_id' => $row['revision_id'],
                ]
            );
        }
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B70903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B701DFA7C8F FOREIGN KEY (revision_id) REFERENCES revision (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ADD PRIMARY KEY (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision CHANGE created created DATETIME NOT NULL, CHANGE created_by created_by VARCHAR(180) NOT NULL
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
            ALTER TABLE environment_revision DROP FOREIGN KEY FK_895F7B70903E3A94
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX `PRIMARY` ON environment_revision
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision DROP created, DROP created_by, DROP deleted, DROP deleted_by, DROP id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B701DFA7C8F FOREIGN KEY (revision_id) REFERENCES revision (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B70903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ADD PRIMARY KEY (revision_id, environment_id)
        SQL);
    }
}
