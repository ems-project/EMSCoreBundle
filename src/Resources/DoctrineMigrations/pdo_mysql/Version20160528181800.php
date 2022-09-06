<?php

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160528181800 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("UPDATE `field_type` SET `type` = 'AppBundle\\\\Form\\\\DataField\\\\TextStringFieldType' WHERE `type`='AppBundle\\\\Form\\\\DataField\\\\TextFieldType'");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("UPDATE `field_type` SET `type` = 'AppBundle\\\\Form\\\\DataField\\\\TextFieldType' WHERE `type`='AppBundle\\\\Form\\\\DataField\\\\TextStringFieldType'");
    }
}
