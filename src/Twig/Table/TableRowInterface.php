<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig\Table;

interface TableRowInterface
{
    public function getData(): object;
}
