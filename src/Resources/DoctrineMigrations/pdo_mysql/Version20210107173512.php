<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210107173512 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE environment ADD update_referrers TINYINT(1) DEFAULT \'0\' NOT NULL');

        $this->addSql('DROP TABLE single_type_index');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE environment DROP update_referrers');

        $this->addSql('CREATE TABLE single_type_index (id BIGINT AUTO_INCREMENT NOT NULL, content_type_id BIGINT DEFAULT NULL, environment_id INT DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, INDEX IDX_FEAD46B3903E3A94 (environment_id), INDEX IDX_FEAD46B31A445520 (content_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE single_type_index ADD CONSTRAINT FK_FEAD46B31A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id)');
        $this->addSql('ALTER TABLE single_type_index ADD CONSTRAINT FK_FEAD46B3903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id)');
    }
}
