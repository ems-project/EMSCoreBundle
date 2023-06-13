<?php

namespace EMS\CoreBundle\Core\DataTable\Type;

use Symfony\Component\OptionsResolver\OptionsResolver;

interface DataTableTypeInterface
{
    /**
     * @return string[]
     */
    public function getRoles(): array;

    public function getHash(): string;

    public function configureOptions(OptionsResolver $optionsResolver): void;
}
