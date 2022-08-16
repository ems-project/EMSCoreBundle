<?php

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160630101646 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE environment_template DROP FOREIGN KEY FK_735C713F903E3A94');
        $this->addSql('ALTER TABLE environment_template ADD CONSTRAINT FK_735C713F903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id)');
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE environment_template DROP FOREIGN KEY FK_735C713F903E3A94');
        $this->addSql('ALTER TABLE environment_template ADD CONSTRAINT FK_735C713F903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) ON DELETE CASCADE');
    }
}
