<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;

class JobRepository extends EntityRepository
{
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
}
