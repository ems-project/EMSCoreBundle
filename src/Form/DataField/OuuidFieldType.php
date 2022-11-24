<?php

namespace EMS\CoreBundle\Form\DataField;

class OuuidFieldType extends DataFieldType
{
    public function getLabel(): string
    {
        return 'Copy of the object identifier';
    }

    public static function getIcon(): string
    {
        return 'fa fa-key';
    }

    public function getBlockPrefix(): string
    {
        return 'empty';
    }
}
