<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CommonBundle\Common\Standard\DateTime;
use EMS\CoreBundle\Core\Revision\Task\TaskDTO;
use EMS\CoreBundle\Core\Revision\Task\TaskLog;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Table(name="task")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 *
 * @final
 */
class Task implements EntityInterface
{
    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     */
    private UuidInterface $id;

    /**
     * @ORM\Column(name="title", type="string", length=255, nullable=false)
     */
    private string $title;

    /**
     * @ORM\Column(name="status", type="string", length=25, nullable=false)
     */
    private string $status;

    /**
     * @ORM\Column(name="deadline", type="datetime_immutable", nullable=false)
     */
    private \DateTimeInterface $deadline;

    /**
     * @ORM\Column(name="assignee", type="text", nullable=false)
     */
    private string $assignee;

    /**
     * @ORM\Column(name="description", type="text", nullable=false)
     */
    private string $description;

    /**
     * @var array<mixed>
     *
     * @ORM\Column(name="logs", type="json", nullable=false)
     */
    private array $logs;

    public const STATUS_PROGRESS = 'progress';
    public const STATUS_PLANNED = 'planned';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_APPROVED = 'approved';

    public function __construct(string $username)
    {
        $this->id = Uuid::uuid4();
        $this->status = self::STATUS_PLANNED;
        $this->addLog($username);
    }

    public static function createFromDTO(TaskDTO $dto, UserInterface $user): Task
    {
        $task = new self($user->getUsername());
        $task->updateFromDTO($dto);

        return $task;
    }

    public function updateFromDTO(TaskDTO $taskDTO): void
    {
        $this->title = $taskDTO->give('title');
        $this->description = $taskDTO->give('description');
        $this->assignee = $taskDTO->give('assignee');
        $this->deadline = DateTime::createFromFormat($taskDTO->give('deadline'), 'd/m/Y');
    }

    public function changeStatus(string $newStatus, string $username, ?string $comment = null): void
    {
        $this->status = $newStatus;
        $this->addLog($username, $comment);
    }

    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getStatusClass(): string
    {
        switch ($this->status) {
            case self::STATUS_PROGRESS:
                return 'primary';
            case self::STATUS_APPROVED:
            case self::STATUS_COMPLETED:
                return 'success';
            case self::STATUS_REJECTED:
                return 'danger';
            default:
                return 'default';
        }
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDeadline(): \DateTimeInterface
    {
        return $this->deadline;
    }

    public function getAssignee(): string
    {
        return $this->assignee;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getLatestCompleted(): ?TaskLog
    {
        if (self::STATUS_COMPLETED !== $this->status) {
            return null;
        }

        return $this->getLogLatestByStatus(self::STATUS_COMPLETED);
    }

    public function getLatestRejection(): ?TaskLog
    {
        if (self::STATUS_REJECTED !== $this->status) {
            return null;
        }

        return $this->getLogLatestByStatus(self::STATUS_REJECTED);
    }

    public function getLatestCompletedUsername(): ?string
    {
        $latestCompleted = $this->getLogLatestByStatus(self::STATUS_COMPLETED);

        return $latestCompleted ? $latestCompleted->getUsername() : null;
    }

    /**
     * @return TaskLog[]
     */
    public function getLogs(): array
    {
        return \array_map(fn (array $log) => TaskLog::fromData($log), $this->logs);
    }

    public function isOpen(): bool
    {
        return !\in_array($this->status, [Task::STATUS_COMPLETED, Task::STATUS_APPROVED], true);
    }

    public function statusProgress(string $username): void
    {
        $this->status = self::STATUS_PROGRESS;
        $this->addLog($username);
    }

    public function setAssignee(string $assignee): void
    {
        $this->assignee = $assignee;
    }

    private function addLog(string $username, ?string $comment = null): void
    {
        $this->logs[] = (new TaskLog($username, $this->status, $comment))->getData();
    }

    private function getLogLatestByStatus(string $status): ?TaskLog
    {
        $logs = $this->getLogs();
        $statusLogs = \array_filter($logs, fn (TaskLog $log) => $log->getStatus() === $status);
        $latestStatusLog = \array_pop($statusLogs);

        return $latestStatusLog instanceof TaskLog ? $latestStatusLog : null;
    }
}
