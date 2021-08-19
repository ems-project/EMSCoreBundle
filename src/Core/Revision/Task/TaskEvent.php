<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Task;
use EMS\CoreBundle\Entity\UserInterface;
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
    public UserInterface $user;
    public ?string $comment;
    /** @var array<mixed> */
    public array $changeSet = [];

    public function __construct(Task $task, Revision $revision, UserInterface $user)
    {
        $this->task = $task;
        $this->revision = $revision;
        $this->user = $user;
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
