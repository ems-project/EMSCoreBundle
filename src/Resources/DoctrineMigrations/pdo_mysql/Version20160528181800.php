<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20160528181800 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql("UPDATE `field_type` SET `type` = 'AppBundle\\\\Form\\\\DataField\\\\TextStringFieldType' WHERE `type`='AppBundle\\\\Form\\\\DataField\\\\TextFieldType'");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql("UPDATE `field_type` SET `type` = 'AppBundle\\\\Form\\\\DataField\\\\TextFieldType' WHERE `type`='AppBundle\\\\Form\\\\DataField\\\\TextStringFieldType'");
    }
}
