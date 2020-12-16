<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use EMS\CoreBundle\Entity\Channel;

final class ChannelRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Channel::class);
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
}
