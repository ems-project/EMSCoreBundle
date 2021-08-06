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

        if ($revision->hasTaskCurrent() && $currentTask = $results->get($revision->getTaskCurrent()->getId())) {
            $this->tasks[] = $currentTask;
        }

        foreach ($revision->getTaskPlannedIds() as $plannedId) {
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
