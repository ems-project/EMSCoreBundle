<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CoreBundle\Entity\WebhookSubscription;
use EMS\CoreBundle\Repository\WebhookSubscriptionRepository;

class WebhookSubscriptionService
{
    public function __construct(public readonly WebhookSubscriptionRepository $repository)
    {
    }

    /**
     * @param string[] $events
     */
    public function create(string $endpointUrl, array $events): WebhookSubscription
    {
        $webhookSubscription = $this->repository->findByEndpointUrlAndEvents($endpointUrl, $events);
        if (null !== $webhookSubscription) {
            return $webhookSubscription;
        }
        $secret = \bin2hex(\random_bytes(32));

        return $this->repository->create($endpointUrl, $events, $secret);
    }

    public function unsubscribe(string $id): void
    {
        $this->repository->disable($id, 'Unsubscribe');
    }
}
