<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task\DataTable;

class TasksDataTableContext
{
    public TasksDataTableFilters $filters;

    public const TAB_USER = 'user';
    public const TAB_REQUESTER = 'requester';
    public const TAB_MANAGER = 'manager';

    public function __construct(
        public readonly string $tab,
        public bool $showVersionTagColumn,
    ) {
        $this->filters = new TasksDataTableFilters();
    }
}
