<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Form\Data\DateTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Repository\TaskRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;

final class TaskTableService implements EntityServiceInterface
{
    private TaskRepository $taskRepository;

    private const COL_TITLE = 'title';
    private const COL_DOCUMENT = 'label';
    private const COL_DOCUMENT_VERSION = 'version';
    private const COL_OWNER = 'owner';
    private const COL_ASSIGNEE = 'assignee';
    private const COL_STATUS = 'status';
    private const COL_DEADLINE = 'deadline';
    private const COL_MODIFIED = 'modified';
    private const COL_ACTIONS = 'actions';

    private const TEMPLATE = '@EMSCore/revision/task/columns.twig';

    public const COLUMNS = [
        self::COL_TITLE => ['type' => 'block', 'column' => 'taskTitle', 'mapping' => 't.title'],
        self::COL_DOCUMENT => ['column' => 'label', 'mapping' => 'r.labelField'],
        self::COL_DOCUMENT_VERSION => ['type' => 'block', 'column' => 'versionTag', 'mapping' => 'r.versionTag'],
        self::COL_OWNER => ['type' => 'block', 'column' => 'owner', 'mapping' => 'r.owner'],
        self::COL_ASSIGNEE => ['type' => 'block', 'column' => 'taskAssignee', 'mapping' => 't.assignee'],
        self::COL_STATUS => ['type' => 'block', 'column' => 'taskStatus', 'mapping' => 't.status'],
        self::COL_DEADLINE => ['type' => 'block', 'column' => 'taskDeadline', 'mapping' => 't.deadline'],
        self::COL_MODIFIED => ['type' => 'block', 'column' => 'taskModified', 'mapping' => 't.modified'],
        self::COL_ACTIONS => ['type' => 'block'],
    ];

    public function __construct(TaskRepository $taskRepository)
    {
        $this->taskRepository = $taskRepository;
    }

    public function buildTable(EntityTable $table, TaskTableContext $context): void
    {
        /** @var array<string, array{type: ?string, column: ?string, label: string, mapping?: string}> $columns */
        $columns = $this->getColumns($context);

        foreach ($columns as $name => $options) {
            $orderField = self::COL_ACTIONS !== $name ? $name : null;

            if (isset($options['mapping'])) {
                $context->addColumn($name, $options['mapping']);
            }

            $type = $options['type'] ?? null;

            if ('block' === $type) {
                $def = new TemplateBlockTableColumn($options['label'], $name, self::TEMPLATE, $orderField);
                $def->setCellRender(!\in_array($name, [self::COL_DEADLINE, self::COL_MODIFIED]));
                $table->addColumnDefinition($def)->setCellClass('col-'.$name);
            } else {
                $table->addColumn($options['label'], $name);
            }
        }

        $table->setDefaultOrder(self::COL_MODIFIED, 'DESC');
    }

    public function buildTableExport(EntityTable $table, TaskTableContext $context): void
    {
        $columns = $this->getColumns($context);
        unset($columns[self::COL_ACTIONS]);

        foreach ($columns as $name => $options) {
            if (\in_array($name, [self::COL_DEADLINE, self::COL_MODIFIED])) {
                $table->addColumnDefinition(new DateTableColumn($options['label'], $options['column']));
            } else {
                $table->addColumn($options['label'], $options['column']);
            }
        }

        $table->setDefaultOrder(self::COL_MODIFIED, 'DESC');
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

    /**
     * @return string[]
     */
    public function getAliasesName(): array
    {
        return [];
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
    private function getColumns(TaskTableContext $context): array
    {
        $columns = self::COLUMNS;
        if (TaskManager::TAB_USER === $context->tab) {
            unset($columns[self::COL_ASSIGNEE]);
        }
        if (TaskManager::TAB_OWNER === $context->tab) {
            unset($columns[self::COL_OWNER]);
        }
        if (!$context->showVersionTagColumn) {
            unset($columns[self::COL_DOCUMENT_VERSION]);
        }

        foreach ($columns as $name => &$column) {
            $column['label'] = \sprintf('task.dashboard.column.%s', $name);
        }

        return $columns;
    }

    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->taskRepository->findTaskById($name);
    }

    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        throw new \RuntimeException('updateEntityFromJson method not yet implemented');
    }

    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        throw new \RuntimeException('createEntityFromJson method not yet implemented');
    }

    public function deleteByItemName(string $name): string
    {
        throw new \RuntimeException('deleteByItemName method not yet implemented');
    }
}
