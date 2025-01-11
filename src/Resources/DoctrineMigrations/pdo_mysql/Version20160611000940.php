<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20160611000940 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE data_field DROP FOREIGN KEY FK_154A89C7727ACA70');
        $this->addSql('ALTER TABLE revision DROP FOREIGN KEY FK_6D6315CC8EE9CE6C');
        $this->addSql('DROP TABLE data_field');
        $this->addSql('DROP INDEX UNIQ_6D6315CC8EE9CE6C ON revision');
        $this->addSql('ALTER TABLE revision DROP data_field_id');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE data_field (id INT AUTO_INCREMENT NOT NULL, field_type_id INT DEFAULT NULL, parent_id INT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, revision_id INT DEFAULT NULL, orderKey INT NOT NULL, raw_data LONGTEXT DEFAULT NULL COLLATE utf8_unicode_ci COMMENT \'(DC2Type:json_array)\', INDEX IDX_154A89C72B68A933 (field_type_id), INDEX IDX_154A89C7727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE data_field ADD CONSTRAINT FK_154A89C72B68A933 FOREIGN KEY (field_type_id) REFERENCES field_type (id)');
        $this->addSql('ALTER TABLE data_field ADD CONSTRAINT FK_154A89C7727ACA70 FOREIGN KEY (parent_id) REFERENCES data_field (id)');
        $this->addSql('ALTER TABLE revision ADD data_field_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE revision ADD CONSTRAINT FK_6D6315CC8EE9CE6C FOREIGN KEY (data_field_id) REFERENCES data_field (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6D6315CC8EE9CE6C ON revision (data_field_id)');
    }
}
