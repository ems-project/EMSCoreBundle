<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use EMS\CoreBundle\Entity\ManagedAlias;

/**
 * @extends EntityRepository<ManagedAlias>
 */
class ManagedAliasRepository extends EntityRepository
{
    /**
     * @return string[]
     */
    public function findAllAliases(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $qb = $conn->createQueryBuilder();
        $qb->addSelect('alias')->from('managed_alias');
        $result = $qb->executeQuery();

        return $result->fetchFirstColumn();
    }
}
