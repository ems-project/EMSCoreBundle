<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210723112301 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE task (id UUID NOT NULL, title VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN task.id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE revision ADD task_current_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE revision ADD task_planned_ids JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE revision ADD task_approved_ids JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE task');
        $this->addSql('ALTER TABLE revision DROP task_current_id');
        $this->addSql('ALTER TABLE revision DROP task_planned_ids');
        $this->addSql('ALTER TABLE revision DROP task_approved_ids');
    }
}
