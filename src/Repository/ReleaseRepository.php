<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use EMS\CoreBundle\DBAL\ReleaseStatusEnumType;
use EMS\CoreBundle\Entity\Release;

final class ReleaseRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Release::class);
    }

    /**
     * @return Release[]
     */
    public function getAll(): array
    {
        return $this->findBy([]);
    }

    public function counter(): int
    {
        return parent::count([]);
    }

    public function create(Release $release): void
    {
        $this->getEntityManager()->persist($release);
        $this->getEntityManager()->flush();
    }

    public function delete(Release $release): void
    {
        $this->getEntityManager()->remove($release);
        $this->getEntityManager()->flush();
    }

    /**
     * @param string[] $ids
     *
     * @return Release[]
     */
    public function getByIds(array $ids): array
    {
        $queryBuilder = $this->createQueryBuilder('r');
        $queryBuilder->where('r.id IN (:ids)')
            ->setParameter('ids', $ids);

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return Release[]
     */
    public function get(int $from, int $size): array
    {
        $qb = $this->createQueryBuilder('r')
            ->setFirstResult($from)
            ->setMaxResults($size)
            ->getQuery();

        return $qb->execute();
    }

    /**
     * @return Release[]
     */
    public function findReady(): array
    {
        $format = 'Y-m-d H:i';
        $qb = $this->createQueryBuilder('r');
        $qb->where('r.status = :status')
        ->andWhere('r.executionDate <= :dateTime')
        ->setParameters([
            'status' => ReleaseStatusEnumType::READY_STATUS,
            'dateTime' => new \DateTime(),
        ]);

        return $qb->getQuery()->execute();
    }
}
