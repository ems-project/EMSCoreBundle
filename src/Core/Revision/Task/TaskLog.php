<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

final class TaskLog implements \JsonSerializable
{
    private \DateTimeImmutable $date;
    private string $user;
    private string $status;
    private ?string $comment;

    public function __construct(string $user, string $status, ?string $comment)
    {
        $this->user = $user;
        $this->status = $status;
        $this->comment = $comment;
        $this->date = new \DateTimeImmutable('now');
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return \array_filter([
            'user' => $this->user,
            'status' => $this->status,
            'comment' => $this->comment,
            'date' => $this->date->format(\DATE_ATOM),
        ]);
    }
}
