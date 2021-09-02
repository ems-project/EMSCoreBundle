<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use EMS\CoreBundle\Entity\Job;

class JobRepository extends EntityRepository
{
    public function findById(int $jobId): Job
    {
        $qb = $this->createQueryBuilder('j');
        $query = $qb
            ->andWhere($qb->expr()->eq('j.id', ':job_id'))
            ->setParameter('job_id', $jobId)
            ->getQuery();

        $job = $query->getSingleResult();

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
}
