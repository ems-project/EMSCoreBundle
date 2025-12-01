<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\ClientHelperBundle\Helper\Webhook\Webhook;
use EMS\CoreBundle\Core\Messenger\Message\WebhookSubscriberMessage;
use EMS\CoreBundle\Entity\WebhookSubscription;
use EMS\CoreBundle\Repository\WebhookSubscriptionRepository;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

class WebhookService
{
    public function __construct(
        private readonly WebhookSubscriptionRepository $repository,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * @param mixed[]          $data
     * @param StampInterface[] $stamps
     */
    public function dispatch(string $eventName, array $data, array $stamps = []): int
    {
        $payload = [
            'event' => $eventName,
            'data' => $data,
        ];

        $counter = 0;
        foreach ($this->repository->findEnabled() as $subscription) {
            if (!\in_array($eventName, $subscription->getEvents(), true)) {
                continue;
            }

            $this->bus->dispatch(
                new WebhookSubscriberMessage($subscription->getId(), $eventName, $payload),
                $stamps
            );
            ++$counter;
        }

        return $counter;
    }

    public function disable(WorkerMessageFailedEvent $event, WebhookSubscriberMessage $message): void
    {
        $this->repository->disable($message->subscriptionId, $event->getThrowable()->getMessage());
    }

    public function validate(WebhookSubscription $subscription): void
    {
        $this->bus->dispatch(
            new WebhookSubscriberMessage(
                $subscription->getId(),
                Webhook::VALIDATE_WEBHOOK_SUBSCRIBER,
                [
                    'event' => Webhook::VALIDATE_WEBHOOK_SUBSCRIBER,
                    'data' => [
                        'secret' => $subscription->getSecret(),
                        'events' => $subscription->getEvents(),
                    ],
                ],
            ),
            [new DelayStamp(40_000)]
        );
    }
}
