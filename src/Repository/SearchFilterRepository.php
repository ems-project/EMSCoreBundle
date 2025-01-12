<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use EMS\CoreBundle\Entity\Form\SearchFilter;

/**
 * @extends EntityRepository<SearchFilter>
 */
class SearchFilterRepository extends EntityRepository
{
}
