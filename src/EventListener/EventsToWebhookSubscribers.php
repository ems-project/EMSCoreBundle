<?php

declare(strict_types=1);

namespace EMS\CoreBundle\EventListener;

use EMS\CoreBundle\Core\Messenger\Message\WebhookSubscriberMessage;
use EMS\CoreBundle\Event\NewIndexEvent;
use EMS\CoreBundle\Event\RevisionDeleteEvent;
use EMS\CoreBundle\Event\RevisionFinalizeDraftEvent;
use EMS\CoreBundle\Event\RevisionPublishEvent;
use EMS\CoreBundle\Event\RevisionUnpublishEvent;
use EMS\CoreBundle\Repository\WebhookSubscriptionRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class EventsToWebhookSubscribers implements EventSubscriberInterface
{
    public function __construct(
        private WebhookSubscriptionRepository $repository,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RevisionPublishEvent::class => 'onRevisionPublished',
            RevisionFinalizeDraftEvent::class => 'onFinalizeDraft',
            RevisionUnpublishEvent::class => 'onUnpublish',
            WorkerMessageFailedEvent::class => 'onMessageFailed',
            NewIndexEvent::class => 'onNewIndex',
            RevisionDeleteEvent::class => 'onDelete',
        ];
    }

    public function onRevisionPublished(RevisionPublishEvent $event): void
    {
        $this->dispatch(\sprintf('content.published.%s', $event->getEnvironment()->getName()), [
            'environment' => $event->getEnvironment()->getName(),
            'alias' => $event->getEnvironment()->getAlias(),
            'content_type' => $event->getRevision()->giveContentType()->getName(),
            'ouuid' => $event->getRevision()->getOuuid(),
            'raw_data' => $event->getRevision()->getData(),
        ]);
    }

    public function onFinalizeDraft(RevisionFinalizeDraftEvent $event): void
    {
        $this->dispatch('content.finalize', [
            'environment' => $event->getEnvironment()->getName(),
            'alias' => $event->getEnvironment()->getAlias(),
            'content_type' => $event->getRevision()->giveContentType()->getName(),
            'ouuid' => $event->getRevision()->getOuuid(),
            'raw_data' => $event->getRevision()->getData(),
        ]);
    }

    public function onUnpublish(RevisionUnpublishEvent $event): void
    {
        $this->dispatch('content.unpublish', [
            'environment' => $event->getEnvironment()->getName(),
            'alias' => $event->getEnvironment()->getAlias(),
            'content_type' => $event->getRevision()->giveContentType()->getName(),
            'ouuid' => $event->getRevision()->getOuuid(),
        ]);
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }
        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof WebhookSubscriberMessage) {
            return;
        }
        $this->repository->disable($message->subscriptionId, $event->getThrowable()->getMessage());
        $this->logger->error('webhook.subscriber.disabled', [
            'subscriptionId' => $message->subscriptionId,
            'event' => $message->eventName,
            'errorMessage' => $event->getThrowable()->getMessage(),
        ]);
    }

    public function onNewIndex(NewIndexEvent $event): void
    {
        $this->dispatch(\sprintf('environment.new_index.%s', $event->getEnvironment()->getName()), [
            'environment' => $event->getEnvironment()->getName(),
            'index' => $event->getIndex(),
            'aliases' => $event->getAliases(),
            'old_index' => $event->getOldIndex(),
        ]);
    }

    public function onDelete(RevisionDeleteEvent $event): void
    {
        $this->dispatch('content.delete', [
            'environment' => $event->getEnvironment()->getName(),
            'alias' => $event->getEnvironment()->getAlias(),
            'content_type' => $event->getRevision()->giveContentType()->getName(),
            'ouuid' => $event->getRevision()->getOuuid(),
        ]);
    }

    /**
     * @param mixed[] $data
     */
    private function dispatch(string $eventName, array $data): void
    {
        $payload = [
            'event' => $eventName,
            'data' => $data,
        ];

        foreach ($this->repository->findEnabled() as $subscription) {
            if (!\in_array($eventName, $subscription->getEvents(), true)) {
                continue;
            }

            $this->bus->dispatch(
                new WebhookSubscriberMessage($subscription->getId(), $eventName, $payload)
            );
        }
    }
}
