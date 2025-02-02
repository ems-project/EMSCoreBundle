<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220615093145 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE log_message ADD ouuid VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE log_message ALTER context TYPE JSON USING context::json');
        $this->addSql('ALTER TABLE log_message ALTER context DROP DEFAULT');
        $this->addSql('ALTER TABLE log_message ALTER extra TYPE JSON USING extra::json');
        $this->addSql('ALTER TABLE log_message ALTER extra DROP DEFAULT');
        $this->addSql('COMMENT ON COLUMN log_message.context IS NULL');
        $this->addSql('COMMENT ON COLUMN log_message.extra IS NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE log_message DROP ouuid');
        $this->addSql('ALTER TABLE log_message ALTER context TYPE TEXT USING context::text');
        $this->addSql('ALTER TABLE log_message ALTER context DROP DEFAULT');
        $this->addSql('ALTER TABLE log_message ALTER extra TYPE TEXT USING extra::text');
        $this->addSql('ALTER TABLE log_message ALTER extra DROP DEFAULT');
        $this->addSql('COMMENT ON COLUMN log_message.context IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN log_message.extra IS \'(DC2Type:array)\'');
    }
}
