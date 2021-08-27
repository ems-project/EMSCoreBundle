<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CoreBundle\Form\Data\DateTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Repository\TaskRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;

final class TaskTableService implements EntityServiceInterface
{
    private TaskRepository $taskRepository;

    private const COL_ICON = 'icon';
    private const COL_TITLE = 'title';
    private const COL_DOCUMENT = 'label';
    private const COL_OWNER = 'owner';
    private const COL_ASSIGNEE = 'assignee';
    private const COL_STATUS = 'status';
    private const COL_DEADLINE = 'deadline';
    private const COL_ACTIONS = 'actions';

    private const TEMPLATE = '@EMSCore/revision/task/columns.twig';

    public const COLUMNS = [
        self::COL_ICON => ['type' => 'block', 'mapping' => 't.status'],
        self::COL_TITLE => ['type' => 'block', 'column' => 'taskTitle', 'mapping' => 't.title'],
        self::COL_DOCUMENT => ['column' => 'label', 'mapping' => 'r.labelField'],
        self::COL_OWNER => ['type' => 'block', 'column' => 'owner', 'mapping' => 'r.owner'],
        self::COL_ASSIGNEE => ['type' => 'block', 'column' => 'taskAssignee', 'mapping' => 't.assignee'],
        self::COL_STATUS => ['type' => 'block', 'column' => 'taskStatus', 'mapping' => 't.status'],
        self::COL_DEADLINE => ['type' => 'block', 'column' => 'taskDeadline', 'mapping' => 't.deadline'],
        self::COL_ACTIONS => ['type' => 'block'],
    ];

    public function __construct(TaskRepository $taskRepository)
    {
        $this->taskRepository = $taskRepository;
    }

    public function buildTable(EntityTable $table, TaskTableContext $context): void
    {
        /** @var array<string, array{type: ?string, column: ?string, label: string}> $columns */
        $columns = $this->getColumns($context->tab);

        foreach ($columns as $name => $options) {
            $orderField = self::COL_ACTIONS !== $name ? $name : null;

            if (isset($options['mapping'])) {
                $context->addColumn($name, $options['mapping']);
            }

            $type = $options['type'] ?? null;

            if ('block' === $type) {
                $def = new TemplateBlockTableColumn($options['label'], $name, self::TEMPLATE, $orderField);
                $table->addColumnDefinition($def)->setCellClass('col-'.$name);
            } else {
                $table->addColumn($options['label'], $name);
            }
        }
    }

    public function buildTableExport(EntityTable $table, TaskTableContext $context): void
    {
        $columns = $this->getColumns($context->tab);
        unset($columns[self::COL_ICON]);
        unset($columns[self::COL_ACTIONS]);

        foreach ($columns as $name => $options) {
            if (self::COL_DEADLINE === $name) {
                $table->addColumnDefinition(new DateTableColumn($options['label'], $options['column']));
            } else {
                $table->addColumn($options['label'], $options['column']);
            }
        }
    }

    public function isSortable(): bool
    {
        return false;
    }

    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        if (!$context instanceof TaskTableContext) {
            throw new \RuntimeException('Invalid context');
        }

        return $this->taskRepository->findTable($from, $size, $orderField, $orderDirection, $searchValue, $context);
    }

    public function getEntityName(): string
    {
        return 'task';
    }

    public function count(string $searchValue = '', $context = null): int
    {
        if (!$context instanceof TaskTableContext) {
            throw new \RuntimeException('Invalid context');
        }

        return $this->taskRepository->countTable($searchValue, $context);
    }

    /**
     * @return array<mixed>
     */
    private function getColumns(string $tab): array
    {
        $columns = self::COLUMNS;
        if (TaskManager::TAB_USER === $tab) {
            unset($columns[self::COL_ASSIGNEE]);
        }
        if (TaskManager::TAB_OWNER === $tab) {
            unset($columns[self::COL_OWNER]);
        }

        foreach ($columns as $name => &$column) {
            if (self::COL_ICON === $name) {
                $column['label'] = '';
            } else {
                $column['label'] = \sprintf('task.dashboard.column.%s', $name);
            }
        }

        return $columns;
    }
}