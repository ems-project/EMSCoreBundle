<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataField\AssetFieldType;
use EMS\CoreBundle\Form\DataField\CheckboxFieldType;
use EMS\CoreBundle\Form\DataField\CollectionFieldType;
use EMS\CoreBundle\Form\DataField\CollectionItemFieldType;
use EMS\CoreBundle\Form\DataField\DateFieldType;
use EMS\CoreBundle\Form\DataField\DateRangeFieldType;
use EMS\CoreBundle\Form\DataField\DateTimeFieldType;
use EMS\CoreBundle\Form\DataField\IndexedAssetFieldType;
use EMS\CoreBundle\Form\DataField\IntegerFieldType;
use EMS\CoreBundle\Form\DataField\NestedFieldType;
use EMS\CoreBundle\Form\DataField\NumberFieldType;
use EMS\CoreBundle\Form\DataField\TimeFieldType;
use EMS\CoreBundle\Form\DataField\VersionTagFieldType;
use EMS\Helpers\Standard\Json;

final class Version20250111190930 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Remove the Mapping option\'s index field from FieldType ';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );
        $fieldTypes = $this->connection->executeQuery('select id, type, options from field_type');
        while ($fieldType = $fieldTypes->fetchAssociative()) {
            $options = Json::decode($fieldType['options']);
            if (!isset($options[FieldType::MAPPING_OPTIONS]['index'])) {
                continue;
            }
            $index = $options[FieldType::MAPPING_OPTIONS]['index'];
            unset($options[FieldType::MAPPING_OPTIONS]['index']);
            $analyzer = $options[FieldType::MAPPING_OPTIONS]['analyzer'] ?? null;
            $type = $options[FieldType::MAPPING_OPTIONS]['type'] ?? match ($fieldType['type']) {
                VersionTagFieldType::class => 'keyword',
                CheckboxFieldType::class => 'boolean',
                IntegerFieldType::class => 'integer',
                NumberFieldType::class => 'double',
                TimeFieldType::class, DateTimeFieldType::class, DateRangeFieldType::class, DateFieldType::class => 'date',
                IndexedAssetFieldType::class, CollectionItemFieldType::class, CollectionFieldType::class, AssetFieldType::class, NestedFieldType::class => 'nested',
                default => 'string',
            };
            if ('string' === $type && null === $analyzer && 'not_analyzed' === $index) {
                $options[FieldType::MAPPING_OPTIONS]['analyzer'] = 'keyword';
            }
            $this->addSql('UPDATE field_type SET options = :options WHERE id = :id', [
                'id' => $fieldType['id'],
                'options' => Json::encode($options),
            ]);
        }
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );
    }
}
