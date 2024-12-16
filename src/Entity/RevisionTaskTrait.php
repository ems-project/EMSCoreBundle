<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CoreBundle\Core\Revision\Task\TaskStatus;

trait RevisionTaskTrait
{
    private ?Task $taskCurrent = null;

    /** @var string[]|null */
    private ?array $taskPlannedIds = [];

    /** @var string[]|null */
    private ?array $taskApprovedIds = [];

    public function tasksEnabled(): bool
    {
        return $this->isCurrent() && $this->giveContentType()->tasksEnabled();
    }

    public function addTask(Task $task): void
    {
        if (null === $this->taskCurrent) {
            $this->taskCurrent = $task;
        } elseif ($task->isStatus(TaskStatus::PLANNED)) {
            $this->taskPlannedIds[] = $task->getId();
        } elseif ($task->isStatus(TaskStatus::APPROVED)) {
            $this->taskApprovedIds[] = $task->getId();
        }
    }

    public function hasTask(Task $task): bool
    {
        return match (true) {
            $this->taskCurrent?->getId() === $task->getId() => true,
            \in_array($task->getId(), $this->getTaskPlannedIds(), true) => true,
            \in_array($task->getId(), $this->getTaskApprovedIds(), true) => true,
            default => false,
        };
    }

    public function taskCurrentReplace(Task $newTaskCurrent): bool
    {
        if ($this->hasTaskCurrent() && $newTaskCurrent->getId() === $this->getTaskCurrent()->getId()) {
            return false;
        }

        $this->addTask($this->getTaskCurrent());
        $this->taskCurrent = $newTaskCurrent;
        $this->deleteTaskPlanned($newTaskCurrent);

        return true;
    }

    public function tasksClear(): void
    {
        $this->taskCurrent = null;
        $this->taskPlannedIds = [];
        $this->taskApprovedIds = [];
    }

    public function tasksRollback(Revision $revision): void
    {
        $this->taskCurrent = $revision->taskCurrent;
        $this->taskPlannedIds = $revision->taskPlannedIds;
        $this->taskApprovedIds = $revision->taskApprovedIds;
    }

    public function deleteTaskPlanned(Task $task): void
    {
        $this->taskPlannedIds = \array_values(\array_diff($this->getTaskPlannedIds(), [$task->getId()]));
    }

    public function deleteTaskApproved(Task $task): void
    {
        $this->taskApprovedIds = \array_values(\array_diff($this->getTaskApprovedIds(), [$task->getId()]));
    }

    /**
     * @return string[]
     */
    public function getTaskApprovedIds(): array
    {
        return $this->taskApprovedIds ?? [];
    }

    /**
     * @return string[]
     */
    public function getTaskPlannedIds(): array
    {
        return $this->taskPlannedIds ?? [];
    }

    public function getTaskDeadline(): ?\DateTimeInterface
    {
        return $this->getTaskCurrent()->hasDeadline() ? $this->getTaskCurrent()->getDeadline() : null;
    }

    public function getTaskModified(): \DateTimeInterface
    {
        return $this->getTaskCurrent()->getModified();
    }

    public function getTaskCurrent(): Task
    {
        if (null === $this->taskCurrent) {
            throw new \RuntimeException('Revision has no current task');
        }

        return $this->taskCurrent;
    }

    public function getTaskAssignee(): string
    {
        return $this->getTaskCurrent()->getAssignee();
    }

    public function getTaskStatus(): string
    {
        return $this->getTaskCurrent()->getStatus();
    }

    public function getTaskTitle(): string
    {
        return $this->getTaskCurrent()->getTitle();
    }

    public function getTaskNextPlannedId(): ?string
    {
        if (!$this->hasTaskPlannedIds()) {
            return null;
        }

        $taskPlannedIds = $this->getTaskPlannedIds();

        return \array_shift($taskPlannedIds);
    }

    public function hasTasks(bool $includeApproved = true): bool
    {
        return $this->hasTaskCurrent() || $this->hasTaskPlannedIds() || ($includeApproved && $this->hasTaskApprovedIds());
    }

    public function hasTaskCurrent(): bool
    {
        return null !== $this->taskCurrent;
    }

    public function hasTaskPlannedIds(): bool
    {
        return \count($this->taskPlannedIds ?? []) > 0;
    }

    public function hasTaskApprovedIds(): bool
    {
        return \count($this->taskApprovedIds ?? []) > 0;
    }

    public function isTaskCurrent(Task $task): bool
    {
        return $this->taskCurrent === $task;
    }

    public function isTaskPlanned(Task $task): bool
    {
        return \in_array($task->getId(), $this->getTaskPlannedIds(), true);
    }

    public function isTaskApproved(Task $task): bool
    {
        return \in_array($task->getId(), $this->getTaskApprovedIds(), true);
    }

    public function setTaskCurrent(?Task $task): void
    {
        $this->taskCurrent = $task;

        if ($task) {
            $this->deleteTaskPlanned($task);
        }
    }

    /**
     * @param Task[] $taskPlanned
     */
    public function setTaskPlanned(array $taskPlanned): void
    {
        $this->taskPlannedIds = \array_values(\array_map(fn (Task $task) => $task->getId(), $taskPlanned));
    }
}
