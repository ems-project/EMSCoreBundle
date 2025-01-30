<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\QuerySearch;

/**
 * @extends ServiceEntityRepository<QuerySearch>
 *
 * @method QuerySearch|null find($id, $lockMode = null, $lockVersion = null)
 * @method QuerySearch[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
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
            ->setParameter('ids', $ids, ArrayParameterType::STRING);

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return QuerySearch[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('c')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['label', 'name'])) {
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
                $qb->expr()->like('c.name', ':term')
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }

    public function getById(string $id): QuerySearch
    {
        if (null === $querySearch = $this->find($id)) {
            throw new \RuntimeException('Unexpected querySearch type');
        }

        return $querySearch;
    }
}
