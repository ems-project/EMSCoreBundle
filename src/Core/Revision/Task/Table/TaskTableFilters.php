<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task\Table;

class TaskTableFilters
{
    /** @var string[] */
    public array $status = [];
    /** @var string[] */
    public array $assignee = [];
    /** @var string[] */
    public array $requester = [];

    /**
     * @return array<string, string[]>
     */
    public function all(): array
    {
        return \array_filter([
            TaskTableService::COL_STATUS => $this->status,
            TaskTableService::COL_ASSIGNEE => $this->assignee,
            TaskTableService::COL_REQUESTER => $this->requester,
        ]);
    }
}
