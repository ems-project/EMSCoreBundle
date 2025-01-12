<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use EMS\CoreBundle\Entity\SortOption;

/**
 * @extends EntityRepository<SortOption>
 *
 * @method SortOption[] findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
class SortOptionRepository extends EntityRepository
{
    /**
     * @return SortOption[]
     */
    #[\Override]
    public function findAll(): array
    {
        return parent::findBy([], ['orderKey' => 'asc']);
    }
}
