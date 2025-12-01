<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Event;

use EMS\CoreBundle\Entity\WebhookSubscription;
use Symfony\Contracts\EventDispatcher\Event;

class ValidateWebhookSubscriptionEvent extends Event
{
    public function __construct(public readonly WebhookSubscription $webhookSubscription)
    {
    }
}
