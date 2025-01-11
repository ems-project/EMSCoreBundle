<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20160528181644 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(255) NOT NULL, username_canonical VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, email_canonical VARCHAR(255) NOT NULL, enabled TINYINT(1) NOT NULL, salt VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, last_login DATETIME DEFAULT NULL, locked TINYINT(1) NOT NULL, expired TINYINT(1) NOT NULL, expires_at DATETIME DEFAULT NULL, confirmation_token VARCHAR(255) DEFAULT NULL, password_requested_at DATETIME DEFAULT NULL, roles LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', credentials_expired TINYINT(1) NOT NULL, credentials_expire_at DATETIME DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, UNIQUE INDEX UNIQ_8D93D64992FC23A8 (username_canonical), UNIQUE INDEX UNIQ_8D93D649A0D96FBF (email_canonical), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE content_type (id BIGINT AUTO_INCREMENT NOT NULL, field_types_id INT DEFAULT NULL, environment_id INT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(100) NOT NULL, pluralName VARCHAR(100) NOT NULL, icon VARCHAR(100) DEFAULT NULL, description LONGTEXT DEFAULT NULL, indexTwig LONGTEXT DEFAULT NULL, lockBy VARCHAR(100) DEFAULT NULL, lockUntil DATETIME DEFAULT NULL, circles LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:simple_array)\', deleted TINYINT(1) NOT NULL, ask_for_ouuid TINYINT(1) NOT NULL, dirty TINYINT(1) NOT NULL, color VARCHAR(50) DEFAULT NULL, labelField VARCHAR(100) DEFAULT NULL, color_field VARCHAR(100) DEFAULT NULL, parentField VARCHAR(100) DEFAULT NULL, userField VARCHAR(100) DEFAULT NULL, dateField VARCHAR(100) DEFAULT NULL, startDateField VARCHAR(100) DEFAULT NULL, endDateField VARCHAR(100) DEFAULT NULL, locationField VARCHAR(100) DEFAULT NULL, category_field VARCHAR(100) DEFAULT NULL, ouuidField VARCHAR(100) DEFAULT NULL, imageField VARCHAR(100) DEFAULT NULL, videoField VARCHAR(100) DEFAULT NULL, orderKey INT NOT NULL, rootContentType TINYINT(1) NOT NULL, edit_twig_with_wysiwyg TINYINT(1) NOT NULL, active TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_41BCBAEC588AB49A (field_types_id), INDEX IDX_41BCBAEC903E3A94 (environment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE data_field (id INT AUTO_INCREMENT NOT NULL, field_type_id INT DEFAULT NULL, parent_id INT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, revision_id INT DEFAULT NULL, orderKey INT NOT NULL, INDEX IDX_154A89C72B68A933 (field_type_id), INDEX IDX_154A89C7727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE data_value (id INT AUTO_INCREMENT NOT NULL, data_field_id INT NOT NULL, integer_value BIGINT DEFAULT NULL, float_value DOUBLE PRECISION DEFAULT NULL, date_value DATETIME DEFAULT NULL, text_value LONGTEXT DEFAULT NULL, sha1 VARCHAR(20) DEFAULT NULL, index_key INT NOT NULL, INDEX IDX_53C894AB8EE9CE6C (data_field_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE environment (id INT AUTO_INCREMENT NOT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL, alias VARCHAR(255) NOT NULL, color VARCHAR(50) DEFAULT NULL, baseUrl VARCHAR(1024) DEFAULT NULL, managed TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_4626DE225E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE field_type (id INT AUTO_INCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, parent_id INT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, type VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, deleted TINYINT(1) NOT NULL, description LONGTEXT DEFAULT NULL, options LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json_array)\', orderKey INT NOT NULL, UNIQUE INDEX UNIQ_9F123E931A445520 (content_type_id), INDEX IDX_9F123E93727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE search (id BIGINT AUTO_INCREMENT NOT NULL, boolean VARCHAR(100) NOT NULL, type_facet VARCHAR(100) DEFAULT NULL, alias_facet VARCHAR(100) DEFAULT NULL, user VARCHAR(100) NOT NULL, name VARCHAR(100) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE search_filter (id BIGINT AUTO_INCREMENT NOT NULL, search_id BIGINT DEFAULT NULL, pattern VARCHAR(200) DEFAULT NULL, field VARCHAR(100) DEFAULT NULL, inverted TINYINT(1) DEFAULT NULL, operator VARCHAR(50) NOT NULL, boost NUMERIC(10, 2) DEFAULT NULL, INDEX IDX_A6263002650760A9 (search_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE job (id INT AUTO_INCREMENT NOT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, status VARCHAR(2048) NOT NULL, output LONGTEXT DEFAULT NULL, done TINYINT(1) NOT NULL, progress INT NOT NULL, arguments LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json_array)\', user VARCHAR(255) DEFAULT NULL, service VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE revision (id INT AUTO_INCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, data_field_id INT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, deleted TINYINT(1) NOT NULL, version INT DEFAULT 1 NOT NULL, ouuid VARCHAR(255) DEFAULT NULL, start_time DATETIME NOT NULL, end_time DATETIME DEFAULT NULL, draft TINYINT(1) NOT NULL, lock_by VARCHAR(255) DEFAULT NULL, lock_until DATETIME DEFAULT NULL, INDEX IDX_6D6315CC1A445520 (content_type_id), UNIQUE INDEX UNIQ_6D6315CC8EE9CE6C (data_field_id), UNIQUE INDEX tuple_index (end_time, ouuid), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE environment_revision (revision_id INT NOT NULL, environment_id INT NOT NULL, INDEX IDX_895F7B701DFA7C8F (revision_id), INDEX IDX_895F7B70903E3A94 (environment_id), PRIMARY KEY(revision_id, environment_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE template (id INT AUTO_INCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL, icon VARCHAR(255) DEFAULT NULL, body LONGTEXT DEFAULT NULL, edit_with_wysiwyg TINYINT(1) NOT NULL, render_option VARCHAR(255) NOT NULL, orderKey INT NOT NULL, mime_type VARCHAR(255) DEFAULT NULL, filename LONGTEXT DEFAULT NULL, extension VARCHAR(255) DEFAULT NULL, recipient VARCHAR(255) DEFAULT NULL, INDEX IDX_97601F831A445520 (content_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE view (id INT AUTO_INCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, icon VARCHAR(255) DEFAULT NULL, options LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json_array)\', orderKey INT NOT NULL, INDEX IDX_FEFDAB8E1A445520 (content_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE content_type ADD CONSTRAINT FK_41BCBAEC588AB49A FOREIGN KEY (field_types_id) REFERENCES field_type (id)');
        $this->addSql('ALTER TABLE content_type ADD CONSTRAINT FK_41BCBAEC903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id)');
        $this->addSql('ALTER TABLE data_field ADD CONSTRAINT FK_154A89C72B68A933 FOREIGN KEY (field_type_id) REFERENCES field_type (id)');
        $this->addSql('ALTER TABLE data_field ADD CONSTRAINT FK_154A89C7727ACA70 FOREIGN KEY (parent_id) REFERENCES data_field (id)');
        $this->addSql('ALTER TABLE data_value ADD CONSTRAINT FK_53C894AB8EE9CE6C FOREIGN KEY (data_field_id) REFERENCES data_field (id)');
        $this->addSql('ALTER TABLE field_type ADD CONSTRAINT FK_9F123E931A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id)');
        $this->addSql('ALTER TABLE field_type ADD CONSTRAINT FK_9F123E93727ACA70 FOREIGN KEY (parent_id) REFERENCES field_type (id)');
        $this->addSql('ALTER TABLE search_filter ADD CONSTRAINT FK_A6263002650760A9 FOREIGN KEY (search_id) REFERENCES search (id)');
        $this->addSql('ALTER TABLE revision ADD CONSTRAINT FK_6D6315CC1A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id)');
        $this->addSql('ALTER TABLE revision ADD CONSTRAINT FK_6D6315CC8EE9CE6C FOREIGN KEY (data_field_id) REFERENCES data_field (id)');
        $this->addSql('ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B701DFA7C8F FOREIGN KEY (revision_id) REFERENCES revision (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE environment_revision ADD CONSTRAINT FK_895F7B70903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE template ADD CONSTRAINT FK_97601F831A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id)');
        $this->addSql('ALTER TABLE view ADD CONSTRAINT FK_FEFDAB8E1A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id)');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE field_type DROP FOREIGN KEY FK_9F123E931A445520');
        $this->addSql('ALTER TABLE revision DROP FOREIGN KEY FK_6D6315CC1A445520');
        $this->addSql('ALTER TABLE template DROP FOREIGN KEY FK_97601F831A445520');
        $this->addSql('ALTER TABLE view DROP FOREIGN KEY FK_FEFDAB8E1A445520');
        $this->addSql('ALTER TABLE data_field DROP FOREIGN KEY FK_154A89C7727ACA70');
        $this->addSql('ALTER TABLE data_value DROP FOREIGN KEY FK_53C894AB8EE9CE6C');
        $this->addSql('ALTER TABLE revision DROP FOREIGN KEY FK_6D6315CC8EE9CE6C');
        $this->addSql('ALTER TABLE content_type DROP FOREIGN KEY FK_41BCBAEC903E3A94');
        $this->addSql('ALTER TABLE environment_revision DROP FOREIGN KEY FK_895F7B70903E3A94');
        $this->addSql('ALTER TABLE content_type DROP FOREIGN KEY FK_41BCBAEC588AB49A');
        $this->addSql('ALTER TABLE data_field DROP FOREIGN KEY FK_154A89C72B68A933');
        $this->addSql('ALTER TABLE field_type DROP FOREIGN KEY FK_9F123E93727ACA70');
        $this->addSql('ALTER TABLE search_filter DROP FOREIGN KEY FK_A6263002650760A9');
        $this->addSql('ALTER TABLE environment_revision DROP FOREIGN KEY FK_895F7B701DFA7C8F');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE content_type');
        $this->addSql('DROP TABLE data_field');
        $this->addSql('DROP TABLE data_value');
        $this->addSql('DROP TABLE environment');
        $this->addSql('DROP TABLE field_type');
        $this->addSql('DROP TABLE search');
        $this->addSql('DROP TABLE search_filter');
        $this->addSql('DROP TABLE job');
        $this->addSql('DROP TABLE revision');
        $this->addSql('DROP TABLE environment_revision');
        $this->addSql('DROP TABLE template');
        $this->addSql('DROP TABLE view');
    }
}
