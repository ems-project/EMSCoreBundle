<?php

namespace EMS\CoreBundle\Resources\DoctrineMigrations\Scripts;

use Doctrine\Migrations\AbstractMigration;
use EMS\Helpers\Standard\Json;

trait ScriptContentTypeFields
{
    public function scriptEncodeFields(AbstractMigration $migration): void
    {
        $emptyStringToNull = function (?string $value): ?string {
            return $value && \strlen($value) > 0 ? $value : null;
        };

        $result = $migration->connection->executeQuery('select * from content_type');
        while ($row = $result->fetchAssociative()) {
            $migration->addSql('UPDATE content_type SET fields = :fields WHERE id = :id', [
                'fields' => Json::encode([
                    'label' => $emptyStringToNull($row['labelField'] ?? ($row['labelfield'] ?? null)),
                    'circles' => $emptyStringToNull($row['circles_field'] ?? null),
                    'color' => $emptyStringToNull($row['color_field'] ?? null),
                    'business_id' => $emptyStringToNull($row['business_id_field'] ?? null),
                ]),
                'id' => $row['id'],
            ]);
        }

//        $migration->addSql('ALTER TABLE content_type DROP labelField');
//        $migration->addSql('ALTER TABLE content_type DROP circles_field');
//        $migration->addSql('ALTER TABLE content_type DROP business_id_field');
//        $migration->addSql('ALTER TABLE content_type DROP color_field');
    }

    public function scriptDecodeFields(AbstractMigration $migration): void
    {
//        $migration->addSql('ALTER TABLE content_type ADD labelField VARCHAR(255) DEFAULT NULL');
//        $migration->addSql('ALTER TABLE content_type ADD circles_field VARCHAR(255) DEFAULT NULL');
//        $migration->addSql('ALTER TABLE content_type ADD business_id_field VARCHAR(255) DEFAULT NULL');
//        $migration->addSql('ALTER TABLE content_type ADD color_field VARCHAR(255) DEFAULT NULL');

        $updateQuery = <<<QUERY
            UPDATE content_type SET
                labelField = :labelField,
                circles_field = :circles_field,
                color_field = :color_field,
                business_id_field = :business_id_field
            WHERE id = :id
QUERY;

        $result = $migration->connection->executeQuery('select * from content_type');
        while ($row = $result->fetchAssociative()) {
            $fields = Json::decode($row['fields']);

            $migration->addSql($updateQuery, [
                'labelField' => $fields['label'] ?? null,
                'circles_field' => $fields['circles'] ?? null,
                'color_field' => $fields['color'] ?? null,
                'business_id_field' => $fields['business_id'] ?? null,
                'email_field' => $fields['email'] ?? null,
                'id' => $row['id'],
            ]);
        }

        $migration->addSql('ALTER TABLE content_type DROP fields');
    }
}
