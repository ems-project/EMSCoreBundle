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
                    'delete' => $row['delete_role'] ?? null,
                ]),
                'id' => $row['id'],
            ]);
        }

        $this->addSql('ALTER TABLE content_type DROP delete_role');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_type ADD delete_role VARCHAR(100) DEFAULT NULL');

        $result = $this->connection->executeQuery('select * from content_type');
        while ($row = $result->fetchAssociative()) {
            $roles = Json::decode($row['roles']);

            $this->addSql('UPDATE content_type SET delete_role = :delete_role WHERE id = :id', [
                'delete_role' => $roles['delete'] ?? null,
                'id' => $row['id'],
            ]);
        }

        $this->addSql('ALTER TABLE content_type DROP roles');
    }
}
