<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CoreBundle\Entity\Task;
use Symfony\Component\Validator\Constraints as Assert;

final class TaskDTO
{
    /**
     * @Assert\NotBlank
     */
    public string $title;

    /**
     * @Assert\NotBlank
     */
    public string $description;

    /**
     * @Assert\NotBlank
     */
    public string $assignee;

    /**
     * @Assert\NotBlank
     */
    public string $deadline;

    public static function fromEntity(Task $task): TaskDTO
    {
        $dto = new self();

        $dto->title = $task->getTitle();
        $dto->description = $task->getDescription();
        $dto->assignee = $task->getAssignee();
        $dto->deadline = $task->getDeadline()->format(\DateTimeInterface::ATOM);

        return $dto;
    }

    public function giveTitle(): string
    {
        return $this->title;
    }

    public function giveDescription(): string
    {
        return $this->description;
    }

    public function giveAssignee(): string
    {
        return $this->assignee;
    }

    public function giveDeadline(): string
    {
        return $this->deadline;
    }
}
