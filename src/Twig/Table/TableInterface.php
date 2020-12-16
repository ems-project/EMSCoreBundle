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

    /**
     * @return string[]
     */
    public function getSelected(): array;

    /**
     * @param string[] $selected
     */
    public function setSelected(array $selected): void;
}
