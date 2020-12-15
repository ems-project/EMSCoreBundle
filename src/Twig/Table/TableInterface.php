<?php

namespace EMS\CoreBundle\Twig\Table;

/**
 * @extends \IteratorAggregate<string, TableRowInterface>
 */
interface TableInterface extends \Countable, \IteratorAggregate
{
    public function getTitleTransKey(): string;

    public function getAddTransKey(): string;

    public function isSortable(): bool;

    /**
     * @return iterable<TableColumn>
     */
    public function getColumns(): iterable;

    /**
     * @return iterable<TableAction>
     */
    public function getActions(): iterable;
}
