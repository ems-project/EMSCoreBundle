<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CommonBundle\Common\Standard\DateTime;
use EMS\CoreBundle\Core\Revision\Task\TaskDTO;
use EMS\CoreBundle\Core\Revision\Task\TaskLog;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Table(name="task")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
final class Task
{
    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=UuidGenerator::class)
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
     * @var TaskLog[]
     *
     * @ORM\Column(name="logs", type="json", nullable=false)
     */
    private array $logs;

    public const STATUS_PROGRESS = 'progress';
    public const STATUS_PLANNED = 'planned';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_APPROVED = 'approved';

    public function __construct(string $username, string $status)
    {
        $this->id = Uuid::uuid4();
        $this->status = self::STATUS_PLANNED;
        $this->logs[] = new TaskLog($username, $status, 'created');
    }

    public function changeStatus(string $newStatus, string $username, ?string $comment): void
    {
        $this->status = $newStatus;
        $this->logs[] = new TaskLog($username, $newStatus, $comment);
    }

    public function setAssignee(string $assignee): void
    {
        $this->assignee = $assignee;
    }

    public static function createFromDTO(TaskDTO $dto, string $username): Task
    {
        $task = new self($username, self::STATUS_PLANNED);
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

    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getStatus(): string
    {
        return $this->status;
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

    /**
     * @return TaskLog[]
     */
    public function getLogs(): array
    {
        return $this->logs;
    }
}
