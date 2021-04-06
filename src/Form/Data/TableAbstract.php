<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

abstract class TableAbstract implements TableInterface
{
    /** @var string */
    public const DELETE_ACTION = 'delete';
    /** @var string */
    public const DOWNLOAD_ACTION = 'download';
    /** @var string */
    public const EXPORT_ACTION = 'export';

    /** @var string[] */
    private $selected = [];
    /** @var string[] */
    private $reordered = [];
    /** @var TableColumn[] */
    private $columns = [];
    /** @var TableItemAction[] */
    private $itemActions = [];
    /** @var TableAction[] */
    private $tableActions = [];

    public function isSortable(): bool
    {
        return false;
    }

    public function getLabelAttribute(): string
    {
        return 'name';
    }

    /**
     * @return string[]
     */
    public function getSelected(): array
    {
        return $this->selected;
    }

    /**
     * @param string[] $selected
     */
    public function setSelected(array $selected): void
    {
        $this->selected = $selected;
    }

    /**
     * @return string[]
     */
    public function getReordered(): array
    {
        return $this->reordered;
    }

    /**
     * @param string[] $reordered
     */
    public function setReordered(array $reordered): void
    {
        $this->reordered = $reordered;
    }

    /**
     * @param array<mixed, string> $valueToIconMapping
     */
    public function addColumn(string $titleKey, string $attribute, array $valueToIconMapping = []): TableColumn
    {
        $column = new TableColumn($titleKey, $attribute, $valueToIconMapping);
        $this->columns[] = $column;

        return $column;
    }

    public function addColumnDefinition(TableColumn $column): TableColumn
    {
        $this->columns[] = $column;

        return $column;
    }

    /**
     * @return TableColumn[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @param array<mixed> $routeParameters
     */
    public function addItemGetAction(string $route, string $labelKey, string $icon, array $routeParameters = []): TableItemAction
    {
        $action = TableItemAction::getAction($route, $labelKey, $icon, $routeParameters);
        $this->itemActions[] = $action;

        return $action;
    }

    /**
     * @param array<string, mixed> $routeParameters
     */
    public function addItemPostAction(string $route, string $labelKey, string $icon, string $messageKey, array $routeParameters = []): TableItemAction
    {
        $action = TableItemAction::postAction($route, $labelKey, $icon, $messageKey, $routeParameters);
        $this->itemActions[] = $action;

        return $action;
    }

    /**
     * @param array<string, string> $routeParameters
     */
    public function addDynamicItemPostAction(string $route, string $labelKey, string $icon, string $messageKey, array $routeParameters = []): TableItemAction
    {
        $action = TableItemAction::postDynamicAction($route, $labelKey, $icon, $messageKey, $routeParameters);
        $this->itemActions[] = $action;

        return $action;
    }

    /**
     * @param array<string, string> $routeParameters
     */
    public function addDynamicItemGetAction(string $route, string $labelKey, string $icon, array $routeParameters = []): TableItemAction
    {
        $action = TableItemAction::getDynamicAction($route, $labelKey, $icon, $routeParameters);
        $this->itemActions[] = $action;

        return $action;
    }

    /**
     * @return TableItemAction[]
     */
    public function getItemActions(): iterable
    {
        return $this->itemActions;
    }

    public function addTableAction(string $name, string $icon, string $labelKey, string $confirmationKey): TableAction
    {
        $action = new TableAction($name, $icon, $labelKey, $confirmationKey);
        $this->tableActions[] = $action;

        return $action;
    }

    /**
     * @return TableAction[]
     */
    public function getTableActions(): iterable
    {
        return $this->tableActions;
    }

    public function countTableActions(): int
    {
        return \count($this->tableActions);
    }
}
