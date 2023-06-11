<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\DataTable\Type;

abstract class AbstractTableType implements DataTableTypeInterface
{
    /**
     * @return string[]
     */
    abstract public function getRoles(): array;
}
