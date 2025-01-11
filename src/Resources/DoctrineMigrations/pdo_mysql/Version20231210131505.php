<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use EMS\Helpers\Standard\Json;

final class Version20231210131505 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'convert deprecated array type to json type';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $users = $this->connection->executeQuery('select id, roles from user');
        while ($userRow = $users->fetchAssociative()) {
            $this->addSql('UPDATE user SET roles = :roles WHERE id = :id', [
                'id' => $userRow['id'],
                'roles' => Json::encode(\unserialize($userRow['roles'])),
            ]);
        }

        $uploadAssets = $this->connection->executeQuery('select id, head_in from uploaded_asset where head_in is not null');
        while ($uploadAsset = $uploadAssets->fetchAssociative()) {
            $this->addSql('UPDATE uploaded_asset SET head_in = :head_in WHERE id = :id', [
                'id' => $uploadAsset['id'],
                'head_in' => Json::encode(\unserialize($uploadAsset['head_in'])),
            ]);
        }

        $this->addSql('ALTER TABLE uploaded_asset CHANGE head_in head_in JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL COMMENT \'(DC2Type:json)\'');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE uploaded_asset CHANGE head_in head_in LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:array)\'');
        $this->addSql('ALTER TABLE `user` CHANGE roles roles LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\'');

        $userRoles = $this->connection->executeQuery('select id, roles from user');
        while ($userRow = $userRoles->fetchAssociative()) {
            $this->addSql('UPDATE user SET roles = :roles WHERE id = :id', [
                'id' => $userRow['id'],
                'roles' => \serialize(\json_decode((string) $userRow['roles'])),
            ]);
        }

        $uploadAssets = $this->connection->executeQuery('select id, head_in from uploaded_asset where head_in is not null');
        while ($uploadAsset = $uploadAssets->fetchAssociative()) {
            $this->addSql('UPDATE uploaded_asset SET head_in = :head_in WHERE id = :id', [
                'id' => $uploadAsset['id'],
                'head_in' => \serialize(\json_decode((string) $uploadAsset['head_in'])),
            ]);
        }
    }
}
