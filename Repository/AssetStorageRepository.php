<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\NonUniqueResultException;
use EMS\CoreBundle\Entity\AssetStorage;
use Exception;

/**
 * AssetStorageRepository
 *
 */
class AssetStorageRepository extends \Doctrine\ORM\EntityRepository
{

    /**
     * @param string $hash
     * @param false|string $context
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function getQuery($hash, $context)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->where($qb->expr()->eq('a.hash', ':hash'));

        if($context)
        {
            $qb->andWhere($qb->expr()->eq('a.context', ':context'));
            $qb->setParameters([
                ':hash' => $hash,
                ':context' => $context,
            ]);
        }
        else{
            $qb->andWhere('a.context is null');
            $qb->setParameters([
                ':hash' => $hash,
            ]);
        }
        return $qb;
    }

    /**
     * @param string $hash
     * @param string $context
     * @return bool
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function head($hash, $context)
    {
        try{
            $qb = $this->getQuery($hash, $context)->select('count(a.hash)');
            return $qb->getQuery()->getSingleScalarResult() !== 0;
        }
        catch (NonUniqueResultException $e){
            return false;
        }
    }

    /**
     * @return bool
     */
    public function clearCache()
    {
        try
        {
            $qb = $this->createQueryBuilder('asset')->delete()
                ->where('asset.context is not null');
            return $qb->getQuery()->execute() !== false;
        }
        catch (Exception $e){
            return false;
        }
    }

    /**
     * @return bool
     */
    public function removeByHash($hash)
    {
        try
        {
            $qb = $this->createQueryBuilder('asset')->delete();
            $qb->where($qb->expr()->eq('asset.hash', ':hash'));
            $qb->setParameters([
                ':hash' => $hash,
            ]);
            return $qb->getQuery()->execute() !== false;
        }
        catch (Exception $e){
            return false;
        }
    }

    /**
     * @param string $hash
     * @param string $context
     * @return null|AssetStorage
     */
    public function findByHash($hash, $context)
    {
        $qb = $this->getQuery($hash, $context)->select('a');

        try {
            return $qb->getQuery()->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            return null;
        }
    }

    /**
     * @param string $hash
     * @param string $context
     * @return int
     */
    public function getSize($hash, $context)
    {
        try
        {
            $qb = $this->getQuery($hash, $context)->select('a.size');
            return $qb->getQuery()->getSingleScalarResult();
        }
        catch (NonUniqueResultException $e){
            return false;
        }
    }

    /**
     * @param string $hash
     * @param string $context
     * @return int
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getLastUpdateDate($hash, $context)
    {
        try
        {
            $qb = $this->getQuery($hash, $context)->select('a.lastUpdateDate');
            return $qb->getQuery()->getSingleScalarResult();
        }
        catch (NonUniqueResultException $e){
            return false;
        }
    }
}
