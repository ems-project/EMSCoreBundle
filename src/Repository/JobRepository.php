<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;

class JobRepository extends EntityRepository
{
    /**
     * @return string
     */
    public function countJobs()
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
