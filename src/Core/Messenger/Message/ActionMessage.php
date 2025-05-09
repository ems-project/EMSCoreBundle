<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Messenger\Message;

readonly class ActionMessage implements AsyncMessageInterface
{
    public function __construct(
        public int $revisionId,
        /** @var array<mixed> */
        public array $request,
        public string $createdBy,
    ) {
    }
}
