<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\FieldType;

class OuuidFieldType extends DataFieldType
{
    #[\Override]
    public function generateJsonSchema(FieldType $fieldType, callable $buildObjectSchema): array
    {
        return ['type' => 'string'];
    }

    #[\Override]
    public function getLabel(): string
    {
        return 'Copy of the object identifier';
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'fa fa-key';
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'empty';
    }
}
