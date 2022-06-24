<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use EMS\CoreBundle\Entity\SortOption;

class SortOptionRepository extends \Doctrine\ORM\EntityRepository
{
    /**
     * @return SortOption[]
     */
    public function findAll(): array
    {
        return parent::findBy([], ['orderKey' => 'asc']);
    }
}
