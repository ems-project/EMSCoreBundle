<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use EMS\CommonBundle\Entity\Log;

class LogRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Log::class);
    }

    public function counter(string $searchValue = ''): int
    {
        $qb = $this->createQueryBuilder('log');
        $qb->select('count(log.id)');
        $this->addSearchFilters($qb, $searchValue);

        try {
            return \intval($qb->getQuery()->getSingleScalarResult());
        } catch (NonUniqueResultException $e) {
            return 0;
        }
    }

    public function create(Log $log): void
    {
        $this->getEntityManager()->persist($log);
        $this->getEntityManager()->flush();
    }

    public function delete(Log $log): void
    {
        $this->getEntityManager()->remove($log);
        $this->getEntityManager()->flush();
    }

    /**
     * @param string[] $ids
     *
     * @return Log[]
     */
    public function getByIds(array $ids): array
    {
        $queryBuilder = $this->createQueryBuilder('log');
        $queryBuilder->where('log.id IN (:ids)')
            ->setParameter('ids', $ids);

        return $queryBuilder->getQuery()->getResult();
    }

    public function getById(string $id): Log
    {
        $schedule = $this->find($id);
        if (!$schedule instanceof Log) {
            throw new \RuntimeException('Unexpected log type');
        }

        return $schedule;
    }

    /**
     * @return Log[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('log')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['created', 'message', 'level', 'level_name', 'channel', 'formatted', 'username'])) {
            $qb->orderBy(\sprintf('log.%s', $orderField), $orderDirection);
        } else {
            $qb->orderBy('log.orderKey', $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        if (\strlen($searchValue) > 0) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('log.message', ':term'),
                $qb->expr()->like('log.level_name', ':term'),
                $qb->expr()->like('log.username', ':term')
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }
}
