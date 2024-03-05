<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task\DataTable;

use EMS\CoreBundle\Core\Revision\Task\TaskStatus;

class TasksDataTableFilters
{
    /** @var string[] */
    public array $status = [];
    /** @var string[] */
    public array $assignee = [];
    /** @var string[] */
    public array $requester = [];
    /** @var array<string, string|null> */
    public array $versionNextTag = [];

    public function __construct()
    {
        $this->status = [
            TaskStatus::PROGRESS->value,
            TaskStatus::REJECTED->value,
            TaskStatus::COMPLETED->value,
        ];
    }
}
