<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class DispatchToWebhookEvent extends Event
{
    /**
     * @param mixed[] $data
     */
    public function __construct(public readonly string $name, public readonly array $data)
    {
    }
}
