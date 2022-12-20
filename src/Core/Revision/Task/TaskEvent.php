<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Task;
use Symfony\Contracts\EventDispatcher\Event;

class TaskEvent extends Event
{
    final public const PLANNED = 'ems_core.event.task.planned';
    final public const PROGRESS = 'ems_core.event.task.progress';
    final public const COMPLETED = 'ems_core.event.task.completed';
    final public const APPROVED = 'ems_core.event.task.approved';
    final public const REJECTED = 'ems_core.event.task.rejected';

    final public const CREATE = 'ems_core.event.task.create';
    final public const UPDATE = 'ems_core.event.task.update';
    final public const DELETE = 'ems_core.event.task.delete';
    public ?string $comment = null;
    /** @var array<mixed> */
    public array $changeSet = [];

    public function __construct(public Task $task, public Revision $revision, public string $username)
    {
    }

    public function isTaskCurrent(): bool
    {
        return $this->revision->isTaskCurrent($this->task);
    }

    public function isAssigneeIsRequester(): bool
    {
        return $this->task->getCreatedBy() === $this->task->getAssignee();
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
