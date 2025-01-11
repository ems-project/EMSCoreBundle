<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use EMS\CoreBundle\Entity\AggregateOption;

/**
 * @extends EntityRepository<AggregateOption>
 */
class AggregateOptionRepository extends EntityRepository
{
    /**
     * @return AggregateOption[]
     */
    #[\Override]
    public function findAll(): array
    {
        return parent::findBy([], ['orderKey' => 'asc']);
    }
}
