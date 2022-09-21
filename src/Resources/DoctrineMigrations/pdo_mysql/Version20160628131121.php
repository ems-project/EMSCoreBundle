<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20160628131121 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE environment_template DROP FOREIGN KEY FK_735C713F903E3A94');
        $this->addSql('ALTER TABLE environment_template ADD CONSTRAINT FK_735C713F903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE environment_template DROP FOREIGN KEY FK_735C713F903E3A94');
        $this->addSql('ALTER TABLE environment_template ADD CONSTRAINT FK_735C713F903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id)');
    }
}
