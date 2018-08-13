<?php

namespace EMS\CoreBundle\Repository;

use EMS\CoreBundle\Entity\AssetStorage;

/**
 * AssetStorageRepository
 *
 */
class AssetStorageRepository extends \Doctrine\ORM\EntityRepository
{
    /**
     * @param string $hash
     * @param string $context
     * @return bool
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function head($hash, $context)
    {
        $qb = $this->createQueryBuilder('a')
            ->select('count(a.hash)')
            ->where('a.hash = :hash')
            ->andWhere('a.context = :context');

        $qb->setParameters([
            'hash' => $hash,
            'context' => $context?$context:null,
        ]);

        return $qb->getQuery()->getSingleScalarResult() !== 0;
    }

    /**
     * @param string $hash
     * @param string $context
     * @return null|AssetStorage
     */
    public function findByHash($hash, $context)
    {

        return $this->findOneBy([
            'hash' => $hash,
            'context' => ($context?$context:null),
        ]);
    }

    /**
     * @param string $hash
     * @param string $context
     * @return int
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getSize($hash, $context)
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a.size')
            ->where('a.hash = :hash')
            ->andWhere('a.context = :context');

        $qb->setParameters([
            'hash' => $hash,
            'context' => $context?$context:null,
        ]);


        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param string $hash
     * @param string $context
     * @return int
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getLastUpdateDate($hash, $context)
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a.lastUpdateDate')
            ->where('a.hash = :hash')
            ->andWhere('a.context = :context');

        $qb->setParameters([
            'hash' => $hash,
            'context' => $context?$context:null,
        ]);


        return $qb->getQuery()->getSingleScalarResult();
    }
}
