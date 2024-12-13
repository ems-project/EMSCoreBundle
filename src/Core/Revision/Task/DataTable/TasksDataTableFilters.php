<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task\DataTable;

use EMS\CoreBundle\Core\Revision\Task\TaskStatus;
use EMS\CoreBundle\Entity\Revision;

class TasksDataTableFilters
{
    /** @var string[] */
    public array $status = [];
    /** @var string[] */
    public array $assignee = [];
    /** @var string[] */
    public array $requester = [];
    /** @var array<int, string|null> */
    public array $versionNextTag = [];

    public function __construct()
    {
        $this->status = [
            TaskStatus::PROGRESS->value,
            TaskStatus::REJECTED->value,
            TaskStatus::COMPLETED->value,
        ];
    }

    /**
     * @return array<int, string|null>
     */
    public function getVersionNextTag(): array
    {
        if (\in_array(null, $this->versionNextTag, true)) {
            return [Revision::VERSION_BLANK, ...$this->versionNextTag];
        }

        return $this->versionNextTag;
    }
}
