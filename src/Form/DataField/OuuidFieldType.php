<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

class OuuidFieldType extends DataFieldType
{
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
