<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200713150916 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE form_submission (id INT AUTO_INCREMENT NOT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL, locale VARCHAR(2) NOT NULL, data JSON NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE form_submission_file (id INT AUTO_INCREMENT NOT NULL, form_submission_id INT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, file LONGBLOB NOT NULL, filename VARCHAR(255) NOT NULL, form_field VARCHAR(255) NOT NULL, mime_type VARCHAR(1024) NOT NULL, size BIGINT NOT NULL, INDEX IDX_AEFF00A6422B0E0C (form_submission_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE form_submission_file ADD CONSTRAINT FK_AEFF00A6422B0E0C FOREIGN KEY (form_submission_id) REFERENCES form_submission (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE form_submission_file DROP FOREIGN KEY FK_AEFF00A6422B0E0C');
        $this->addSql('DROP TABLE form_submission');
        $this->addSql('DROP TABLE form_submission_file');
    }
}
