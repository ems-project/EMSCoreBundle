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
        $counter = 0;
        foreach ($this->repository->findEnabled() as $subscription) {
            if (!\in_array($eventName, $subscription->getEvents(), true)) {
                continue;
            }
            $this->dispatchTo($subscription, $eventName, $data, $stamps);
            ++$counter;
        }

        return $counter;
    }

    /**
     * @param mixed[]          $data
     * @param StampInterface[] $stamps
     */
    public function dispatchTo(WebhookSubscription $subscription, string $eventName, array $data, array $stamps = []): void
    {
        $payload = [
            'event' => $eventName,
            'data' => $data,
        ];
        $this->bus->dispatch(
            new WebhookSubscriberMessage($subscription->getId(), $eventName, $payload),
            $stamps
        );
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

    public function enable(WebhookSubscription $webhookSubscription, bool $enabled = true): void
    {
        $webhookSubscription->setEnabled($enabled);
        $this->repository->update($webhookSubscription);
    }
}
