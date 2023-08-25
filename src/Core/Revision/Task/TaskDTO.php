<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CoreBundle\Entity\Task;
use Symfony\Component\Validator\Constraints as Assert;

final class TaskDTO
{
    public ?string $id = null;
    #[Assert\NotBlank]
    public ?string $title = null;
    #[Assert\NotBlank]
    public ?string $assignee = null;
    public ?string $deadline = null;
    public ?string $description = null;
    #[Assert\NotBlank]
    #[Assert\Range(min: 0)]
    public ?int $delay = null;

    public static function fromEntity(Task $task, string $coreDateFormat): TaskDTO
    {
        $dto = new self();
        $dto->id = $task->getId();
        $dto->title = $task->getTitle();
        $dto->delay = $task->getDelay();
        $dto->assignee = $task->getAssignee();

        if ($task->hasDeadline()) {
            $dto->deadline = $task->getDeadline()->format($coreDateFormat);
        }
        if ($task->hasDescription()) {
            $dto->description = $task->getDescription();
        }

        return $dto;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
}
