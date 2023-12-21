<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use EMS\CoreBundle\Helper\DataTableRequest;

abstract class TableAbstract implements TableInterface
{
    /** @var string */
    final public const DELETE_ACTION = 'delete';
    /** @var string */
    final public const DOWNLOAD_ACTION = 'download';
    /** @var string */
    final public const EXPORT_ACTION = 'export';

    /** @var string */
    final public const ADD_ACTION = 'add';

    /** @var string */
    final public const REMOVE_ACTION = 'remove';

    /** @var string[] */
    private array $selected = [];
    /** @var string[] */
    private array $reordered = [];
    /** @var TableColumn[] */
    private array $columns = [];
    private TableItemActionCollection $itemActionCollection;
    /** @var TableAction[] */
    private array $tableActions = [];
    private ?string $orderField = null;
    private string $orderDirection = 'asc';
    private string $searchValue = '';
    /** @var array<mixed> */
    private array $extraFrontendOption = [];

    private string $exportSheetName = 'table';
    private string $exportFileName = 'table';
    private string $exportDisposition = 'attachment';
    private string $labelAttribute = 'name';
    private string $rowActionsClass = '';

    public function __construct(private readonly ?string $ajaxUrl, private int $from, private int $size)
    {
        $this->itemActionCollection = new TableItemActionCollection();
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

    public function next(int $pagingSize = 100): bool
    {
        if ($this->from + $this->size >= $this->count()) {
            return false;
        }
        $this->from = $this->from + $this->size;
        $this->size = $pagingSize;

        return true;
    }

    public function getLabelAttribute(): string
    {
        return $this->labelAttribute;
    }

    public function setLabelAttribute(string $labelAttribute): void
    {
        $this->labelAttribute = $labelAttribute;
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

    public function addItemActionCollection(string $labelKey = null, string $icon = null): TableItemActionCollection
    {
        $itemActionCollection = new TableItemActionCollection($labelKey, $icon);
        $this->itemActionCollection->addItemActionCollection($itemActionCollection);

        return $itemActionCollection;
    }

    /**
     * @param array<mixed> $routeParameters
     */
    public function addItemGetAction(string $route, string $labelKey, string $icon, array $routeParameters = []): TableItemAction
    {
        return $this->itemActionCollection->addItemGetAction($route, $labelKey, $icon, $routeParameters);
    }

    /**
     * @param array<string, mixed> $routeParameters
     */
    public function addItemPostAction(string $route, string $labelKey, string $icon, string $messageKey, array $routeParameters = []): TableItemAction
    {
        return $this->itemActionCollection->addItemPostAction($route, $labelKey, $icon, $messageKey, $routeParameters);
    }

    /**
     * @param array<string, string> $routeParameters
     */
    public function addDynamicItemPostAction(string $route, string $labelKey, string $icon, string $messageKey, array $routeParameters = []): TableItemAction
    {
        return $this->itemActionCollection->addDynamicItemPostAction($route, $labelKey, $icon, $messageKey, $routeParameters);
    }

    /**
     * @param array<string, string> $routeParameters
     */
    public function addDynamicItemGetAction(string $route, string $labelKey, string $icon, array $routeParameters = []): TableItemAction
    {
        return $this->itemActionCollection->addDynamicItemGetAction($route, $labelKey, $icon, $routeParameters);
    }

    public function getItemActions(): TableItemActionCollection
    {
        return $this->itemActionCollection;
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
     * @param array<mixed> $extraFrontendOption
     */
    public function setExtraFrontendOption(array $extraFrontendOption): TableAbstract
    {
        $this->extraFrontendOption = $extraFrontendOption;

        return $this;
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
        $options = [];

        if (null !== $columnIndex) {
            $options['order'] = [[$columnIndex, $this->orderDirection]];
        }

        if (null !== $this->ajaxUrl) {
            $options = \array_merge($options, [
                'processing' => true,
                'serverSide' => true,
                'ajax' => $this->ajaxUrl,
            ]);
        }

        $columnOptions = [];
        $columnTarget = 0;
        if ($this->supportsTableActions()) {
            $columnOptions[] = [
                'targets' => $columnTarget++,
            ];
        }

        foreach ($this->getColumns() as $column) {
            $columnOptions[] = \array_merge($column->getFrontendOptions(), ['targets' => $columnTarget++]);
        }
        $options['columnDefs'] = $columnOptions;

        $options = \array_merge($options, $this->extraFrontendOption);

        return $options;
    }

    public function getAjaxUrl(): ?string
    {
        return $this->ajaxUrl;
    }

    public function getOrderField(): ?string
    {
        foreach ($this->getColumns() as $column) {
            if ($column->getAttribute() === $this->orderField) {
                return $column->getOrderField();
            }
        }

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

    public function setSize(int $size): void
    {
        $this->size = $size;
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

    public function getExportSheetName(): string
    {
        return $this->exportSheetName;
    }

    public function setExportSheetName(string $exportSheetName): self
    {
        $this->exportSheetName = $exportSheetName;

        return $this;
    }

    public function getExportFileName(): string
    {
        return $this->exportFileName;
    }

    public function setExportFileName(string $exportFileName): self
    {
        $this->exportFileName = $exportFileName;

        return $this;
    }

    public function getExportDisposition(): string
    {
        return $this->exportDisposition;
    }

    public function setExportDisposition(string $exportDisposition): self
    {
        $this->exportDisposition = $exportDisposition;

        return $this;
    }

    public function getRowActionsClass(): string
    {
        return $this->rowActionsClass;
    }

    public function setRowActionsClass(string $rowActionsClass): void
    {
        $this->rowActionsClass = $rowActionsClass;
    }
}
