<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use EMS\CoreBundle\Entity\SearchFieldOption;

class SearchFieldOptionRepository extends \Doctrine\ORM\EntityRepository
{
    /**
     * @return SearchFieldOption[]
     */
    public function findAll(): array
    {
        return parent::findBy([], ['orderKey' => 'asc']);
    }
}
