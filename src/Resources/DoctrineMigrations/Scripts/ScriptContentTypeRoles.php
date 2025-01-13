<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Resources\DoctrineMigrations\Scripts;

use Doctrine\Migrations\AbstractMigration;
use EMS\Helpers\Standard\Json;

trait ScriptContentTypeRoles
{
    public function scriptEncodeRoles(AbstractMigration $migration): void
    {
        $result = $migration->connection->executeQuery('select * from content_type');
        while ($row = $result->fetchAssociative()) {
            $migration->addSql('UPDATE content_type SET roles = :roles WHERE id = :id', [
                'roles' => Json::encode([
                    'view' => $row['view_role'] ?? 'ROLE_AUTHOR',
                    'create' => $row['create_role'] ?? 'ROLE_AUTHOR',
                    'edit' => $row['edit_role'] ?? 'ROLE_AUTHOR',
                    'publish' => $row['publish_role'] ?? 'ROLE_PUBLISHER',
                    'delete' => $row['delete_role'] ?? 'not-defined',
                    'trash' => $row['trash_role'] ?? 'not-defined',
                    'archive' => $row['archive_role'] ?? 'not-defined',
                    'owner' => $row['owner_role'] ?? 'not-defined',
                    'show_link_create' => $row['createLinkDisplayRole'] ?? ($row['createlinkdisplayrole'] ?? 'ROLE_USER'),
                    'show_link_search' => $row['searchLinkDisplayRole'] ?? ($row['searchlinkdisplayrole'] ?? 'ROLE_USER'),
                ]),
                'id' => $row['id'],
            ]);
        }

        $migration->addSql('ALTER TABLE content_type DROP view_role');
        $migration->addSql('ALTER TABLE content_type DROP create_role');
        $migration->addSql('ALTER TABLE content_type DROP edit_role');
        $migration->addSql('ALTER TABLE content_type DROP publish_role');
        $migration->addSql('ALTER TABLE content_type DROP delete_role');
        $migration->addSql('ALTER TABLE content_type DROP trash_role');
        $migration->addSql('ALTER TABLE content_type DROP archive_role');
        $migration->addSql('ALTER TABLE content_type DROP owner_role');
        $migration->addSql('ALTER TABLE content_type DROP createLinkDisplayRole');
        $migration->addSql('ALTER TABLE content_type DROP searchLinkDisplayRole');
    }

    public function scriptDecodeRoles(AbstractMigration $migration): void
    {
        $migration->addSql('ALTER TABLE content_type ADD view_role VARCHAR(100) DEFAULT NULL');
        $migration->addSql('ALTER TABLE content_type ADD create_role VARCHAR(100) DEFAULT NULL');
        $migration->addSql('ALTER TABLE content_type ADD edit_role VARCHAR(100) DEFAULT NULL');
        $migration->addSql('ALTER TABLE content_type ADD publish_role VARCHAR(100) DEFAULT NULL');
        $migration->addSql('ALTER TABLE content_type ADD delete_role VARCHAR(100) DEFAULT NULL');
        $migration->addSql('ALTER TABLE content_type ADD trash_role VARCHAR(100) DEFAULT NULL');
        $migration->addSql('ALTER TABLE content_type ADD archive_role VARCHAR(100) DEFAULT NULL');
        $migration->addSql('ALTER TABLE content_type ADD owner_role VARCHAR(100) DEFAULT NULL');
        $migration->addSql('ALTER TABLE content_type ADD createLinkDisplayRole VARCHAR(100) DEFAULT NULL');
        $migration->addSql('ALTER TABLE content_type ADD searchLinkDisplayRole VARCHAR(100) DEFAULT NULL');

        $updateQuery = <<<QUERY
                        UPDATE content_type SET 
                            view_role = :view_role,
                            create_role = :create_role,
                            edit_role = :edit_role,
                            publish_role = :publish_role,
                            delete_role = :delete_role,
                            trash_role = :trash_role,
                            archive_role = :archive_role,
                            owner_role = :owner_role,
                            createLinkDisplayRole = :show_link_create,
                            searchLinkDisplayRole = :show_link_search
                        WHERE id = :id
            QUERY;

        $result = $migration->connection->executeQuery('select * from content_type');
        while ($row = $result->fetchAssociative()) {
            $roles = Json::decode($row['roles']);

            $migration->addSql($updateQuery, [
                'view_role' => $roles['view'] ?? 'ROLE_AUTHOR',
                'create_role' => $roles['create'] ?? 'ROLE_AUTHOR',
                'edit_role' => $roles['edit'] ?? 'ROLE_AUTHOR',
                'publish_role' => $roles['publish'] ?? 'ROLE_PUBLISHER',
                'delete_role' => $roles['delete'] ?? 'not-defined',
                'trash_role' => $roles['trash'] ?? 'not-defined',
                'archive_role' => $roles['archive'] ?? 'not-defined',
                'owner_role' => $roles['owner'] ?? 'not-defined',
                'show_link_create' => $roles['show_link_create'] ?? 'ROLE_USER',
                'show_link_search' => $roles['show_link_search'] ?? 'ROLE_USER',
                'id' => $row['id'],
            ]);
        }

        $migration->addSql('ALTER TABLE content_type DROP roles');
    }
}
