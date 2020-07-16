<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20161204221357 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE auth_tokens (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, value VARCHAR(255) NOT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, INDEX IDX_8AF9B66CA76ED395 (user_id), UNIQUE INDEX auth_tokens_value_unique (value), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE auth_tokens ADD CONSTRAINT FK_8AF9B66CA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE auth_tokens');
    }
}
