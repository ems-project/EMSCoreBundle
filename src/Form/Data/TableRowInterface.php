<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

interface TableRowInterface
{
    /**
     * @return object|array<mixed>
     */
    public function getData(): object|array;
}
