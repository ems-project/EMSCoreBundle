<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use EMS\CoreBundle\Entity\SortOption;

/**
 * @extends EntityRepository<SortOption>
 *
 * @method SortOption[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SortOptionRepository extends EntityRepository
{
    /**
     * @return SortOption[]
     */
    public function findAll(): array
    {
        return parent::findBy([], ['orderKey' => 'asc']);
    }
}
