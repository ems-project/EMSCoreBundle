<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200723065630 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('UPDATE form_submission SET process_try_counter = 0 WHERE process_try_counter IS NULL');
        $this->addSql('ALTER TABLE form_submission ALTER process_try_counter SET DEFAULT 0');
        $this->addSql('ALTER TABLE form_submission ALTER process_try_counter SET NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE form_submission ALTER process_try_counter DROP DEFAULT');
        $this->addSql('ALTER TABLE form_submission ALTER process_try_counter DROP NOT NULL');
    }
}
