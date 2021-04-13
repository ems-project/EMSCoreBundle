<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use EMS\CoreBundle\Helper\DataTableRequest;

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
    private ?string $orderField = null;
    private string $orderDirection = 'asc';
    private int $size;
    private int $from;
    private string $searchValue = '';
    private ?string $ajaxUrl = null;

    public function __construct(?string $ajaxUrl, int $from, int $to)
    {
        $this->ajaxUrl = $ajaxUrl;
        $this->from = $from;
        $this->size = $to;
    }

    public function isSortable(): bool
    {
        return false;
    }

    public function resetIterator(DataTableRequest $dataTableRequest): void
    {
        $this->from = $dataTableRequest->getFrom();
        $this->size = $dataTableRequest->getSize();
        $this->orderField = $dataTableRequest->getOrderField();
        $this->orderDirection = $dataTableRequest->getOrderDirection();
        $this->searchValue = $dataTableRequest->getSearchValue();
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

    public function addColumn(string $titleKey, string $attribute): TableColumn
    {
        $column = new TableColumn($titleKey, $attribute);
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

    public function setDefaultOrder(string $orderField, string $direction = 'asc'): void
    {
        $this->orderField = $orderField;
        $this->orderDirection = $direction;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFrontendOptions(): array
    {
        $columnIndex = 0;
        if ($this->supportsTableActions()) {
            $columnIndex = 1;
        }
        if (!$this->isSortable() && null !== $this->orderField) {
            $counter = $columnIndex;
            foreach ($this->getColumns() as $column) {
                if ($this->orderField === $column->getAttribute()) {
                    $columnIndex = $counter;
                    break;
                }
                ++$counter;
            }
        }
        $options = [
            'order' => [[$columnIndex, $this->orderDirection]],
        ];

        $ajaxUrl = $this->getAjaxUrl();
        if (null !== $ajaxUrl) {
            $options = \array_merge($options, [
                'processing' => true,
                'serverSide' => true,
                'ajax' => $ajaxUrl,
            ]);
        }

        return $options;
    }

    public function getAjaxUrl(): ?string
    {
        return null;
    }

    public function getOrderField(): ?string
    {
        return $this->orderField;
    }

    public function getOrderDirection(): string
    {
        return $this->orderDirection;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getFrom(): int
    {
        return $this->from;
    }

    public function getSearchValue(): string
    {
        return $this->searchValue;
    }

    abstract public function supportsTableActions(): bool;

    abstract public function totalCount(): int;
}
