<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

final class TaskLog implements \JsonSerializable
{
    private \DateTimeImmutable $date;
    private string $user;
    private string $log;

    public function __construct(string $user, string $log)
    {
        $this->date = new \DateTimeImmutable('now');
        $this->user = $user;
        $this->log = $log;
    }

    /**
     * @return array{date: string, user: string, log: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'date' => $this->date->format(\DATE_ATOM),
            'user' => $this->user,
            'log' => $this->log,
        ];
    }
}
