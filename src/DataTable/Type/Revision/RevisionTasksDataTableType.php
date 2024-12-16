<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Revision;

use EMS\CoreBundle\Core\DataTable\DataTableFormat;
use EMS\CoreBundle\Core\DataTable\Type\AbstractQueryTableType;
use EMS\CoreBundle\Core\DataTable\Type\DataTableFilterFormInterface;
use EMS\CoreBundle\Core\Revision\Task\DataTable\TasksDataTableContext;
use EMS\CoreBundle\Core\Revision\Task\DataTable\TasksDataTableQueryService;
use EMS\CoreBundle\Form\Data\DateTableColumn;
use EMS\CoreBundle\Form\Data\QueryTable;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Form\Revision\Task\RevisionTaskFiltersType;
use EMS\CoreBundle\Repository\TaskRepository;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RevisionTasksDataTableType extends AbstractQueryTableType implements DataTableFilterFormInterface
{
    public const LOAD_MAX_ROWS = 1000;

    /**
     * @return array<string, array{'order_field'?: string, 'cellRender'?: bool }>
     */
    public const COLUMNS = [
        'title' => 'task_title',
        'label' => 'revision_label',
        'version_next_tag' => 'revision_version_next_tag',
        'requester' => 'task_requester',
        'assignee' => 'task_assignee',
        'status' => 'task_status',
        'deadline' => 'task_deadline',
        'modified' => 'task_modified',
        'actions' => '',
    ];

    public function __construct(
        TasksDataTableQueryService $queryService,
        private readonly TaskRepository $taskRepository,
    ) {
        parent::__construct($queryService);
    }

    public function build(QueryTable $table): void
    {
        /** @var TasksDataTableContext $context */
        $context = $table->getContext();

        $table->setIdField('task_id');
        $table->setDefaultOrder('task_modified', 'DESC');

        match ($this->format) {
            DataTableFormat::TABLE => $this->buildTable($table, $context),
            DataTableFormat::EXCEL, DataTableFormat::CSV => $this->buildExport($table, $context),
        };
    }

    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver
            ->setRequired(['tab'])
            ->setAllowedValues('tab', [
                TasksDataTableContext::TAB_USER,
                TasksDataTableContext::TAB_REQUESTER,
                TasksDataTableContext::TAB_MANAGER,
            ]);
    }

    public function getExportFormats(): array
    {
        return [DataTableFormat::CSV, DataTableFormat::EXCEL];
    }

    /**
     * @param TasksDataTableContext $context
     */
    public function filterFormAddToContext(FormInterface $filterForm, mixed $context): mixed
    {
        if ($filterForm->isSubmitted()) {
            $context->filters = $filterForm->getData();
        }

        return $context;
    }

    /**
     * @param TasksDataTableContext $context
     */
    public function filterFormBuild(FormFactoryInterface $formFactory, mixed $context): FormInterface
    {
        return $formFactory->create(
            type: RevisionTaskFiltersType::class,
            data: $context->filters,
            options: ['tab' => $context->tab]
        );
    }

    public function getContext(array $options): TasksDataTableContext
    {
        return new TasksDataTableContext(
            tab: $options['tab'],
            showVersionTagColumn: $this->taskRepository->hasVersionedContentType()
        );
    }

    public function getLoadMaxRows(): int
    {
        return self::LOAD_MAX_ROWS;
    }

    public function getQueryName(): string
    {
        return 'revision_tasks';
    }

    private function buildExport(QueryTable $table, TasksDataTableContext $context): void
    {
        $table->setExportFileName('tasks');

        foreach ($this->getColumns($context) as [$name, $field, $label]) {
            if (\in_array($name, ['deadline', 'modified'])) {
                $table->addColumnDefinition(new DateTableColumn($label, $field));
            } else {
                $table->addColumn($label, $field);
            }
        }
    }

    private function buildTable(QueryTable $table, TasksDataTableContext $context): void
    {
        $columnTemplate = "@$table->templateNamespace/revision/task/columns.twig";

        foreach ($this->getColumns($context) as [$name, $field, $label]) {
            $def = new TemplateBlockTableColumn(
                label: $label,
                blockName: $name,
                template: $columnTemplate,
                orderField: !\in_array($name, ['label', 'actions']) ? $field : null
            );
            $def->setCellRender(!\in_array($name, ['deadline', 'modified']));
            $table->addColumnDefinition($def)->setCellClass('col-'.$name);
        }
    }

    /**
     * @return \Generator<string[]>
     */
    private function getColumns(TasksDataTableContext $context): \Generator
    {
        $columns = self::COLUMNS;

        if (TasksDataTableContext::TAB_USER === $context->tab) {
            unset($columns['assignee']);
        }
        if (TasksDataTableContext::TAB_REQUESTER === $context->tab) {
            unset($columns['requester']);
        }
        if (!$context->showVersionTagColumn) {
            unset($columns['version_next_tag']);
        }
        if (DataTableFormat::TABLE !== $this->format) {
            unset($columns['actions']);
        }

        foreach ($columns as $name => $field) {
            yield [$name, $field, \sprintf('task.dashboard.column.%s', $name)];
        }
    }
}
