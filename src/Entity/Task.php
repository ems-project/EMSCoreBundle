<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CoreBundle\Core\Revision\Task\TaskDTO;
use EMS\CoreBundle\Core\Revision\Task\TaskLog;
use EMS\CoreBundle\Core\Revision\Task\TaskStatus;
use EMS\Helpers\Standard\DateTime;
use EMS\Helpers\Standard\Type;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class Task implements EntityInterface
{
    use CreatedModifiedTrait;

    private UuidInterface $id;
    private string $revisionOuuid;
    private string $title;
    private string $status;
    private int $delay;
    private ?\DateTimeInterface $deadline = null;
    private string $assignee;
    private ?string $description = null;
    /** @var array<mixed> */
    private array $logs;

    private function __construct(Revision $revision, private string $createdBy)
    {
        $this->id = Uuid::uuid4();
        $this->revisionOuuid = $revision->giveOuuid();
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
        $this->status = TaskStatus::PLANNED->value;
    }

    public function getRevisionOuuid(): string
    {
        return $this->revisionOuuid;
    }

    public function addLog(TaskLog $taskLog): void
    {
        $this->logs[] = $taskLog->getData();
    }

    public static function createFromDTO(TaskDTO $dto, Revision $revision, string $username): Task
    {
        $task = new self($revision, $username);
        $task->updateFromDTO($dto);

        return $task;
    }

    public function updateFromDTO(TaskDTO $taskDTO): void
    {
        $this->title = Type::string($taskDTO->title);
        $this->assignee = Type::string($taskDTO->assignee);
        $this->description = $taskDTO->description;

        if (null !== $taskDTO->delay) {
            $this->delay = Type::integer($taskDTO->delay);
        }
        if (null !== $taskDTO->deadline) {
            $this->deadline = $taskDTO->deadline;
        }
    }

    #[\Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isStatus(TaskStatus ...$status): bool
    {
        $statuses = \array_map(static fn (TaskStatus $s) => $s->value, $status);

        return \in_array($this->status, $statuses, true);
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;

        if ($this->isStatus(TaskStatus::PROGRESS)) {
            $this->deadline = DateTime::create('now')->add(new \DateInterval(\sprintf('P%dD', $this->delay)));
        }
    }

    public function getStatusIcon(): string
    {
        return TaskStatus::from($this->status)->getCssClassIcon();
    }

    public function getStatusLabel(): string
    {
        return TaskStatus::from($this->status)->getCssClassLabel();
    }

    public function getStatusText(): string
    {
        return TaskStatus::from($this->status)->getCssClassText();
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
        if (!$this->isStatus(TaskStatus::COMPLETED)) {
            return null;
        }

        return $this->getLogLatestByStatus(TaskStatus::COMPLETED);
    }

    public function getLatestRejection(): ?TaskLog
    {
        if (!$this->isStatus(TaskStatus::REJECTED)) {
            return null;
        }

        return $this->getLogLatestByStatus(TaskStatus::REJECTED);
    }

    public function getLatestApproved(): ?TaskLog
    {
        if (!$this->isStatus(TaskStatus::APPROVED)) {
            return null;
        }

        return $this->getLogLatestByStatus(TaskStatus::APPROVED);
    }

    /**
     * @return TaskLog[]
     */
    public function getLogs(): array
    {
        return \array_map(static fn (array $log) => TaskLog::fromData($log), $this->logs);
    }

    public function isOpen(): bool
    {
        return !$this->isStatus(TaskStatus::COMPLETED, TaskStatus::APPROVED);
    }

    public function setAssignee(string $assignee): void
    {
        $this->assignee = $assignee;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    private function getLogLatestByStatus(TaskStatus $status): ?TaskLog
    {
        $logs = $this->getLogs();
        $statusLogs = \array_filter($logs, static fn (TaskLog $log) => $log->getStatus() === $status->value);
        $latestStatusLog = \array_pop($statusLogs);

        return $latestStatusLog instanceof TaskLog ? $latestStatusLog : null;
    }

    #[\Override]
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
