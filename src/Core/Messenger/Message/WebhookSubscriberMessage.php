<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Messenger\Message;

readonly class WebhookSubscriberMessage implements AsyncMessageInterface
{
    /**
     * @param mixed[] $payload
     */
    public function __construct(
        public string $subscriptionId,
        public string $eventName,
        public array $payload,
    ) {
    }
}
