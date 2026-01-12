<?php

declare(strict_types=1);

namespace EMS\CoreBundle\EventListener;

use EMS\CoreBundle\Core\Messenger\Message\WebhookSubscriberMessage;
use EMS\CoreBundle\Event\AliasUpdateEvent;
use EMS\CoreBundle\Event\DispatchToWebhookEvent;
use EMS\CoreBundle\Event\NewIndexEvent;
use EMS\CoreBundle\Event\RevisionDeleteEvent;
use EMS\CoreBundle\Event\RevisionFinalizeDraftEvent;
use EMS\CoreBundle\Event\RevisionPublishEvent;
use EMS\CoreBundle\Event\RevisionUnpublishEvent;
use EMS\CoreBundle\Event\ValidateWebhookSubscriptionEvent;
use EMS\CoreBundle\Service\WebhookService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

final readonly class EventsToWebhookSubscribers implements EventSubscriberInterface
{
    public function __construct(
        private WebhookService $webhookService,
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
            DispatchToWebhookEvent::class => 'onDispatchToWebhook',
            ValidateWebhookSubscriptionEvent::class => 'onValidateWebhookSubscription',
            AliasUpdateEvent::class => 'onAliasUpdateSubscription',
        ];
    }

    public function onRevisionPublished(RevisionPublishEvent $event): void
    {
        $this->webhookService->dispatch(\sprintf('content.published.%s', $event->getEnvironment()->getName()), [
            'environment' => $event->getEnvironment()->getName(),
            'alias' => $event->getEnvironment()->getAlias(),
            'content_type' => $event->getRevision()->giveContentType()->getName(),
            'ouuid' => $event->getRevision()->getOuuid(),
            'raw_data' => $event->getRevision()->getData(),
        ]);
    }

    public function onFinalizeDraft(RevisionFinalizeDraftEvent $event): void
    {
        $this->webhookService->dispatch('content.finalize', [
            'environment' => $event->getEnvironment()->getName(),
            'alias' => $event->getEnvironment()->getAlias(),
            'content_type' => $event->getRevision()->giveContentType()->getName(),
            'ouuid' => $event->getRevision()->getOuuid(),
            'raw_data' => $event->getRevision()->getData(),
        ]);
    }

    public function onUnpublish(RevisionUnpublishEvent $event): void
    {
        $this->webhookService->dispatch('content.unpublish', [
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
        $this->webhookService->disable($event, $message);
        $this->logger->error('webhook.subscriber.disabled', [
            'subscriptionId' => $message->subscriptionId,
            'event' => $message->eventName,
            'errorMessage' => $event->getThrowable()->getMessage(),
        ]);
    }

    public function onNewIndex(NewIndexEvent $event): void
    {
        $this->webhookService->dispatch(\sprintf('environment.new_index.%s', $event->getEnvironment()->getName()), [
            'environment' => $event->getEnvironment()->getName(),
            'index' => $event->getIndex(),
            'aliases' => $event->getAliases(),
            'old_index' => $event->getOldIndex(),
        ]);
    }

    public function onDelete(RevisionDeleteEvent $event): void
    {
        $this->webhookService->dispatch('content.delete', [
            'environment' => $event->getEnvironment()->getName(),
            'alias' => $event->getEnvironment()->getAlias(),
            'content_type' => $event->getRevision()->giveContentType()->getName(),
            'ouuid' => $event->getRevision()->getOuuid(),
        ]);
    }

    public function onDispatchToWebhook(DispatchToWebhookEvent $event): void
    {
        $this->webhookService->dispatch($event->name, $event->data);
    }

    public function onValidateWebhookSubscription(ValidateWebhookSubscriptionEvent $event): void
    {
        $this->webhookService->validate($event->webhookSubscription);
    }

    public function onAliasUpdateSubscription(AliasUpdateEvent $event): void
    {
        $this->webhookService->dispatch(\sprintf('alias.update.%s', $event->getAlias()), [
            'actions' => $event->getActions(),
        ]);
    }
}
