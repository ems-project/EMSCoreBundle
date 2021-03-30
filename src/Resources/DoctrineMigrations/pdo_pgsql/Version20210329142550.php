<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210329142550 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE release_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TYPE releasestatusenum AS ENUM (\'wip\', \'ready\', \'apply\', \'scheduled\', \'canceled\', \'rollback\')');
        $this->addSql('CREATE TABLE release (id INT NOT NULL, execution_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status releasestatusenum NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN release.status IS \'(DC2Type:releasestatusenum)\'');
        $this->addSql('CREATE TABLE environment_release (release_id INT NOT NULL, environment_id INT NOT NULL, PRIMARY KEY(release_id, environment_id))');
        $this->addSql('CREATE INDEX IDX_ACE6B491B12A727D ON environment_release (release_id)');
        $this->addSql('CREATE INDEX IDX_ACE6B491903E3A94 ON environment_release (environment_id)');
        $this->addSql('CREATE TABLE revision_release (release_id INT NOT NULL, revision_id INT NOT NULL, PRIMARY KEY(release_id, revision_id))');
        $this->addSql('CREATE INDEX IDX_1D22CD79B12A727D ON revision_release (release_id)');
        $this->addSql('CREATE INDEX IDX_1D22CD791DFA7C8F ON revision_release (revision_id)');
        $this->addSql('ALTER TABLE environment_release ADD CONSTRAINT FK_ACE6B491B12A727D FOREIGN KEY (release_id) REFERENCES release (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE environment_release ADD CONSTRAINT FK_ACE6B491903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE revision_release ADD CONSTRAINT FK_1D22CD79B12A727D FOREIGN KEY (release_id) REFERENCES release (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE revision_release ADD CONSTRAINT FK_1D22CD791DFA7C8F FOREIGN KEY (revision_id) REFERENCES revision (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP SEQUENCE release_id_seq CASCADE');
        $this->addSql('ALTER TABLE environment_release DROP CONSTRAINT FK_ACE6B491B12A727D');
        $this->addSql('ALTER TABLE revision_release DROP CONSTRAINT FK_1D22CD79B12A727D');
        $this->addSql('DROP TABLE release');
        $this->addSql('DROP TYPE releasestatusenum');
        $this->addSql('DROP TABLE environment_release');
        $this->addSql('DROP TABLE revision_release');
    }
}
