<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataField\ChoiceFieldType;
use EMS\Helpers\Standard\Json;

final class Version20250112174825 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace RadioFieldType and SelectFieldType by ChoiceFieldType';
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
            if ('EMS\CoreBundle\Form\DataField\RadioFieldType' === $fieldType['type']) {
                $options[FieldType::DISPLAY_OPTIONS]['expanded'] = true;
                $options[FieldType::DISPLAY_OPTIONS]['multiple'] = false;
            } elseif ('EMS\CoreBundle\Form\DataField\SelectFieldType' === $fieldType['type']) {
                $options[FieldType::DISPLAY_OPTIONS]['expanded'] = false;
                $options[FieldType::DISPLAY_OPTIONS]['multiple'] ??= false;
            } else {
                continue;
            }
            $this->addSql('UPDATE field_type SET options = :options WHERE id = :id', [
                'id' => $fieldType['id'],
                'type' => ChoiceFieldType::class,
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
