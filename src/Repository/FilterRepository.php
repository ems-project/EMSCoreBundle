<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;

class FilterRepository extends EntityRepository
{
    public function findByName($name)
    {
        return $this->findOneBy([
            'name' => $name,
        ]);
    }
}
