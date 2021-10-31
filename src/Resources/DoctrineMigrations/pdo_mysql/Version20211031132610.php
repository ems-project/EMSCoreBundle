<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211031132610 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE `release` (id INT AUTO_INCREMENT NOT NULL, environment_source_id INT DEFAULT NULL, environment_target_id INT DEFAULT NULL, execution_date DATETIME DEFAULT NULL, status ENUM(\'wip\', \'ready\', \'applied\', \'scheduled\', \'canceled\', \'rollbacked\') NOT NULL COMMENT \'(DC2Type:release_status_enum)\', name VARCHAR(255) NOT NULL, INDEX IDX_9E47031D570FDFA8 (environment_source_id), INDEX IDX_9E47031DD7BDC8AF (environment_target_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE release_revision (id INT AUTO_INCREMENT NOT NULL, release_id INT DEFAULT NULL, revision_id INT DEFAULT NULL, content_type_id BIGINT DEFAULT NULL, revision_ouuid VARCHAR(255) NOT NULL, INDEX IDX_3663CEADB12A727D (release_id), INDEX IDX_3663CEAD1DFA7C8F (revision_id), INDEX IDX_3663CEAD1A445520 (content_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE `release` ADD CONSTRAINT FK_9E47031D570FDFA8 FOREIGN KEY (environment_source_id) REFERENCES environment (id)');
        $this->addSql('ALTER TABLE `release` ADD CONSTRAINT FK_9E47031DD7BDC8AF FOREIGN KEY (environment_target_id) REFERENCES environment (id)');
        $this->addSql('ALTER TABLE release_revision ADD CONSTRAINT FK_3663CEADB12A727D FOREIGN KEY (release_id) REFERENCES `release` (id)');
        $this->addSql('ALTER TABLE release_revision ADD CONSTRAINT FK_3663CEAD1DFA7C8F FOREIGN KEY (revision_id) REFERENCES revision (id)');
        $this->addSql('ALTER TABLE release_revision ADD CONSTRAINT FK_3663CEAD1A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id)');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE release_revision DROP FOREIGN KEY FK_3663CEADB12A727D');
        $this->addSql('DROP TABLE `release`');
        $this->addSql('DROP TABLE release_revision');
    }
}
