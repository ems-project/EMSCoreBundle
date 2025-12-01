<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api;

use EMS\CoreBundle\Event\ValidateWebhookSubscriptionEvent;
use EMS\CoreBundle\Service\WebhookSubscriptionService;
use EMS\Helpers\Standard\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class WebhookSubscriptionController extends AbstractController
{
    public function __construct(
        private readonly WebhookSubscriptionService $webhookSubscriptionService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function subscribe(Request $request): JsonResponse
    {
        $requestContent = Json::decode($request->getContent());
        $resolver = new OptionsResolver();
        $resolver->setRequired(['endpointUrl', 'events']);
        $resolver->setAllowedTypes('endpointUrl', 'string');
        $resolver->setAllowedTypes('events', 'array');
        /** @var array{endpointUrl: string, events: string[]} $request */
        $request = $resolver->resolve($requestContent);
        $subscription = $this->webhookSubscriptionService->create($request['endpointUrl'], $request['events']);
        $this->eventDispatcher->dispatch(new ValidateWebhookSubscriptionEvent($subscription));

        return new JsonResponse([
            'id' => $subscription->getId(),
            'secret' => $subscription->getSecret(),
        ]);
    }

    public function unsubscribe(string $id): JsonResponse
    {
        $this->webhookSubscriptionService->unsubscribe($id);

        return new JsonResponse([
            'success' => true,
            'id' => $id,
        ]);
    }
}
