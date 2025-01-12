<?php

declare(strict_types=1);

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

    public function getItemActions(): TableItemActionCollection;

    /**
     * @return iterable<TableAction>
     */
    public function getTableActions(): iterable;

    /**
     * @return iterable<TableAction>
     */
    public function getTableMassActions(): iterable;

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

    /**
     * @return array<string, mixed>
     */
    public function getFrontendOptions(): array;

    public function getAjaxUrl(): ?string;

    public function supportsTableActions(): bool;

    public function next(int $pagingSize = 100): bool;

    public function setSize(int $size): void;

    public function getRowTemplate(): string;

    public function getExportSheetName(): string;

    public function getExportFileName(): string;

    public function getExportDisposition(): string;
}
