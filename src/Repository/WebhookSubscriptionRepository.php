<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    public function counter(string $searchValue = ''): int
    {
        $qb = $this->createQueryBuilder('c');
        $qb->select('count(c.id)');
        $this->addSearchFilters($qb, $searchValue);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        if ('' !== $searchValue) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('ws.id', ':term'),
                $qb->expr()->like('ws.events', ':term'),
                $qb->expr()->like('ws.endpoint_url', ':term')
            );
            $qb->where($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }

    /**
     * @param string[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        $queryBuilder = $this->createQueryBuilder('gws');
        $queryBuilder
            ->delete(WebhookSubscription::class, 'ws')
            ->where('ws.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }

    /**
     * @return WebhookSubscription[]
     */
    public function getAll(): array
    {
        return $this->findBy([]);
    }

    /**
     * @return WebhookSubscription[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('ws')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['id', 'endpoint_url', 'events'], true)) {
            $qb->orderBy(\sprintf('ws.%s', $orderField), $orderDirection);
        } else {
            $qb->orderBy('ws.created', $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    public function getById(string $id): WebhookSubscription
    {
        if (null === $webhookSubscription = $this->find($id)) {
            throw new \RuntimeException('Unexpected WebhookSubscription type');
        }

        return $webhookSubscription;
    }

    public function update(WebhookSubscription $webhookSubscription): void
    {
        $this->getEntityManager()->persist($webhookSubscription);
        $this->getEntityManager()->flush();
    }

    /**
     * @param string[] $events
     */
    public function findByEndpointUrlAndEvents(string $endpointUrl, array $events): ?WebhookSubscription
    {
        \sort($events);
        $qb = $this->createQueryBuilder('ws')
            ->where('ws.endpointUrl = :endpointUrl')
            ->setParameter(':endpointUrl', $endpointUrl);
        $result = $qb->getQuery()->execute();
        foreach ($result as $webhookSubscription) {
            if (!$webhookSubscription instanceof WebhookSubscription) {
                throw new \RuntimeException('Unexpected WebhookSubscription type');
            }
            $currentEvents = $webhookSubscription->getEvents();
            \sort($currentEvents);
            if ($events !== $currentEvents) {
                continue;
            }
            if ($webhookSubscription->isEnabled()) {
                return $webhookSubscription;
            }
            $webhookSubscription->setEnabled(true);
            $this->update($webhookSubscription);

            return $webhookSubscription;
        }

        return null;
    }
}
