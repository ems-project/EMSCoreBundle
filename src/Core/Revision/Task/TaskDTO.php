<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

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

    public function give(string $property): string
    {
        $property = $this->{$property};

        if (!\is_string($property)) {
            throw new \RuntimeException(\sprintf('Missing %s', $property));
        }

        return $property;
    }
}
