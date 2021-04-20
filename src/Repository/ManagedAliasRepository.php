<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\ORM\EntityRepository;

/**
 * Managed Alias Repository.
 */
class ManagedAliasRepository extends EntityRepository
{
    /**
     * @return array
     */
    public function findAllAliases()
    {
        $conn = $this->getEntityManager()->getConnection();
        $qb = $conn->createQueryBuilder();
        $qb->addSelect('alias')->from('managed_alias');
        $result = $qb->execute();
        if (!$result instanceof ResultStatement) {
            throw new \RuntimeException('Unexpected ResultStatement type');
        }

        return $result->fetchFirstColumn();
    }
}
