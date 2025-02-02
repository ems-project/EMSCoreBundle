<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20160609174255 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('DROP TABLE data_value');
        $this->addSql('ALTER TABLE data_field ADD raw_data LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json_array)\'');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE data_value (id INT AUTO_INCREMENT NOT NULL, data_field_id INT NOT NULL, integer_value BIGINT DEFAULT NULL, float_value DOUBLE PRECISION DEFAULT NULL, date_value DATETIME DEFAULT NULL, text_value LONGTEXT DEFAULT NULL COLLATE utf8_unicode_ci, sha1 VARCHAR(20) DEFAULT NULL COLLATE utf8_unicode_ci, index_key INT NOT NULL, INDEX IDX_53C894AB8EE9CE6C (data_field_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE data_value ADD CONSTRAINT FK_53C894AB8EE9CE6C FOREIGN KEY (data_field_id) REFERENCES data_field (id)');
        $this->addSql('ALTER TABLE data_field DROP raw_data');
    }
}
