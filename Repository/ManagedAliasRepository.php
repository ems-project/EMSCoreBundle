<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

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

        return $qb->execute()->fetchAll(\PDO::FETCH_COLUMN);
    }
}
