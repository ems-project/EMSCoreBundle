<?php

namespace EMS\CoreBundle\Twig\Table;

interface TableInterface
{
    public function getTitleTransKey(): string;

    public function getTotal(): int;

    public function isSortable(): bool;

}
