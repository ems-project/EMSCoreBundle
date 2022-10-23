<?php

namespace EMS\CoreBundle\Resources\DoctrineMigrations\Scripts;

use Doctrine\Migrations\AbstractMigration;
use EMS\Helpers\Standard\Json;

trait ScriptContentTypeFields
{
    public function scriptEncodeFields(AbstractMigration $migration): void
    {
        $result = $migration->connection->executeQuery('select * from content_type');
        while ($row = $result->fetchAssociative()) {
            $migration->addSql('UPDATE content_type SET fields = :fields WHERE id = :id', [
                'fields' => Json::encode([
                    'label' => $row['labelField'] ?? ($row['labelfield'] ?? null),
                ]),
                'id' => $row['id'],
            ]);
        }

        $migration->addSql('ALTER TABLE content_type DROP labelField');
    }

    public function scriptDecodeFields(AbstractMigration $migration): void
    {
        $migration->addSql('ALTER TABLE content_type ADD labelField VARCHAR(255) DEFAULT NULL');

        $updateQuery = <<<QUERY
            UPDATE content_type SET
                labelField = :labelField
            WHERE id = :id
QUERY;

        $result = $migration->connection->executeQuery('select * from content_type');
        while ($row = $result->fetchAssociative()) {
            $fields = Json::decode($row['fields']);

            $migration->addSql($updateQuery, [
                'labelField' => $fields['label'] ?? null,
                'id' => $row['id'],
            ]);
        }

        $migration->addSql('ALTER TABLE content_type DROP fields');
    }
}
