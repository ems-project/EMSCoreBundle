<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CommonBundle\Common\Standard\DateTime;
use EMS\CoreBundle\Entity\Task;
use Symfony\Component\Validator\Constraints as Assert;

final class TaskDTO
{
    /** @Assert\NotBlank */
    public ?string $title = null;
    /** @Assert\NotBlank */
    public ?string $assignee = null;
    public ?string $deadline = null;
    public ?string $description = null;

    public static function fromEntity(Task $task): TaskDTO
    {
        $dto = new self();
        $dto->title = $task->getTitle();
        $dto->assignee = $task->getAssignee();

        if ($task->hasDeadline()) {
            $dto->deadline = $task->getDeadline()->format(\DateTimeInterface::ATOM);
        }
        if ($task->hasDescription()) {
            $dto->description = $task->getDescription();
        }

        return $dto;
    }

    public function giveTitle(): string
    {
        if (null === $this->title) {
            throw new \RuntimeException('missing title');
        }

        return $this->title;
    }

    public function giveAssignee(): string
    {
        if (null === $this->assignee) {
            throw new \RuntimeException('missing assignee');
        }

        return $this->assignee;
    }

    public function hasDeadline(): bool
    {
        return null !== $this->deadline;
    }

    public function giveDeadline(): \DateTimeInterface
    {
        if (null === $this->deadline) {
            throw new \RuntimeException('missing deadline');
        }

        return DateTime::createFromFormat($this->deadline, 'd/m/Y');
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
}
