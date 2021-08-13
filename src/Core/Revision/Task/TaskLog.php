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

    public function getIcon(): string
    {
        $style = Task::STYLES[$this->status] ?? null;

        return $style ? sprintf('%s bg-%s', $style['icon'], $style['bg']) : 'fa-dot-circle-o bg-gray';
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
        $log->date = DateTime::createFromFormat($data['date'], \DATE_ATOM);

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
