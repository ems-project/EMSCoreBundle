<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\Schedule;

class ScheduleRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Schedule::class);
    }

    /**
     * @return Schedule[]
     */
    public function getAll(): array
    {
        return $this->findBy([], ['orderKey' => 'ASC']);
    }

    public function counter(string $searchValue = ''): int
    {
        $qb = $this->createQueryBuilder('schedule');
        $qb->select('count(schedule.id)');
        $this->addSearchFilters($qb, $searchValue);

        try {
            return \intval($qb->getQuery()->getSingleScalarResult());
        } catch (NonUniqueResultException $e) {
            return 0;
        }
    }

    public function create(Schedule $schedule): void
    {
        $this->getEntityManager()->persist($schedule);
        $this->getEntityManager()->flush();
    }

    public function delete(Schedule $schedule): void
    {
        $this->getEntityManager()->remove($schedule);
        $this->getEntityManager()->flush();
    }

    /**
     * @param string[] $ids
     *
     * @return Schedule[]
     */
    public function getByIds(array $ids): array
    {
        $queryBuilder = $this->createQueryBuilder('schedule');
        $queryBuilder->where('schedule.id IN (:ids)')
            ->setParameter('ids', $ids);

        return $queryBuilder->getQuery()->getResult();
    }

    public function getById(string $id): Schedule
    {
        $schedule = $this->find($id);
        if (!$schedule instanceof Schedule) {
            throw new \RuntimeException('Unexpected schedule type');
        }

        return $schedule;
    }

    /**
     * @return Schedule[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('schedule')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['name', 'cron', 'command', 'previousRun', 'nextRun'])) {
            $qb->orderBy(\sprintf('schedule.%s', $orderField), $orderDirection);
        } else {
            $qb->orderBy('schedule.orderKey', $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        if (\strlen($searchValue) > 0) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('schedule.name', ':term'),
                $qb->expr()->like('schedule.cron', ':term'),
                $qb->expr()->like('schedule.command', ':term')
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }
}
