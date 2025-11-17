<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Messenger\Handler;

use EMS\CoreBundle\Core\Messenger\Message\WebhookSubscriberMessage;
use EMS\CoreBundle\Repository\WebhookSubscriptionRepository;
use EMS\Helpers\Html\Headers;
use EMS\Helpers\Html\MimeTypes;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
readonly class WebhookSubscriptionHandler
{
    public function __construct(
        private WebhookSubscriptionRepository $repository,
        private HttpClientInterface $httpClient
    ) {
    }

    public function __invoke(WebhookSubscriberMessage $message): void
    {
        $subscription = $this->repository->find($message->subscriptionId);
        if (!$subscription || !$subscription->isEnabled()) {
            return;
        }

        $body = Json::encode($message->payload);
        $secret = $subscription->getSecret();
        $signature = \hash_hmac('sha256', $body, $secret);

        $this->httpClient->request('POST', $subscription->getEndpointUrl(), [
            'headers' => [
                Headers::CONTENT_TYPE => MimeTypes::APPLICATION_JSON->value,
                Headers::X_WEBHOOK_SIGNATURE => $signature,
                Headers::X_WEBHOOK_EVENT => $message->eventName,
                Headers::X_WEBHOOK_SUBSCRIPTION_ID => $message->subscriptionId,
            ],
            'body' => $body,
        ]);
    }
}
