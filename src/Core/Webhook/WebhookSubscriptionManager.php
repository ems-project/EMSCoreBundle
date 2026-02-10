<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Webhook;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Entity\WebhookSubscription;
use EMS\CoreBundle\Repository\WebhookSubscriptionRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;

readonly class WebhookSubscriptionManager implements EntityServiceInterface
{
    public function __construct(
        private WebhookSubscriptionRepository $webhookSubscriptionRepository,
    ) {
    }

    /**
     * @return WebhookSubscription[]
     */
    public function getAll(): array
    {
        return $this->webhookSubscriptionRepository->getAll();
    }

    public function isSortable(): bool
    {
        return false;
    }

    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->webhookSubscriptionRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    public function getEntityName(): string
    {
        return 'webhook-subscription';
    }

    public function getAliasesName(): array
    {
        return [
            'Webhook-Subscriptions',
            'WebhookSubscriptions',
            'WebhookSubscription',
        ];
    }

    public function count(string $searchValue = '', mixed $context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->webhookSubscriptionRepository->counter($searchValue);
    }

    public function getByItemName(string $name): ?WebhookSubscription
    {
        return $this->getByItemId($name);
    }

    public function getByItemId(string $id): ?WebhookSubscription
    {
        return $this->webhookSubscriptionRepository->getById($id);
    }

    public function updateEntityFromJson(EntityInterface $entity, string $json): never
    {
        throw new \RuntimeException('Webhooks can be serialized');
    }

    public function createEntityFromJson(string $json, ?string $name = null): never
    {
        throw new \RuntimeException('Webhooks can be serialized');
    }

    public function deleteByItemName(string $name): string
    {
        $this->webhookSubscriptionRepository->deleteByIds([$name]);

        return $name;
    }

    public function delete(WebhookSubscription $WebhookSubscription): void
    {
        $this->webhookSubscriptionRepository->deleteByIds([$WebhookSubscription->getId()]);
    }

    /**
     * @param string[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        $this->webhookSubscriptionRepository->deleteByIds($ids);
    }
}
