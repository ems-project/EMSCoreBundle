<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CoreBundle\Entity\Task;
use EMS\Helpers\Standard\DateTime;

final class TaskLog
{
    private \DateTimeInterface $date;
    public string $status;
    public ?string $comment = null;

    public ?string $taskTitle = null;
    public ?string $taskDescription = null;
    public ?\DateTimeInterface $taskDeadline = null;
    public ?string $taskAssignee = null;

    private const string STATUS_CREATED = 'created';
    private const string STATUS_UPDATED = 'updated';

    private function __construct(public string $assignee, public string $username)
    {
        $this->date = new \DateTimeImmutable('now');
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromData(array $data): self
    {
        $log = new self($data['assignee'], $data['username']);
        $log->status = $data['status'];
        $log->comment = $data['comment'] ?? null;
        $log->date = DateTime::createFromFormat($data['date'], \DATE_ATOM);

        $log->taskTitle = $data['task_title'] ?? null;
        $log->taskAssignee = $data['task_assignee'] ?? null;
        $log->taskDescription = $data['task_description'] ?? null;
        if (isset($data['task_deadline'])) {
            $log->taskDeadline = DateTime::createFromFormat($data['task_deadline'], \DATE_ATOM);
        }

        return $log;
    }

    public static function logStatusUpdate(Task $task, string $username, ?string $comment): self
    {
        $log = new self($task->getAssignee(), $username);
        $log->status = $task->getStatus();
        $log->comment = $comment;

        return $log;
    }

    public static function logNewAssignee(Task $task, string $username): self
    {
        $log = new self($task->getAssignee(), $username);
        $log->status = TaskStatus::PROGRESS->value;

        return $log;
    }

    public static function logCreate(Task $task, string $username): self
    {
        $log = new self($task->getAssignee(), $username);
        $log->status = self::STATUS_CREATED;
        $log->taskTitle = $task->getTitle();
        $log->taskAssignee = $task->getAssignee();
        $log->taskDeadline = $task->hasDeadline() ? $task->getDeadline() : null;
        $log->taskDescription = $task->hasDescription() ? $task->getDescription() : null;

        return $log;
    }

    /**
     * @param array<mixed> $changeSet
     */
    public static function logUpdate(Task $task, string $username, array $changeSet): self
    {
        $log = new self($task->getAssignee(), $username);
        $log->status = self::STATUS_UPDATED;

        if (isset($changeSet['title'])) {
            $log->taskTitle = $changeSet['title'][1];
        }
        if (isset($changeSet['assignee'])) {
            $log->taskAssignee = $changeSet['assignee'][1];
        }
        if (isset($changeSet['deadline'])) {
            $log->taskDeadline = $changeSet['deadline'][1];
        }
        if (isset($changeSet['description'])) {
            $log->taskDescription = $changeSet['description'][1];
        }

        return $log;
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function getIcon(): string
    {
        if (self::STATUS_CREATED === $this->status) {
            return 'fa fa-plus bg-green';
        }
        if (self::STATUS_UPDATED === $this->status) {
            return 'fa fa-pencil bg-gray';
        }

        $status = TaskStatus::tryFrom($this->status);
        if ($status) {
            return \sprintf('%s bg-%s', $status->getCssClassIcon(), $status->getColor());
        }

        return 'fa fa-dot-circle-o bg-gray';
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function hasTaskData(): bool
    {
        return null !== $this->taskTitle
            || null !== $this->taskAssignee
            || null !== $this->taskDeadline
            || null !== $this->taskDescription;
    }

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        return \array_filter([
            'username' => $this->username,
            'assignee' => $this->assignee,
            'status' => $this->status,
            'comment' => $this->comment,
            'date' => $this->date->format(\DATE_ATOM),
            'task_title' => $this->taskTitle,
            'task_assignee' => $this->taskAssignee,
            'task_deadline' => $this->taskDeadline?->format(\DATE_ATOM),
            'task_description' => $this->taskDescription,
        ]);
    }
}
