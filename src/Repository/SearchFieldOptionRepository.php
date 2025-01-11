<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use EMS\CoreBundle\Entity\SearchFieldOption;

/**
 * @extends EntityRepository<SearchFieldOption>
 *
 * @method SearchFieldOption[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SearchFieldOptionRepository extends EntityRepository
{
    /**
     * @return SearchFieldOption[]
     */
    #[\Override]
    public function findAll(): array
    {
        return parent::findBy([], ['orderKey' => 'asc']);
    }
}
