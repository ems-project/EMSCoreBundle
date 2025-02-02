<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20210107173336 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );
        $this->addSql('ALTER TABLE environment ADD update_referrers BOOLEAN DEFAULT \'false\' NOT NULL');

        $this->addSql('DROP SEQUENCE single_type_index_id_seq CASCADE');
        $this->addSql('DROP TABLE single_type_index');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );
        $this->addSql('ALTER TABLE environment DROP update_referrers');

        $this->addSql('CREATE SEQUENCE single_type_index_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE single_type_index (id BIGINT NOT NULL, content_type_id BIGINT DEFAULT NULL, environment_id INT DEFAULT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_FEAD46B31A445520 ON single_type_index (content_type_id)');
        $this->addSql('CREATE INDEX IDX_FEAD46B3903E3A94 ON single_type_index (environment_id)');
        $this->addSql('ALTER TABLE single_type_index ADD CONSTRAINT fk_fead46b31a445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE single_type_index ADD CONSTRAINT fk_fead46b3903e3a94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
