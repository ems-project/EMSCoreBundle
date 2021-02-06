<?php

namespace EMS\CoreBundle\Form\Data;

/**
 * @extends \IteratorAggregate<string, TableRowInterface>
 */
interface TableInterface extends \Countable, \IteratorAggregate
{
    public function isSortable(): bool;

    public function getAttributeName(): string;

    public function getLabelAttribute(): string;

    /**
     * @return iterable<TableColumn>
     */
    public function getColumns(): iterable;

    /**
     * @return iterable<TableItemAction>
     */
    public function getItemActions(): iterable;

    /**
     * @return iterable<TableAction>
     */
    public function getTableActions(): iterable;

    public function countTableActions(): int;

    /**
     * @return string[]
     */
    public function getSelected(): array;

    /**
     * @param string[] $selected
     */
    public function setSelected(array $selected): void;

    /**
     * @return string[]
     */
    public function getReordered();

    /**
     * @param string[] $reordered
     */
    public function setReordered(array $reordered): void;
}
