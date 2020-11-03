<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;

class AnalyzerRepository extends EntityRepository
{
    public function findByName($name)
    {
        return $this->findOneBy([
            'name' => $name,
        ]);
    }
}
