<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\Job;

class JobRepository extends EntityRepository
{
    public function findById(int $jobId): Job
    {
        $job = $this->findOneBy(['id' => $jobId]);

        if (!$job instanceof Job) {
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
            ->getSingleScalarResult());
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

    public function save(Job $job): void
    {
        $this->getEntityManager()->persist($job);
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
