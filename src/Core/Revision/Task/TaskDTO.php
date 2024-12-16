<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CoreBundle\Entity\Task;
use EMS\Helpers\Standard\DateTime;
use Symfony\Component\Validator\Constraints as Assert;

final class TaskDTO
{
    public ?string $id = null;
    #[Assert\NotBlank]
    public ?string $title = null;
    #[Assert\NotBlank]
    public ?string $assignee = null;
    public ?\DateTimeInterface $deadline = null;
    public ?string $description = null;
    #[Assert\NotBlank]
    #[Assert\Range(min: 0)]
    public ?int $delay = null;

    public function __construct(
        private readonly string $coreDateFormat,
    ) {
    }

    public static function fromEntity(Task $task, string $coreDateFormat): TaskDTO
    {
        $dto = new self($coreDateFormat);
        $dto->id = $task->getId();
        $dto->title = $task->getTitle();
        $dto->delay = $task->getDelay();
        $dto->assignee = $task->getAssignee();

        if ($task->hasDeadline()) {
            $dto->deadline = $task->getDeadline();
        }
        if ($task->hasDescription()) {
            $dto->description = $task->getDescription();
        }

        return $dto;
    }

    public function getDeadline(): ?string
    {
        return $this->deadline?->format($this->coreDateFormat);
    }

    public function setDeadline(string $deadline): void
    {
        $this->deadline = DateTime::createFromFormat($deadline, $this->coreDateFormat);
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
}
