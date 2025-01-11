<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\Analyzer;

/**
 * @extends ServiceEntityRepository<Analyzer>
 *
 * @method Analyzer|null findOneBy(array $criteria, array $orderBy = null)
 */
class AnalyzerRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Analyzer::class);
    }

    public function delete(Analyzer $analyzer): void
    {
        $this->getEntityManager()->remove($analyzer);
        $this->getEntityManager()->flush();
    }

    /**
     * @return Analyzer[]
     */
    #[\Override]
    public function findAll(): array
    {
        return $this->findBy([], ['orderKey' => 'asc']);
    }

    public function findByName(string $name): ?Analyzer
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function getById(string $id): Analyzer
    {
        if (null === $analyzer = $this->find($id)) {
            throw new \RuntimeException('Analyzer not found');
        }

        return $analyzer;
    }

    /**
     * @return Analyzer[]
     */
    public function getByIds(string ...$ids): array
    {
        $qb = $this->createQueryBuilder('a');
        $qb
            ->andWhere($qb->expr()->in('a.id', ':ids'))
            ->setParameter('ids', $ids);

        return $qb->getQuery()->getResult();
    }

    public function makeQueryBuilder(string $searchValue = ''): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a');

        if ('' !== $searchValue) {
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('a.label', ':term'),
                    $qb->expr()->like('a.name', ':term'),
                ))
                ->setParameter(':term', '%'.\strtolower($searchValue).'%');
        }

        return $qb;
    }

    public function update(Analyzer $analyzer): void
    {
        $this->getEntityManager()->persist($analyzer);
        $this->getEntityManager()->flush();
    }
}
