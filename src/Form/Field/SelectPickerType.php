<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class SelectPickerType extends ChoiceType
{
    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'selectpicker';
    }

    public static function humanize(string $str): string
    {
        $str = \trim(\strtolower($str));
        $str = \preg_replace('/\_/', ' ', $str);
        $str = \preg_replace('/[^a-z0-9\s+\-]/', '', $str);
        $str = \preg_replace('/\s+/', ' ', $str);
        $str = \preg_replace('/\-/', ' ', $str);
        $str = \explode(' ', $str);

        $str = \array_map('ucwords', $str);

        return \implode(' ', $str);
    }
}
