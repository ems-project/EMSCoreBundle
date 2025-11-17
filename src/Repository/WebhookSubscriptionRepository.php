<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use EMS\CoreBundle\Entity\WebhookSubscription;

/**
 * @extends ServiceEntityRepository<WebhookSubscription>
 *
 * @method WebhookSubscription|null find($id, $lockMode = null, $lockVersion = null)
 * @method WebhookSubscription|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method WebhookSubscription[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
class WebhookSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, WebhookSubscription::class);
    }

    /**
     * @param string[] $events
     */
    public function create(string $endpointUrl, array $events, string $secret): WebhookSubscription
    {
        $subscription = new WebhookSubscription();
        $subscription->setEndpointUrl($endpointUrl);
        $subscription->setEvents($events);
        $subscription->setSecret($secret);
        $this->getEntityManager()->persist($subscription);
        $this->getEntityManager()->flush();

        return $subscription;
    }

    /**
     * @return WebhookSubscription[]
     */
    public function findEnabled(): array
    {
        return $this->findBy(['enabled' => true]);
    }

    public function disable(string $subscriptionId, string $errorMessage): void
    {
        $subscription = $this->find($subscriptionId);
        if (!$subscription) {
            return;
        }
        $subscription->setEnabled(false);
        $subscription->setErrorMessage($errorMessage);
        $this->getEntityManager()->persist($subscription);
        $this->getEntityManager()->flush();
    }
}
