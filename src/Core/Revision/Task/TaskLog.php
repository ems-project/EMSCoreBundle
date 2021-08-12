<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CommonBundle\Common\Standard\DateTime;
use EMS\CoreBundle\Entity\Task;

final class TaskLog
{
    private \DateTimeInterface $date;
    private string $username;
    private string $status;
    private ?string $comment;

    public function __construct(string $username, string $status, ?string $comment = null)
    {
        $this->username = $username;
        $this->status = $status;
        $this->comment = $comment;
        $this->date = new \DateTimeImmutable('now');
    }

    public function getIconClass(): string
    {
        switch ($this->status) {
            case Task::STATUS_PLANNED:
                return 'fa fa-hourglass-o bg-gray';
            case Task::STATUS_PROGRESS:
                return 'fa-paper-plane-o bg-blue';
            case Task::STATUS_COMPLETED:
                return 'fa-commenting-o bg-green';
            case Task::STATUS_APPROVED:
                return 'fa-check bg-green';
            case Task::STATUS_REJECTED:
                return 'fa-close bg-red';
            default:
                return 'fa-dot-circle-o bg-gray';
        }
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromData(array $data): self
    {
        $log = new self($data['username'], $data['status'], $data['comment'] ?? null);
        $log->date = DateTime::createFromFormat(\DATE_ATOM, $data['date']);

        return $log;
    }

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        return \array_filter([
            'username' => $this->username,
            'status' => $this->status,
            'comment' => $this->comment,
            'date' => $this->date->format(\DATE_ATOM),
        ]);
    }
}
