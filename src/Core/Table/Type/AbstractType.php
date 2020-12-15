<?php

namespace EMS\CoreBundle\Core\Table\Type;

use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractType implements TypeInterface
{
    public function configureOptions(OptionsResolver $optionsResolver): void
    {
    }
}