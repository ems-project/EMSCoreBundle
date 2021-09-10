<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
        $queryBuilder = $this->createQueryBuilder('release');
        $queryBuilder->where('release.id IN (:ids)')
            ->setParameter('ids', $ids);

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return Release[]
     */
    public function get(int $from, int $size): array
    {
        $query = $this->createQueryBuilder('c')
            ->setFirstResult($from)
            ->setMaxResults($size)
            ->getQuery();

        return $query->execute();
    }

//     public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
//     {
//         $qb = $this->createQueryBuilder('c')
//         ->setFirstResult($from)
//         ->setMaxResults($size);
//         $this->addSearchFilters($qb, $searchValue);

//         if (\in_array($orderField, ['label', 'name', 'alias', 'public'])) {
//             $qb->orderBy(\sprintf('c.%s', $orderField), $orderDirection);
//         } else {
//             $qb->orderBy('c.orderKey', $orderDirection);
//         }

//         return $qb->getQuery()->execute();
//     }
}
