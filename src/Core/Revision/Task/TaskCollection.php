<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use Doctrine\Common\Collections\ArrayCollection;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Task;
use EMS\CoreBundle\Entity\UserInterface;

final class TaskCollection
{
    private Revision $revision;
    /** @var Task[] */
    private array $tasks = [];
    private bool $isOwner;

    /**
     * @param ArrayCollection<string, Task> $results
     */
    public function __construct(UserInterface $user, Revision $revision, ArrayCollection $results)
    {
        $this->revision = $revision;
        $this->isOwner = $user->getUsername() === $revision->getOwner();

        if ($revision->hasTaskCurrent() && $currentTask = $results->get($revision->getTaskCurrent()->getId())) {
            $this->tasks[] = $currentTask;
        }

        foreach ($revision->getTaskPlannedIds() as $plannedId) {
            if (null !== $plannedTask = $results->get($plannedId)) {
                $this->tasks[] = $plannedTask;
            }
        }
    }

    public function isOwner(): bool
    {
        return $this->isOwner;
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
