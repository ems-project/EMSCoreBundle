<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\Job;

/**
 * @extends EntityRepository<Job>
 *
 * @method Job|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 */
class JobRepository extends EntityRepository
{
    public function findById(int $jobId): Job
    {
        if (null === $job = $this->findOneBy(['id' => $jobId])) {
            throw new \RuntimeException('Job not found');
        }

        return $job;
    }

    public function countJobs(string $searchValue = ''): int
    {
        $qb = $this->createQueryBuilder('job');
        $this->addSearchFilters($qb, $searchValue);

        return \intval(
            $qb->select('COUNT(job)')
            ->getQuery()
            ->getSingleScalarResult()
        );
    }

    public function countPendingJobs(): int
    {
        $qb = $this->createQueryBuilder('job')->select('COUNT(job)');
        $qb->where($qb->expr()->eq('job.done', ':false'));
        $qb->setParameters([
            ':false' => false,
        ]);

        return \intval($qb->getQuery()->getSingleScalarResult());
    }

    /**
     * @return Job[]
     */
    public function getByIds(string ...$ids): array
    {
        $qb = $this->createQueryBuilder('j');
        $qb
            ->andWhere($qb->expr()->in('j.id', ':ids'))
            ->setParameter('ids', $ids);

        return $qb->getQuery()->getResult();
    }

    public function save(Job $job): void
    {
        $this->getEntityManager()->persist($job);
        $this->getEntityManager()->flush();
    }

    public function delete(Job $job): void
    {
        $this->getEntityManager()->remove($job);
        $this->getEntityManager()->flush();
    }

    public function clean(string $username, \DateTimeInterface $olderDate): int
    {
        $qb = $this->createQueryBuilder('job')->delete();
        $qb->where($qb->expr()->eq('job.done', ':true'));
        $qb->andWhere($qb->expr()->eq('job.user', ':username'));
        $qb->andWhere($qb->expr()->lt('job.modified', ':olderDate'));
        $qb->setParameters([
            ':true' => true,
            ':olderDate' => $olderDate,
            ':username' => $username,
        ]);

        return \intval($qb->getQuery()->execute());
    }

    /**
     * @return Job[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('job')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['username', 'command', 'created', 'modified'])) {
            $qb->orderBy(\sprintf('job.%s', $orderField), $orderDirection);
        } else {
            $qb->orderBy('job.created', $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        if (\strlen($searchValue) > 0) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('job.username', ':term'),
                $qb->expr()->like('job.command', ':term'),
                $qb->expr()->like('job.output', ':term')
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }
}
