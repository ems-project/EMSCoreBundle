<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Embeddable()
 */
final class RevisionTasks
{
    /**
     * @ORM\Column(name="current_id", type="uuid", nullable=true)
     */
    private ?string $currentId;

    /**
     * @var string[]|null
     *
     * @ORM\Column(name="planned_ids", type="json", nullable=true)
     */
    private ?array $plannedIds = [];

    /**
     * @var string[]|null
     *
     * @ORM\Column(name="approved_ids", type="json", nullable=true)
     */
    private ?array $approvedIds = [];

    /**
     * @ORM\Column(name="owner", type="text", nullable=true)
     */
    private ?string $owner = null;

    public function isEmpty(): bool
    {
        return !$this->hasCurrentId() && !$this->hasPlannedIds() && !$this->hasApprovedIds();
    }

    public function isCurrent(Task $task): bool
    {
        return $this->currentId === $task->getId();
    }

    public function hasCurrentId(): bool
    {
        return null !== $this->currentId;
    }

    public function hasPlannedIds(): bool
    {
        return \count($this->plannedIds ?? []) > 0;
    }

    public function hasApprovedIds(): bool
    {
        return \count($this->approvedIds ?? []) > 0;
    }

    public function getOwner(): string
    {
        if (null === $owner = $this->owner) {
            throw new \RuntimeException('Revision has no owner');
        }

        return $owner;
    }

    public function getCurrentId(): string
    {
        if (null === $this->currentId) {
            throw new \RuntimeException('Revision has no current task');
        }

        return $this->currentId;
    }

    /**
     * @return string[]
     */
    public function getPlannedIds(): array
    {
        return $this->plannedIds ?? [];
    }

    /**
     * @return string[]
     */
    public function getApprovedIds(): array
    {
        return $this->approvedIds ?? [];
    }

    public function add(Task $task, UserInterface $user): void
    {
        if (null === $this->owner) {
            $this->owner = $user->getUsername();
        }

        if ($this->owner !== $user->getUsername()) {
            throw new \RuntimeException(sprintf('User %s is not the owner!', $user->getUsername()));
        }

        if (null === $this->currentId) {
            $this->currentId = $task->getId();
        } else {
            $this->plannedIds[] = $task->getId();
        }
    }

    public function delete(Task $task): bool
    {
        $taskId = $task->getId();

        if ($taskId === $this->currentId) {
            $plannedIds = $this->getPlannedIds();
            $this->currentId = \array_shift($plannedIds);
            $this->plannedIds = $plannedIds;

            return true;
        }

        if (\in_array($taskId, $this->getPlannedIds(), true)) {
            $this->plannedIds = \array_diff($this->getPlannedIds(), [$taskId]);

            return true;
        }

        if (\in_array($taskId, $this->getApprovedIds(), true)) {
            $this->approvedIds = \array_diff($this->getApprovedIds(), [$taskId]);

            return true;
        }

        return false;
    }
}
