<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use EMS\CoreBundle\Entity\Channel;
use EMS\CoreBundle\Exception\NotFoundException;

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

    public function counter(): int
    {
        return parent::count([]);
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

    /**
     * @return Channel[]
     */
    public function get(int $from, int $size): array
    {
        $query = $this->createQueryBuilder('c')
            ->orderBy('c.orderKey', 'ASC')
            ->setFirstResult($from)
            ->setMaxResults($size)
            ->getQuery();

        return $query->execute();
    }
}
