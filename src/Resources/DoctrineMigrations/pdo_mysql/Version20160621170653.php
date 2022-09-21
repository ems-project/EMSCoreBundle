<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20160621170653 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE search ADD sort_by VARCHAR(100) NOT NULL, ADD sort_order VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE environment_revision DROP FOREIGN KEY FK_895F7B70903E3A94');
        $this->addSql('ALTER TABLE environment_revision DROP FOREIGN KEY FK_895F7B701DFA7C8F');
        $this->addSql('ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B70903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B701DFA7C8F FOREIGN KEY (revision_id) REFERENCES revision (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE environment_revision DROP FOREIGN KEY FK_895F7B701DFA7C8F');
        $this->addSql('ALTER TABLE environment_revision DROP FOREIGN KEY FK_895F7B70903E3A94');
        $this->addSql('ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B701DFA7C8F FOREIGN KEY (revision_id) REFERENCES revision (id)');
        $this->addSql('ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B70903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id)');
        $this->addSql('ALTER TABLE search DROP sort_by, DROP sort_order');
    }
}
