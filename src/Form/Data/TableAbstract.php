<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use EMS\CommonBundle\Common\Spreadsheet\SpreadsheetValidation;
use EMS\CoreBundle\Helper\DataTableRequest;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Translation\TranslatableMessage;

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
    private readonly TableItemActionCollection $itemActionCollection;
    /** @var TableAction[] */
    private array $tableActions = [];
    /** @var TableAction[] */
    private array $toolbarActions = [];
    /** @var TableAction[] */
    private array $massActions = [];
    private ?string $orderField = null;
    private string $orderDirection = 'asc';
    private string $searchValue = '';
    /** @var array<mixed> */
    private array $extraFrontendOption = [];

    /** @var array<string, string> */
    private array $exportUrls = [];
    /** @var ?FormInterface<mixed> */
    private ?FormInterface $filterForm = null;

    private string $exportSheetName = 'table';
    private string $exportFileName = 'table';
    private string $exportDisposition = 'attachment';
    private string $labelAttribute = 'name';
    private string $rowActionsClass = '';

    public function __construct(private readonly ?string $ajaxUrl, private int $from, private int $size)
    {
        $this->itemActionCollection = new TableItemActionCollection();
    }

    #[\Override]
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

    #[\Override]
    public function next(int $pagingSize = 100): bool
    {
        if ($this->from + $this->size >= $this->count()) {
            return false;
        }
        $this->from = $this->from + $this->size;
        $this->size = $pagingSize;

        return true;
    }

    #[\Override]
    public function getLabelAttribute(): string
    {
        return $this->labelAttribute;
    }

    public function setLabelAttribute(string $labelAttribute): self
    {
        $this->labelAttribute = $labelAttribute;

        return $this;
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function getSelected(): array
    {
        return $this->selected;
    }

    /**
     * @param string[] $selected
     */
    #[\Override]
    public function setSelected(array $selected): void
    {
        $this->selected = $selected;
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function getReordered(): array
    {
        return $this->reordered;
    }

    /**
     * @param string[] $reordered
     */
    #[\Override]
    public function setReordered(array $reordered): void
    {
        $this->reordered = $reordered;
    }

    public function getColumnByName(string $name): ?TableColumn
    {
        return $this->columns[$name] ?? null;
    }

    public function addColumn(string|TranslatableMessage $titleKey, string $attribute, ?string $name = null): TableColumn
    {
        $column = new TableColumn($titleKey, $attribute);

        if ($name) {
            $this->columns[$name] = $column;
        } else {
            $this->columns[] = $column;
        }

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
    #[\Override]
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return array<SpreadsheetValidation|null>
     */
    public function getColumnsValidations(): array
    {
        $validations = [];
        foreach ($this->columns as $index => $col) {
            $validations[$index] = $col->getValidation();
        }

        return $validations;
    }

    public function addItemActionCollection(string|TranslatableMessage|null $labelKey = null, ?string $icon = null): TableItemActionCollection
    {
        $itemActionCollection = new TableItemActionCollection($labelKey, $icon);
        $this->itemActionCollection->addItemActionCollection($itemActionCollection);

        return $itemActionCollection;
    }

    /**
     * @param array<mixed> $routeParameters
     */
    public function addItemGetAction(string $route, string|TranslatableMessage $labelKey, string $icon, array $routeParameters = []): TableItemAction
    {
        return $this->itemActionCollection->addItemGetAction($route, $labelKey, $icon, $routeParameters);
    }

    /**
     * @param array<string, mixed> $routeParameters
     */
    public function addItemPostAction(string $route, string|TranslatableMessage $labelKey, string $icon, string|TranslatableMessage $messageKey, array $routeParameters = []): TableItemAction
    {
        return $this->itemActionCollection->addItemPostAction($route, $labelKey, $icon, $messageKey, $routeParameters);
    }

    /**
     * @param array<string, string|int> $routeParameters
     */
    public function addDynamicItemPostAction(string $route, string|TranslatableMessage $labelKey, string $icon, string|TranslatableMessage|null $messageKey = null, array $routeParameters = []): TableItemAction
    {
        return $this->itemActionCollection->addDynamicItemPostAction($route, $labelKey, $icon, $messageKey, $routeParameters);
    }

    /**
     * @param array<string, string> $routeParameters
     */
    public function addDynamicItemGetAction(string $route, string|TranslatableMessage $labelKey, string $icon, array $routeParameters = []): TableItemAction
    {
        return $this->itemActionCollection->addDynamicItemGetAction($route, $labelKey, $icon, $routeParameters);
    }

    #[\Override]
    public function getItemActions(): TableItemActionCollection
    {
        return $this->itemActionCollection;
    }

    public function addTableAction(string $name, string $icon, string|TranslatableMessage $labelKey, string|TranslatableMessage|null $confirmationKey = null): TableAction
    {
        $action = TableAction::create($name, $icon, $labelKey, $confirmationKey);
        $this->tableActions[] = $action;

        return $action;
    }

    /**
     * @param array<string, string> $routeParams
     */
    public function addToolbarAction(TranslatableMessage $label, string $icon, string $routeName, array $routeParams = []): TableAction
    {
        $toolbarAction = TableAction::create($label->getMessage(), $icon, $label);
        $toolbarAction->setRoute($routeName, $routeParams);
        $toolbarAction->setCssClass('btn btn-sm btn-primary');

        $this->toolbarActions[] = $toolbarAction;

        return $toolbarAction;
    }

    public function addMassAction(string $name, TranslatableMessage|string $label, string $icon, string|TranslatableMessage|null $confirmationKey = null): TableAction
    {
        $massAction = TableAction::create($name, $icon, $label, $confirmationKey);
        $massAction->setCssClass('btn btn-sm btn-outline-danger');

        $this->massActions[] = $massAction;

        return $massAction;
    }

    #[\Override]
    public function getTableMassActions(): iterable
    {
        return $this->massActions;
    }

    /**
     * @return TableAction[]
     */
    #[\Override]
    public function getTableActions(): iterable
    {
        return $this->tableActions;
    }

    /**
     * @return TableAction[]
     */
    public function getToolbarActions(): array
    {
        return $this->toolbarActions;
    }

    public function setDefaultOrder(string $orderField, string $direction = 'asc'): self
    {
        $this->orderField = $orderField;
        $this->orderDirection = $direction;

        return $this;
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
    #[\Override]
    public function getFrontendOptions(): array
    {
        $columnIndex = 0;
        if ($this->supportsTableActions()) {
            $columnIndex = 1;
        }
        if (null !== $this->orderField && !$this->isSortable()) {
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

        if (null !== $this->orderField) {
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

        if ($this->itemActionCollection->count() > 0) {
            $columnOptions[] = [
                'cellType' => 'td',
                'className' => '',
                'targets' => $columnTarget,
                'orderable' => false,
                'searchable' => false,
            ];
        }

        $options['columnDefs'] = $columnOptions;

        $options = \array_merge($options, $this->extraFrontendOption);

        return $options;
    }

    #[\Override]
    public function getAjaxUrl(): ?string
    {
        return $this->ajaxUrl;
    }

    /**
     * @return ?FormInterface<mixed>
     */
    public function getFilterForm(): ?FormInterface
    {
        return $this->filterForm;
    }

    /**
     * @param ?FormInterface<mixed> $filterForm
     */
    public function setFilterForm(?FormInterface $filterForm): self
    {
        $this->filterForm = $filterForm;

        return $this;
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

    #[\Override]
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

    #[\Override]
    abstract public function supportsTableActions(): bool;

    abstract public function totalCount(): int;

    /**
     * @return array<string, string>
     */
    public function getExportUrls(): array
    {
        return $this->exportUrls;
    }

    public function addExportUrl(string $exportFormat, string $exportUrl): void
    {
        $this->exportUrls[$exportFormat] = $exportUrl;
    }

    #[\Override]
    public function getExportSheetName(): string
    {
        return $this->exportSheetName;
    }

    public function setExportSheetName(string $exportSheetName): self
    {
        $this->exportSheetName = $exportSheetName;

        return $this;
    }

    #[\Override]
    public function getExportFileName(): string
    {
        return $this->exportFileName;
    }

    public function setExportFileName(string $exportFileName): self
    {
        $this->exportFileName = $exportFileName;

        return $this;
    }

    #[\Override]
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

    public function setRowActionsClass(string $rowActionsClass): self
    {
        $this->rowActionsClass = $rowActionsClass;

        return $this;
    }
}
