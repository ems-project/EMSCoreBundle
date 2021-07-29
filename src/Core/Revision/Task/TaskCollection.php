<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use Doctrine\Common\Collections\ArrayCollection;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Task;

final class TaskCollection
{
    private Revision $revision;
    /** @var Task[] */
    private array $tasks = [];

    /**
     * @param ArrayCollection<string, Task> $results
     */
    public function __construct(Revision $revision, ArrayCollection $results)
    {
        $this->revision = $revision;
        $revisionTasks = $revision->getTasks();

        if ($revisionTasks->hasCurrentId() && $currentTask = $results->get($revisionTasks->getCurrentId())) {
            $this->tasks[] = $currentTask;
        }

        foreach ($revisionTasks->getPlannedIds() as $plannedId) {
            if (null !== $plannedTask = $results->get($plannedId)) {
                $this->tasks[] = $plannedTask;
            }
        }
    }

    public function getRevision(): Revision
    {
        return $this->revision;
    }

    /**
     * @return Task[]
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }
}
