<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

trait RevisionTaskTrait
{
    /**
     * @ORM\ManyToOne(targetEntity="EMS\CoreBundle\Entity\Task")
     * @ORM\JoinColumn(name="task_current_id", referencedColumnName="id", nullable=true)
     */
    private ?Task $taskCurrent;

    /**
     * @var string[]|null
     * @ORM\Column(name="task_planned_ids", type="json", nullable=true)
     */
    private ?array $taskPlannedIds = [];

    /**
     * @var string[]|null
     * @ORM\Column(name="task_approved_ids", type="json", nullable=true)
     */
    private ?array $taskApprovedIds = [];

    /**
     * @ORM\Column(name="owner", type="text", nullable=true)
     */
    private ?string $owner = null;

    public function addTask(Task $task, UserInterface $user): void
    {
        if (null === $this->owner) {
            $this->owner = $user->getUsername();
        }

        if ($this->owner !== $user->getUsername()) {
            throw new \RuntimeException(\sprintf('User %s is not the owner!', $user->getUsername()));
        }

        if (null === $this->taskCurrent) {
            $this->taskCurrent = $task;
        } else {
            $this->taskPlannedIds[] = $task->getId();
        }
    }

    public function clearTasks(): void
    {
        $this->taskCurrent = null;
        $this->taskPlannedIds = [];
        $this->taskApprovedIds = [];
    }

    public function deleteTaskPlanned(Task $task): void
    {
        $this->taskPlannedIds = \array_diff($this->getTaskPlannedIds(), [$task->getId()]);
    }

    public function deleteTaskApproved(Task $task): void
    {
        $this->taskApprovedIds = \array_diff($this->getTaskApprovedIds(), [$task->getId()]);
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

    public function getTaskDeadline(): \DateTimeInterface
    {
        return $this->getTaskCurrent()->getDeadline();
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

    public function getOwner(): string
    {
        if (null === $owner = $this->owner) {
            throw new \RuntimeException('Revision has no owner');
        }

        return $owner;
    }

    public function getTaskNextPlannedId(): ?string
    {
        if (!$this->hasTaskPlannedIds()) {
            return null;
        }

        $taskPlannedIds = $this->getTaskPlannedIds();
        $nextPlannedId = \array_shift($taskPlannedIds);
        $this->taskPlannedIds = $taskPlannedIds;

        return $nextPlannedId;
    }

    public function hasTasks(): bool
    {
        return $this->hasTaskCurrent() || $this->hasTaskPlannedIds() || $this->hasTaskApprovedIds();
    }

    public function hasOwner(): bool
    {
        return null !== $this->owner;
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

    public function isOwnershipAllowed(): bool
    {
        return $this->giveContentType()->hasOwnerRole();
    }

    public function setTaskCurrent(?Task $task): void
    {
        $this->taskCurrent = $task;
    }
}
