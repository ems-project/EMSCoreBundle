<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use EMS\CoreBundle\Resources\DoctrineMigrations\Scripts\ScriptContentTypeFields;

class Version20221023185909 extends AbstractMigration
{
    use ScriptContentTypeFields;

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE content_type DROP userfield');
        $this->addSql('ALTER TABLE content_type DROP datefield');
        $this->addSql('ALTER TABLE content_type DROP startdatefield');
        $this->addSql('ALTER TABLE content_type DROP enddatefield');
        $this->addSql('ALTER TABLE content_type DROP locationfield');
        $this->addSql('ALTER TABLE content_type DROP ouuidfield');
        $this->addSql('ALTER TABLE content_type DROP videofield');
        $this->addSql('ALTER TABLE content_type DROP email_field');
        $this->addSql('ALTER TABLE content_type DROP imagefield');
        $this->addSql('ALTER TABLE content_type DROP translationfield');
        $this->addSql('ALTER TABLE content_type DROP localefield');

        $this->addSql('ALTER TABLE content_type ADD fields JSON DEFAULT NULL');

        $this->scriptEncodeFields($this);
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE content_type ADD userfield VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD datefield VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD startdatefield VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD enddatefield VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD locationfield VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD ouuidfield VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD videofield VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD email_field VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD imagefield VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD translationfield VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD localefield VARCHAR(255) DEFAULT NULL');

        $this->scriptDecodeFields($this);
    }
}
