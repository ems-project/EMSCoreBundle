<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Repository\TaskRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;

final class TaskTableService implements EntityServiceInterface
{
    private TaskRepository $taskRepository;

    private const COL_ICON = 'icon';
    private const COL_TITLE = 'taskTitle';
    private const COL_DOCUMENT = 'label';
    private const COL_OWNER = 'owner';
    private const COL_ASSIGNEE = 'assignee';
    private const COL_STATUS = 'taskStatus';
    private const COL_DEADLINE = 'taskDeadline';
    private const COL_ACTIONS = 'actions';

    private const TEMPLATE = '@EMSCore/revision/task/columns.twig';

    public const COLUMNS = [
        self::COL_ICON => ['type' => 'block'],
        self::COL_TITLE => ['type' => 'block', 'column' => 't.title'],
        self::COL_DOCUMENT => ['column' => 'r.label'],
        self::COL_OWNER => ['type' => 'block', 'column' => 'r.owner'],
        self::COL_ASSIGNEE => ['type' => 'block', 'column' => 't.assignee'],
        self::COL_STATUS => ['column' => 't.status'],
        self::COL_DEADLINE => ['type' => 'block', 'column' => 't.deadline'],
        self::COL_ACTIONS => ['type' => 'block'],
    ];

    public function __construct(TaskRepository $taskRepository)
    {
        $this->taskRepository = $taskRepository;
    }

    public function buildTable(EntityTable $table, TaskTableContext $context): void
    {
        $columns = self::COLUMNS;
        if (TaskManager::TAB_USER === $context->tab) {
            unset($columns[self::COL_ASSIGNEE]);
        }
        if (TaskManager::TAB_OWNER === $context->tab) {
            unset($columns[self::COL_OWNER]);
        }

        foreach ($columns as $name => $options) {
            if (isset($options['column'])) {
                $context->addColumn($name, $options['column']);
            }

            $label = \sprintf('task.dashboard.column.%s', $name);
            $type = $options['type'] ?? null;

            if ('block' === $type) {
                $orderField = isset($options['column']) ? $name : null;
                $def = new TemplateBlockTableColumn($label, $name, self::TEMPLATE, $orderField);
                $table->addColumnDefinition($def)->setCellClass('col-'.$name);
            } else {
                $table->addColumn($label, $name);
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
}
