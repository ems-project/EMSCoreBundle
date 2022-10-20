<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use EMS\Helpers\Standard\Json;

final class Version20221020135425 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE content_type ADD roles LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\'');

        $result = $this->connection->executeQuery('select * from content_type');
        while ($row = $result->fetchAssociative()) {
            $this->addSql('UPDATE content_type SET roles = :roles WHERE id = :id', [
                'roles' => Json::encode([
                    'archive' => $row['archive_role'] ?? 'not-defined',
                    'view' => $row['view_role'] ?? 'not-defined',
                    'delete' => $row['delete_role'] ?? null,
                    'show_link_create' => $row['createLinkDisplayRole'] ?? 'ROLE_USER',
                    'show_link_search' => $row['searchLinkDisplayRole'] ?? 'ROLE_USER',
                ]),
                'id' => $row['id'],
            ]);
        }

        $this->addSql('ALTER TABLE content_type DROP archive_role');
        $this->addSql('ALTER TABLE content_type DROP view_role');
        $this->addSql('ALTER TABLE content_type DROP delete_role');
        $this->addSql('ALTER TABLE content_type DROP createLinkDisplayRole');
        $this->addSql('ALTER TABLE content_type DROP searchLinkDisplayRole');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE content_type ADD archive_role VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD view_role VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD delete_role VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD createLinkDisplayRole VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD searchLinkDisplayRole VARCHAR(100) DEFAULT NULL');

        $updateQuery = <<<QUERY
            UPDATE content_type SET 
                view_role = :view_role,
                delete_role = :delete_role,
                createLinkDisplayRole = :show_link_create,
                searchLinkDisplayRole = :show_link_search
            WHERE id = :id
QUERY;

        $result = $this->connection->executeQuery('select * from content_type');
        while ($row = $result->fetchAssociative()) {
            $roles = Json::decode($row['roles']);

            $this->addSql($updateQuery, [
                'archive_role' => $roles['archive'] ?? 'not-defined',
                'view_role' => $roles['view'] ?? 'not-defined',
                'delete_role' => $roles['delete'] ?? 'not-defined',
                'show_link_create' => $roles['show_link_create'] ?? 'ROLE_USER',
                'show_link_search' => $roles['show_link_search'] ?? 'ROLE_USER',
                'id' => $row['id'],
            ]);
        }

        $this->addSql('ALTER TABLE content_type DROP roles');
    }
}
