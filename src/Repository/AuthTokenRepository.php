<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use EMS\CoreBundle\Entity\AuthToken;

/**
 * @extends EntityRepository<AuthToken>
 */
class AuthTokenRepository extends EntityRepository
{
}
