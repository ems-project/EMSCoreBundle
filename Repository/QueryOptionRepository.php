<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;

class QueryOptionRepository extends EntityRepository
{
    public function findAll()
    {
        return parent::findBy([], ['orderKey' => 'asc']);
    }
}
