<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use EMS\CoreBundle\Entity\FieldType;

/**
 * @extends EntityRepository<FieldType>
 */
class FieldTypeRepository extends EntityRepository
{
}
