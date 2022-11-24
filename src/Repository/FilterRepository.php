<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\Filter;

/**
 * @extends ServiceEntityRepository<Filter>
 *
 * @method Filter|null findOneBy(array $criteria, array $orderBy = null)
 */
class FilterRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Filter::class);
    }

    public function findByName(string $name): ?Filter
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function update(Filter $filter): void
    {
        $this->getEntityManager()->persist($filter);
        $this->getEntityManager()->flush();
    }

    /**
     * @return Filter[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('filter')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['label', 'name'])) {
            $qb->orderBy(\sprintf('filter.%s', $orderField), $orderDirection);
        } else {
            $qb->orderBy('filter.orderKey', $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    public function counter(string $searchValue = ''): int
    {
        $qb = $this->createQueryBuilder('filter');
        $qb->select('count(filter.id)');
        $this->addSearchFilters($qb, $searchValue);

        return \intval($qb->getQuery()->getSingleScalarResult());
    }

    public function delete(Filter $filter): void
    {
        $this->getEntityManager()->remove($filter);
        $this->getEntityManager()->flush();
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        if (\strlen($searchValue) > 0) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('filter.label', ':term'),
                $qb->expr()->like('filter.name', ':term'),
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }
}
