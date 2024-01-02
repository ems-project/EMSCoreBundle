<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CoreBundle\Core\Revision\Task\TaskDTO;
use EMS\CoreBundle\Core\Revision\Task\TaskLog;
use EMS\Helpers\Standard\DateTime;
use EMS\Helpers\Standard\Type;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class Task implements EntityInterface
{
    use CreatedModifiedTrait;

    private readonly UuidInterface $id;
    private string $title;
    private string $status = self::STATUS_PLANNED;
    private int $delay;
    private ?\DateTimeInterface $deadline = null;
    private string $assignee;
    private ?string $description = null;
    /** @var array<mixed> */
    private array $logs;

    final public const STATUS_PROGRESS = 'progress';
    final public const STATUS_PLANNED = 'planned';
    final public const STATUS_COMPLETED = 'completed';
    final public const STATUS_REJECTED = 'rejected';
    final public const STATUS_APPROVED = 'approved';

    final public const STYLES = [
        self::STATUS_PLANNED => ['icon' => 'fa fa-hourglass-o', 'bg' => 'gray', 'text' => 'muted', 'label' => 'default'],
        self::STATUS_PROGRESS => ['icon' => 'fa fa-ticket', 'bg' => 'blue', 'text' => 'primary', 'label' => 'primary'],
        self::STATUS_COMPLETED => ['icon' => 'fa fa-paper-plane', 'bg' => 'green', 'text' => 'success', 'label' => 'success'],
        self::STATUS_REJECTED => ['icon' => 'fa fa-close', 'bg' => 'red', 'text' => 'danger', 'label' => 'danger'],
        self::STATUS_APPROVED => ['icon' => 'fa fa-check', 'bg' => 'green', 'text' => 'success', 'label' => 'success'],
    ];

    public function __construct(private string $createdBy)
    {
        $this->id = Uuid::uuid4();
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    public function addLog(TaskLog $taskLog): void
    {
        $this->logs[] = $taskLog->getData();
    }

    public static function createFromDTO(TaskDTO $dto, string $username): Task
    {
        $task = new self($username);
        $task->updateFromDTO($dto);

        return $task;
    }

    public function updateFromDTO(TaskDTO $taskDTO): void
    {
        $this->title = Type::string($taskDTO->title);
        $this->assignee = Type::string($taskDTO->assignee);
        $this->delay = Type::integer($taskDTO->delay);
        $this->description = $taskDTO->description;
    }

    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isStatus(string ...$status): bool
    {
        return \in_array($this->status, $status);
    }

    public function setStatus(string $status): void
    {
        if (self::STATUS_PROGRESS === $status) {
            $this->deadline = DateTime::create('now')->add(new \DateInterval(\sprintf('P%dD', $this->delay)));
        }

        $this->status = $status;
    }

    public function getStatusIcon(): string
    {
        $style = Task::STYLES[$this->status] ?? null;

        return $style ? \sprintf('%s text-%s', $style['icon'], $style['text']) : 'fa-dot-circle-o';
    }

    public function getStatusLabel(): string
    {
        return self::STYLES[$this->status]['label'] ?? 'default';
    }

    public function getStatusText(): string
    {
        return self::STYLES[$this->status]['text'] ?? '';
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDelay(): int
    {
        return $this->delay;
    }

    public function hasDeadline(): bool
    {
        return null !== $this->deadline;
    }

    public function getDeadline(): \DateTimeInterface
    {
        if (null === $deadline = $this->deadline) {
            throw new \RuntimeException('No deadline!');
        }

        return $deadline;
    }

    public function getAssignee(): string
    {
        return $this->assignee;
    }

    public function isAssignee(UserInterface $user): bool
    {
        return $this->assignee === $user->getUserIdentifier();
    }

    public function isRequester(UserInterface $user): bool
    {
        return $this->createdBy === $user->getUserIdentifier();
    }

    public function hasDescription(): bool
    {
        return null !== $this->description;
    }

    public function getDescription(): string
    {
        if (null === $description = $this->description) {
            throw new \RuntimeException('No description!');
        }

        return $description;
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

    public function getLatestApproved(): ?TaskLog
    {
        if (self::STATUS_APPROVED !== $this->status) {
            return null;
        }

        return $this->getLogLatestByStatus(self::STATUS_APPROVED);
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

    public function setAssignee(string $assignee): void
    {
        $this->assignee = $assignee;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    private function getLogLatestByStatus(string $status): ?TaskLog
    {
        $logs = $this->getLogs();
        $statusLogs = \array_filter($logs, fn (TaskLog $log) => $log->getStatus() === $status);
        $latestStatusLog = \array_pop($statusLogs);

        return $latestStatusLog instanceof TaskLog ? $latestStatusLog : null;
    }

    public function getName(): string
    {
        return $this->getId();
    }

    public function getCreatedBy(): string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(string $createdBy): void
    {
        $this->createdBy = $createdBy;
    }
}
