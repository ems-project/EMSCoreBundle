<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use EMS\CoreBundle\Resources\DoctrineMigrations\Scripts\ScriptContentTypeFields;

final class Version20221023190143 extends AbstractMigration
{
    use ScriptContentTypeFields;

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE content_type DROP userField, DROP dateField, DROP startDateField, DROP endDateField, DROP locationField, DROP ouuidField, DROP videoField, DROP email_field, DROP imageField, DROP translationField, DROP localeField');
        $this->addSql('ALTER TABLE task CHANGE modified modified DATETIME NOT NULL');

        $this->addSql('ALTER TABLE content_type ADD fields LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\'');

        $this->scriptEncodeFields($this);
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE content_type ADD userField VARCHAR(100) DEFAULT NULL, ADD dateField VARCHAR(100) DEFAULT NULL, ADD startDateField VARCHAR(100) DEFAULT NULL, ADD endDateField VARCHAR(100) DEFAULT NULL, ADD locationField VARCHAR(100) DEFAULT NULL, ADD ouuidField VARCHAR(100) DEFAULT NULL, ADD videoField VARCHAR(100) DEFAULT NULL, ADD email_field VARCHAR(100) DEFAULT NULL, ADD imageField VARCHAR(100) DEFAULT NULL, ADD translationField VARCHAR(100) DEFAULT NULL, ADD localeField VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE task CHANGE modified modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');

        $this->scriptDecodeFields($this);
    }
}
