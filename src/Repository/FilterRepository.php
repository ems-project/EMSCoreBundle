<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\Filter;

/**
 * @extends ServiceEntityRepository<Filter>
 *
 * @method Filter|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 */
class FilterRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Filter::class);
    }

    public function delete(Filter $filter): void
    {
        $this->getEntityManager()->remove($filter);
        $this->getEntityManager()->flush();
    }

    public function findByName(string $name): ?Filter
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function getById(string $id): Filter
    {
        if (null === $filter = $this->find($id)) {
            throw new \RuntimeException('Filter not found');
        }

        return $filter;
    }

    /**
     * @return Filter[]
     */
    public function getByIds(string ...$ids): array
    {
        $qb = $this->createQueryBuilder('f');
        $qb
            ->andWhere($qb->expr()->in('f.id', ':ids'))
            ->setParameter('ids', $ids);

        return $qb->getQuery()->getResult();
    }

    public function makeQueryBuilder(string $searchValue = ''): QueryBuilder
    {
        $qb = $this->createQueryBuilder('f');

        if ('' !== $searchValue) {
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('f.label', ':term'),
                    $qb->expr()->like('f.name', ':term'),
                ))
                ->setParameter(':term', '%'.\strtolower($searchValue).'%');
        }

        return $qb;
    }

    public function update(Filter $filter): void
    {
        $this->getEntityManager()->persist($filter);
        $this->getEntityManager()->flush();
    }
}
