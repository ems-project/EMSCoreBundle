<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20250220092804 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Group entity that add properties (e.g. roles) to User entities';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_group (name VARCHAR(255) NOT NULL, label VARCHAR(255) NOT NULL, roles JSON NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8F02BF9D5E237E06 ON user_group (name)');
        $this->addSql('ALTER TABLE "user" ADD group_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_8D93D649FE54D947 FOREIGN KEY (group_id) REFERENCES user_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_8D93D649FE54D947 ON "user" (group_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_group');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D649FE54D947');
        $this->addSql('DROP INDEX IDX_8D93D649FE54D947');
        $this->addSql('ALTER TABLE "user" DROP group_id');
    }
}
