<?php

namespace EMS\CoreBundle\Twig\Table;

/**
 * @extends \IteratorAggregate<string, TableRowInterface>
 */
interface TableInterface extends \Countable, \IteratorAggregate
{
    public function getTitleTransKey(): string;

    public function isSortable(): bool;
}
