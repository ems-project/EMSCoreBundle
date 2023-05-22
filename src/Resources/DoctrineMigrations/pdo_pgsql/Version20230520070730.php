<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230520070730 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a nullable tag field to the schedule entity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE schedule ADD tag VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE schedule DROP tag');
    }
}
