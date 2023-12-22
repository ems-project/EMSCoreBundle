<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231210103056 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'convert deprecated array type to json type';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $users = $this->connection->executeQuery('select id, roles from "user"');
        while ($userRow = $users->fetchAssociative()) {
            $this->addSql('UPDATE "user" SET roles = :roles WHERE id = :id', [
                'id' => $userRow['id'],
                'roles' => \json_encode(\unserialize($userRow['roles'])),
            ]);
        }

        $uploadAssets = $this->connection->executeQuery('select id, head_in from uploaded_asset where head_in is not null');
        while ($uploadAsset = $uploadAssets->fetchAssociative()) {
            $this->addSql('UPDATE uploaded_asset SET head_in = :head_in WHERE id = :id', [
                'id' => $uploadAsset['id'],
                'head_in' => \json_encode(\unserialize($uploadAsset['head_in'])),
            ]);
        }

        $this->addSql('ALTER TABLE uploaded_asset ALTER head_in TYPE JSON USING head_in::json');
        $this->addSql('COMMENT ON COLUMN uploaded_asset.head_in IS NULL');
        $this->addSql('ALTER TABLE "user" ALTER roles TYPE JSON USING roles::json');
        $this->addSql('COMMENT ON COLUMN "user".roles IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE "user" ALTER roles TYPE TEXT');
        $this->addSql('COMMENT ON COLUMN "user".roles IS \'(DC2Type:array)\'');
        $this->addSql('ALTER TABLE uploaded_asset ALTER head_in TYPE TEXT');
        $this->addSql('COMMENT ON COLUMN uploaded_asset.head_in IS \'(DC2Type:array)\'');

        $userRoles = $this->connection->executeQuery('select id, roles from "user"');
        while ($userRow = $userRoles->fetchAssociative()) {
            $this->addSql('UPDATE "user" SET roles = :roles WHERE id = :id', [
                'id' => $userRow['id'],
                'roles' => \serialize(\json_decode($userRow['roles'])),
            ]);
        }

        $uploadAssets = $this->connection->executeQuery('select id, head_in from uploaded_asset where head_in is not null');
        while ($uploadAsset = $uploadAssets->fetchAssociative()) {
            $this->addSql('UPDATE uploaded_asset SET head_in = :head_in WHERE id = :id', [
                'id' => $uploadAsset['id'],
                'head_in' => \serialize(\json_decode($uploadAsset['head_in'])),
            ]);
        }
    }
}
