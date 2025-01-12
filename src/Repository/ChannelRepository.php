<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\Channel;
use EMS\CoreBundle\Exception\NotFoundException;

/**
 * @extends ServiceEntityRepository<Channel>
 *
 * @method Channel|null find($id, $lockMode = null, $lockVersion = null)
 * @method Channel|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method Channel[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
final class ChannelRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Channel::class);
    }

    public function findRegistered(string $channelName): Channel
    {
        $qb = $this->createQueryBuilder('c');
        $qb
            ->andWhere($qb->expr()->eq('c.name', ':channel_name'))
            ->andWhere($qb->expr()->isNotNull('c.alias'))
            ->setParameter('channel_name', $channelName);

        if (null === $channel = $qb->getQuery()->getOneOrNullResult()) {
            throw NotFoundException::channelByName($channelName);
        }

        return $channel;
    }

    /**
     * @return Channel[]
     */
    public function getAll(): array
    {
        return $this->findBy([], ['orderKey' => 'ASC']);
    }

    public function counter(string $searchValue = ''): int
    {
        $qb = $this->createQueryBuilder('c');
        $qb->select('count(c.id)');
        $this->addSearchFilters($qb, $searchValue);

        try {
            return \intval($qb->getQuery()->getSingleScalarResult());
        } catch (NonUniqueResultException) {
            return 0;
        }
    }

    public function create(Channel $channel): void
    {
        $this->getEntityManager()->persist($channel);
        $this->getEntityManager()->flush();
    }

    public function delete(Channel $channel): void
    {
        $this->getEntityManager()->remove($channel);
        $this->getEntityManager()->flush();
    }

    /**
     * @param string[] $ids
     *
     * @return Channel[]
     */
    public function getByIds(array $ids): array
    {
        $queryBuilder = $this->createQueryBuilder('channel');
        $queryBuilder->where('channel.id IN (:ids)')
            ->setParameter('ids', $ids);

        return $queryBuilder->getQuery()->getResult();
    }

    public function getById(string $id): Channel
    {
        if (null === $channel = $this->find($id)) {
            throw new \RuntimeException('Unexpected channel type');
        }

        return $channel;
    }

    /**
     * @return Channel[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('c')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['label', 'name', 'alias', 'public'])) {
            $qb->orderBy(\sprintf('c.%s', $orderField), $orderDirection);
        } else {
            $qb->orderBy('c.orderKey', $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        if (\strlen($searchValue) > 0) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('c.label', ':term'),
                $qb->expr()->like('c.name', ':term'),
                $qb->expr()->like('c.alias', ':term')
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }

    public function getByName(string $name): ?Channel
    {
        return $this->findOneBy(['name' => $name]);
    }
}
