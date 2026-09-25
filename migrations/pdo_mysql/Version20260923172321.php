<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260923172321 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cleaning of old legacy search entities + Advanced search dashboard.';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE search DROP FOREIGN KEY `FK_B4F0DBA71A445520`');
        $this->addSql('ALTER TABLE search_filter DROP FOREIGN KEY `FK_A6263002650760A9`');
        $this->addSql('DROP TABLE aggregate_option');
        $this->addSql('DROP TABLE search');
        $this->addSql('DROP TABLE search_field_option');
        $this->addSql('DROP TABLE search_filter');
        $this->addSql('DROP TABLE sort_option');
        $this->addSql('ALTER TABLE content_type ADD query_search_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD CONSTRAINT FK_41BCBAEC936B6C19 FOREIGN KEY (query_search_id) REFERENCES query_search (id)');
        $this->addSql('CREATE INDEX IDX_41BCBAEC936B6C19 ON content_type (query_search_id)');
        $this->addSql('ALTER TABLE query_search ADD default_query_search TINYINT DEFAULT 0 NOT NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE aggregate_option (id INT AUTO_INCREMENT NOT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, config LONGTEXT CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, template LONGTEXT CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, orderKey INT NOT NULL, icon TINYTEXT CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE search (id BIGINT AUTO_INCREMENT NOT NULL, username VARCHAR(100) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, name VARCHAR(100) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, sort_by VARCHAR(100) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, sort_order VARCHAR(100) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, environments JSON NOT NULL, contenttypes JSON NOT NULL, default_search TINYINT DEFAULT 0 NOT NULL, content_type_id BIGINT DEFAULT NULL, minimum_should_match INT DEFAULT 1 NOT NULL, UNIQUE INDEX UNIQ_B4F0DBA71A445520 (content_type_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE search_field_option (id INT AUTO_INCREMENT NOT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, field TINYTEXT CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, orderKey INT NOT NULL, icon TINYTEXT CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, contenttypes JSON NOT NULL, operators JSON NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE search_filter (id BIGINT AUTO_INCREMENT NOT NULL, search_id BIGINT DEFAULT NULL, pattern VARCHAR(200) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, field VARCHAR(100) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, boolean_clause VARCHAR(20) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, operator VARCHAR(50) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, boost NUMERIC(10, 2) DEFAULT NULL, INDEX IDX_A6263002650760A9 (search_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE sort_option (id INT AUTO_INCREMENT NOT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, field TINYTEXT CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, orderKey INT NOT NULL, inverted TINYINT NOT NULL, icon TINYTEXT CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE search ADD CONSTRAINT `FK_B4F0DBA71A445520` FOREIGN KEY (content_type_id) REFERENCES content_type (id)');
        $this->addSql('ALTER TABLE search_filter ADD CONSTRAINT `FK_A6263002650760A9` FOREIGN KEY (search_id) REFERENCES search (id)');
        $this->addSql('ALTER TABLE content_type DROP FOREIGN KEY FK_41BCBAEC936B6C19');
        $this->addSql('DROP INDEX IDX_41BCBAEC936B6C19 ON content_type');
        $this->addSql('ALTER TABLE content_type DROP query_search_id');
        $this->addSql('ALTER TABLE query_search DROP default_query_search');
    }
}
