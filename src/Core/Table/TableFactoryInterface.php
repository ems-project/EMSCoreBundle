<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Table;

interface TableFactoryInterface
{
    public function create(string $typeClass): TableInterface;

    public function createByName(string $name): TableInterface;
}