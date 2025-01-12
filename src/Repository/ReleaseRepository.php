<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\Release;

/**
 * @extends ServiceEntityRepository<Release>
 *
 * @method Release[] findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
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

    public function getById(int|string $id): Release
    {
        $queryBuilder = $this->createQueryBuilder('r');
        $queryBuilder->where('r.id = :id')
            ->setParameter('id', $id);

        return $queryBuilder->getQuery()->getSingleResult();
    }

    /**
     * @return Release[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('r')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['name', 'executionDate', 'created'])) {
            $qb->orderBy(\sprintf('r.%s', $orderField), $orderDirection);
        } else {
            $qb->orderBy('r.name', $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    /**
     * @return Release[]
     */
    public function getInWip(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('r')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $qb->where('r.status = :status')
            ->setParameters([
                'status' => Release::WIP_STATUS,
            ]);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['name', 'executionDate', 'created'])) {
            $qb->orderBy(\sprintf('r.%s', $orderField), $orderDirection);
        } else {
            $qb->orderBy('r.name', $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    /**
     * @return Release[]
     */
    public function findReadyAndDue(): array
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->andWhere($qb->expr()->eq('r.status', ':status'))
            ->andWhere($qb->expr()->lte('r.executionDate', ':dateTime'))
            ->setParameters([
                'status' => Release::READY_STATUS,
                'dateTime' => new \DateTime(),
            ]);

        return $qb->getQuery()->execute();
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        if (\strlen($searchValue) > 0) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('r.name', ':term'),
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }

    public function countWipReleases(): int
    {
        $qb = $this->createQueryBuilder('r');
        $qb->select('count(r.id)');
        $qb->where('r.status = :status')
            ->setParameters([
                'status' => Release::WIP_STATUS,
            ]);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
