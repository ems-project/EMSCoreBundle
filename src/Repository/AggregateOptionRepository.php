<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use EMS\CoreBundle\Entity\AggregateOption;

class AggregateOptionRepository extends \Doctrine\ORM\EntityRepository
{
    /**
     * @return AggregateOption[]
     */
    public function findAll(): array
    {
        return parent::findBy([], ['orderKey' => 'asc']);
    }
}
