<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task\Table;

use EMS\CoreBundle\Entity\UserInterface;

final class TaskTableContext
{
    /** @var array<string, string> */
    public array $columns = [];
    public bool $showVersionTagColumn = false;

    public function __construct(public UserInterface $user, public string $tab, public TaskTableFilters $filters)
    {
    }

    public function addColumn(string $name, string $column): void
    {
        $this->columns[$name] = $column;
    }
}
