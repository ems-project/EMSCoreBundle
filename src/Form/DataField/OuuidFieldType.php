<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Translation\t;

class OuuidFieldType extends DataFieldType
{
    #[\Override]
    public function generateMcpSchema(FieldType $fieldType, callable $buildObjectSchema, bool $isOutputSchema = false): array
    {
        return ['type' => 'string'];
    }

    #[\Override]
    public function getLabel(): TranslatableMessage
    {
        return t('field_type.ouuid', [], 'emsco-core');
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
