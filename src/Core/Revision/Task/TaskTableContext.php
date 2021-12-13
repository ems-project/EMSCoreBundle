<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CoreBundle\Entity\UserInterface;

final class TaskTableContext
{
    public UserInterface $user;
    public string $tab;
    /** @var array<string, string> */
    public array $columns = [];
    public bool $showVersionTagColumn = false;

    public function __construct(UserInterface $user, string $tab)
    {
        $this->user = $user;
        $this->tab = $tab;
    }

    public function addColumn(string $name, string $column): void
    {
        $this->columns[$name] = $column;
    }
}
