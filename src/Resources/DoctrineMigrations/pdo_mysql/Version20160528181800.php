<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160528181800 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("UPDATE `field_type` SET `type` = 'AppBundle\\\\Form\\\\DataField\\\\TextStringFieldType' WHERE `type`='AppBundle\\\\Form\\\\DataField\\\\TextFieldType'");
    }

    public function down(Schema $schema)
    {
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("UPDATE `field_type` SET `type` = 'AppBundle\\\\Form\\\\DataField\\\\TextFieldType' WHERE `type`='AppBundle\\\\Form\\\\DataField\\\\TextStringFieldType'");
    }
}
