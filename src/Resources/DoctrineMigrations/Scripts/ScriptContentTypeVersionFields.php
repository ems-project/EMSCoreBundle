<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Resources\DoctrineMigrations\Scripts;

use Doctrine\Migrations\AbstractMigration;
use EMS\Helpers\Standard\Json;

trait ScriptContentTypeVersionFields
{
    public function scriptEncodeVersionFields(AbstractMigration $migration): void
    {
        $result = $migration->connection->executeQuery('select * from content_type');
        while ($row = $result->fetchAssociative()) {
            $migration->addSql('UPDATE content_type SET version_fields = :version_fields WHERE id = :id', [
                'version_fields' => Json::encode([
                    'date_from' => $row['version_date_from_field'] ?? null,
                    'date_to' => $row['version_date_to_field'] ?? null,
                ]),
                'id' => $row['id'],
            ]);
        }

        $migration->addSql('ALTER TABLE content_type DROP version_date_from_field');
        $migration->addSql('ALTER TABLE content_type DROP version_date_to_field');
    }

    public function scriptDecodeVersionFields(AbstractMigration $migration): void
    {
        $migration->addSql('ALTER TABLE content_type ADD version_date_from_field VARCHAR(100) DEFAULT NULL');
        $migration->addSql('ALTER TABLE content_type ADD version_date_to_field VARCHAR(100) DEFAULT NULL');

        $updateQuery = <<<QUERY
            UPDATE content_type SET 
                version_date_from_field = :version_date_from_field,
                version_date_to_field = :version_date_to_field
            WHERE id = :id
QUERY;

        $result = $migration->connection->executeQuery('select * from content_type');
        while ($row = $result->fetchAssociative()) {
            $fields = Json::decode($row['version_fields']);

            $migration->addSql($updateQuery, [
                'version_date_from_field' => $fields['date_from'] ?? null,
                'version_date_to_field' => $fields['date_to'] ?? null,
                'id' => $row['id'],
            ]);
        }

        $migration->addSql('ALTER TABLE content_type DROP version_fields');
    }
}
