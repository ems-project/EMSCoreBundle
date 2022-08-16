<?php

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170603232326 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE "user" ADD wysiwyg_profile_id INT DEFAULT NULL');
        $this->addSql('UPDATE "user" SET wysiwyg_profile_id = 1');
        $this->addSql('UPDATE "user" SET wysiwyg_profile_id = 3 where wysiwyg_profile = \'full\'');
        $this->addSql('UPDATE "user" SET wysiwyg_profile_id = 2 where wysiwyg_profile = \'light\'');
        $this->addSql('UPDATE "user" SET wysiwyg_profile_id = NULL where wysiwyg_profile = \'custom\'');

        $this->addSql('ALTER TABLE "user" DROP wysiwyg_profile');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_8D93D649A282F7EA FOREIGN KEY (wysiwyg_profile_id) REFERENCES wysiwyg_profile (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_8D93D649A282F7EA ON "user" (wysiwyg_profile_id)');
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D649A282F7EA');
        $this->addSql('DROP INDEX IDX_8D93D649A282F7EA');
        $this->addSql('ALTER TABLE "user" ADD wysiwyg_profile TEXT DEFAULT NULL');
//         $this->addSql('UPDATE "user" SET wysiwyg_profile = '1');
//         $this->addSql('UPDATE "user" SET wysiwyg_profile_id = 3 where wysiwyg_profile = \'full\'');
//         $this->addSql('UPDATE "user" SET wysiwyg_profile_id = 2 where wysiwyg_profile = \'light\'');
//         $this->addSql('UPDATE "user" SET wysiwyg_profile_id = NULL where wysiwyg_profile = \'custom\'');
        $this->addSql('ALTER TABLE "user" DROP wysiwyg_profile_id');
    }
}
