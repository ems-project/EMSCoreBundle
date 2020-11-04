<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181028141002 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE search_field_option ADD contentTypes JSON NOT NULL');
        $this->addSql('ALTER TABLE search_field_option ADD operators JSON NOT NULL');
        $this->addSql('COMMENT ON COLUMN search_field_option.contentTypes IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN search_field_option.operators IS \'(DC2Type:json_array)\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE search_field_option DROP contentTypes');
        $this->addSql('ALTER TABLE search_field_option DROP operators');
    }
}
