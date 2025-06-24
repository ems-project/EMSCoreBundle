<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Ramsey\Uuid\Uuid;

final class Version20250426083639 extends AbstractMigration
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
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );


        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ADD id UUID DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision DROP CONSTRAINT FK_895F7B701DFA7C8F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision DROP CONSTRAINT FK_895F7B70903E3A94
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision DROP CONSTRAINT environment_revision_pkey
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ADD created TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ADD created_by VARCHAR(180) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ADD deleted TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ADD deleted_by VARCHAR(180) DEFAULT NULL
        SQL);

        $this->addSql(<<<'SQL'
            UPDATE environment_revision SET created = NOW(), created_by = 'DOCTRINE_UPGRADE_SCRIPT'
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ALTER created SET NOT NULL
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ALTER created_by SET NOT NULL
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
            ALTER TABLE environment_revision ALTER created SET NOT NULL
        SQL);
        
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B701DFA7C8F FOREIGN KEY (revision_id) REFERENCES revision (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B70903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ADD PRIMARY KEY (id)
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
            DELETE FROM environment_revision WHERE deleted IS NOT NULL
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision DROP CONSTRAINT fk_895f7b701dfa7c8f
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision DROP CONSTRAINT fk_895f7b70903e3a94
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision DROP created
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision DROP deleted
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision DROP created_by
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision DROP deleted_by
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision DROP id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ADD CONSTRAINT fk_895f7b701dfa7c8f FOREIGN KEY (revision_id) REFERENCES revision (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ADD CONSTRAINT fk_895f7b70903e3a94 FOREIGN KEY (environment_id) REFERENCES environment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE environment_revision ADD PRIMARY KEY (revision_id, environment_id)
        SQL);
    }
}
