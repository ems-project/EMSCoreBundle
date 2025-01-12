<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class Select2Type extends ChoiceType
{
    #[\Override]
    public function getParent(): string
    {
        return ChoiceType::class;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'select2';
    }
}
