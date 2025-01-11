<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Task;
use Symfony\Contracts\EventDispatcher\Event;

class TaskEvent extends Event
{
    final public const string PLANNED = 'ems_core.event.task.planned';
    final public const string PROGRESS = 'ems_core.event.task.progress';
    final public const string COMPLETED = 'ems_core.event.task.completed';
    final public const string APPROVED = 'ems_core.event.task.approved';
    final public const string REJECTED = 'ems_core.event.task.rejected';

    final public const string CREATE = 'ems_core.event.task.create';
    final public const string UPDATE = 'ems_core.event.task.update';
    final public const string DELETE = 'ems_core.event.task.delete';
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
