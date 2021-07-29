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

    public function isEmpty(): bool
    {
        return !$this->hasCurrentId() && !$this->hasPlannedIds() && !$this->hasApprovedIds();
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

    public function add(Task $task): void
    {
        if (null === $this->currentId) {
            $this->currentId = $task->getId();
        } else {
            $this->plannedIds[] = $task->getId();
        }
    }
}
