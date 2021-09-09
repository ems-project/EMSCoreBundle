<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Task;
use Symfony\Contracts\EventDispatcher\Event;

class TaskEvent extends Event
{
    public const PLANNED = 'ems_core.event.task.planned';
    public const PROGRESS = 'ems_core.event.task.progress';
    public const COMPLETED = 'ems_core.event.task.completed';
    public const APPROVED = 'ems_core.event.task.approved';
    public const REJECTED = 'ems_core.event.task.rejected';

    public const CREATE = 'ems_core.event.task.create';
    public const UPDATE = 'ems_core.event.task.update';
    public const DELETE = 'ems_core.event.task.delete';

    public Task $task;
    public Revision $revision;
    public string $username;
    public ?string $comment = null;
    /** @var array<mixed> */
    public array $changeSet = [];

    public function __construct(Task $task, Revision $revision, string $username)
    {
        $this->task = $task;
        $this->revision = $revision;
        $this->username = $username;
    }

    public function isTaskCurrent(): bool
    {
        return $this->revision->isTaskCurrent($this->task);
    }

    public function isAssigneeIsOwner(): bool
    {
        return $this->revision->hasOwner() && $this->revision->getOwner() === $this->task->getAssignee();
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    /**
     * @param array<mixed> $changeSet
     */
    public function setChangeSet(array $changeSet): void
    {
        $this->changeSet = $changeSet;
    }
}
