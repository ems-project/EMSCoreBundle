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

        if (\is_string($str)) {
            $str = \preg_replace('/[^a-z0-9\s+\-]/', '', $str);
        }
        if (\is_string($str)) {
            $str = \preg_replace('/\s+/', ' ', $str);
        }
        if (\is_string($str)) {
            $str = \preg_replace('/\-/', ' ', $str);
        }
        if (\is_string($str)) {
            $str = \explode(' ', $str);
        }

        if (!\is_array($str)) {
            throw new \RuntimeException('Humanize failed!');
        }

        $str = \array_map('ucwords', $str);

        return \implode(' ', $str);
    }
}
