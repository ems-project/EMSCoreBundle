<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Message;

readonly class Job implements AsyncMessageInterface
{
    public function __construct(
        private int $content,
    ) {
    }

    public function getContent(): int
    {
        return $this->content;
    }
}
