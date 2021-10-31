<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210920102630 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE release_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE release_revision_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TYPE release_status_enum AS ENUM (\'wip\', \'ready\', \'applied\', \'scheduled\', \'canceled\', \'rollbacked\')');
        $this->addSql('CREATE TABLE release (id INT NOT NULL, environment_source_id INT DEFAULT NULL, environment_target_id INT DEFAULT NULL, execution_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status release_status_enum NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_9E47031D570FDFA8 ON release (environment_source_id)');
        $this->addSql('CREATE INDEX IDX_9E47031DD7BDC8AF ON release (environment_target_id)');
        $this->addSql('COMMENT ON COLUMN release.status IS \'(DC2Type:release_status_enum)\'');
        $this->addSql('CREATE TABLE release_revision (id INT NOT NULL, release_id INT DEFAULT NULL, revision_id INT DEFAULT NULL, content_type_id BIGINT DEFAULT NULL, revision_ouuid VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3663CEADB12A727D ON release_revision (release_id)');
        $this->addSql('CREATE INDEX IDX_3663CEAD1DFA7C8F ON release_revision (revision_id)');
        $this->addSql('CREATE INDEX IDX_3663CEAD1A445520 ON release_revision (content_type_id)');
        $this->addSql('ALTER TABLE release ADD CONSTRAINT FK_9E47031D570FDFA8 FOREIGN KEY (environment_source_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE release ADD CONSTRAINT FK_9E47031DD7BDC8AF FOREIGN KEY (environment_target_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE release_revision ADD CONSTRAINT FK_3663CEADB12A727D FOREIGN KEY (release_id) REFERENCES release (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE release_revision ADD CONSTRAINT FK_3663CEAD1DFA7C8F FOREIGN KEY (revision_id) REFERENCES revision (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE release_revision ADD CONSTRAINT FK_3663CEAD1A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE release_revision DROP CONSTRAINT FK_3663CEADB12A727D');
        $this->addSql('DROP SEQUENCE release_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE release_revision_id_seq CASCADE');
        $this->addSql('DROP TYPE release_status_enum');
        $this->addSql('DROP TABLE release');
        $this->addSql('DROP TABLE release_revision');
    }
}
