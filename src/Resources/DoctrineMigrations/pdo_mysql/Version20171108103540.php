<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20171108103540 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE environment ADD order_key INT DEFAULT NULL');
        $this->addSql('ALTER TABLE analyzer ADD order_key INT DEFAULT NULL');
        $this->addSql('ALTER TABLE filter ADD order_key INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE analyzer DROP order_key');
        $this->addSql('ALTER TABLE environment DROP order_key');
        $this->addSql('ALTER TABLE filter DROP order_key');
    }
}
