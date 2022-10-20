<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use EMS\Helpers\Standard\Json;

final class Version20221020125332 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_type ADD roles JSON DEFAULT NULL');

        $result = $this->connection->executeQuery('select * from content_type');
        while ($row = $result->fetchAssociative()) {
            $this->addSql('UPDATE content_type SET roles = :roles WHERE id = :id', [
                'roles' => Json::encode([
                    'delete' => $row['delete_role'] ?? 'not-defined',
                    'show_link_create' => $row['createLinkDisplayRole'] ?? 'ROLE_USER',
                    'show_link_search' => $row['searchLinkDisplayRole'] ?? 'ROLE_USER',
                ]),
                'id' => $row['id'],
            ]);
        }

        $this->addSql('ALTER TABLE content_type DROP delete_role');
        $this->addSql('ALTER TABLE content_type DROP createLinkDisplayRole');
        $this->addSql('ALTER TABLE content_type DROP searchLinkDisplayRole');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_type ADD delete_role VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD createLinkDisplayRole VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_type ADD searchLinkDisplayRole VARCHAR(100) DEFAULT NULL');

        $updateQuery = <<<QUERY
            UPDATE content_type SET 
                delete_role = :delete_role,
                createLinkDisplayRole = :show_link_create,
                searchLinkDisplayRole = :show_link_search
            WHERE id = :id
QUERY;

        $result = $this->connection->executeQuery('select * from content_type');
        while ($row = $result->fetchAssociative()) {
            $roles = Json::decode($row['roles']);

            $this->addSql($updateQuery, [
                'delete_role' => $roles['delete'] ?? 'not-defined',
                'show_link_create' => $roles['show_link_create'] ?? 'ROLE_USER',
                'show_link_search' => $roles['show_link_search'] ?? 'ROLE_USER',
                'id' => $row['id'],
            ]);
        }

        $this->addSql('ALTER TABLE content_type DROP roles');
    }
}
