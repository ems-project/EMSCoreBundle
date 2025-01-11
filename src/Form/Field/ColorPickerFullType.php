<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\Extension\Core\Type\TextType;

class ColorPickerFullType extends TextType
{
    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'colorpicker';
    }
}
