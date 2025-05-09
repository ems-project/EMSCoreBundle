<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use EMS\CoreBundle\Entity\CacheAction;

/**
 * @extends ServiceEntityRepository<CacheAction>
 *
 * @method CacheAction|null find($id, $lockMode = null, $lockVersion = null)
 * @method CacheAction|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method CacheAction[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
class CacheActionRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, CacheAction::class);
    }

    public function save(CacheAction $cache): void
    {
        $this->getEntityManager()->persist($cache);
        $this->getEntityManager()->flush();
    }
}
