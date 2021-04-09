<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use EMS\CoreBundle\Entity\QuerySearch;

final class QuerySearchRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, QuerySearch::class);
    }

    /**
     * @return QuerySearch[]
     */
    public function getAll(): array
    {
        return $this->findBy([], ['orderKey' => 'ASC']);
    }

    public function counter(): int
    {
        return parent::count([]);
    }

    public function create(QuerySearch $querySearch): void
    {
        $this->getEntityManager()->persist($querySearch);
        $this->getEntityManager()->flush();
    }

    public function delete(QuerySearch $querySearch): void
    {
        $this->getEntityManager()->remove($querySearch);
        $this->getEntityManager()->flush();
    }

    /**
     * @param string[] $ids
     *
     * @return QuerySearch[]
     */
    public function getByIds(array $ids): array
    {
        $queryBuilder = $this->createQueryBuilder('query_search');
        $queryBuilder->where('query_search.id IN (:ids)')
            ->setParameter('ids', $ids);

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return QuerySearch[]
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
