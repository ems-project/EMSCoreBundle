<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
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

    public function countJobs(): int
    {
        return \intval($this->createQueryBuilder('a')
            ->select('COUNT(a)')
            ->getQuery()
            ->getSingleScalarResult());
    }

    public function countPendingJobs(): int
    {
        $qb = $this->createQueryBuilder('a')->select('COUNT(a)');
        $qb->where($qb->expr()->eq('a.done', ':false'));
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
}
