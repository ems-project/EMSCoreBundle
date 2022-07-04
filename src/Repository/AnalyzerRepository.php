<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\Analyzer;

class AnalyzerRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Analyzer::class);
    }

    public function findByName(string $name): ?Analyzer
    {
        $analyzer = $this->findOneBy([
            'name' => $name,
        ]);
        if (null !== $analyzer && !$analyzer instanceof Analyzer) {
            throw new \RuntimeException('Unexpected analyzer type');
        }

        return $analyzer;
    }

    public function update(Analyzer $analyzer): void
    {
        $this->getEntityManager()->persist($analyzer);
        $this->getEntityManager()->flush();
    }

    /**
     * @return Analyzer[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('analyzer')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['label', 'name'])) {
            $qb->orderBy(\sprintf('analyzer.%s', $orderField), $orderDirection);
        } else {
            $qb->orderBy('analyzer.orderKey', $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    public function counter(string $searchValue = ''): int
    {
        $qb = $this->createQueryBuilder('analyzer');
        $qb->select('count(analyzer.id)');
        $this->addSearchFilters($qb, $searchValue);

        return \intval($qb->getQuery()->getSingleScalarResult());
    }

    public function delete(Analyzer $analyzer): void
    {
        $this->getEntityManager()->remove($analyzer);
        $this->getEntityManager()->flush();
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        if (\strlen($searchValue) > 0) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('analyzer.label', ':term'),
                $qb->expr()->like('analyzer.name', ':term'),
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }
}
