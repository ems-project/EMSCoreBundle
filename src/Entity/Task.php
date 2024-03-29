<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CoreBundle\Core\Revision\Task\TaskDTO;
use EMS\CoreBundle\Core\Revision\Task\TaskLog;
use EMS\CoreBundle\Core\Revision\Task\TaskStatus;
use EMS\Helpers\Standard\DateTime;
use EMS\Helpers\Standard\Type;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Table(name="task")
 *
 * @ORM\Entity()
 *
 * @ORM\HasLifecycleCallbacks()
 */
class Task implements EntityInterface
{
    use CreatedModifiedTrait;

    /**
     * @ORM\Id
     *
     * @ORM\Column(type="uuid", unique=true)
     */
    private UuidInterface $id;

    /**
     * @ORM\Column(name="revision_ouuid", type="string", length=255)
     */
    private string $revisionOuuid;

    /**
     * @ORM\Column(name="title", type="string", length=255)
     */
    private string $title;

    /**
     * @ORM\Column(name="status", type="string", length=25)
     */
    private string $status;

    /**
     * @ORM\Column(name="delay", type="integer")
     */
    private int $delay;

    /**
     * @ORM\Column(name="deadline", type="datetime_immutable", nullable=true)
     */
    private ?\DateTimeInterface $deadline = null;

    /**
     * @ORM\Column(name="assignee", type="text")
     */
    private string $assignee;

    /**
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    private ?string $description = null;

    /**
     * @var array<mixed>
     *
     * @ORM\Column(name="logs", type="json")
     */
    private array $logs;

    /**
     * @ORM\Column(name="created_by", type="text")
     */
    private string $createdBy;

    private function __construct(Revision $revision, string $username)
    {
        $this->id = Uuid::uuid4();
        $this->revisionOuuid = $revision->giveOuuid();
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
        $this->createdBy = $username;
        $this->status = TaskStatus::PLANNED->value;
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
