<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use EMS\CoreBundle\Entity\I18n;

class I18nRepository extends EntityRepository
{
    public function countWithFilter(?string $identifier): int
    {
        $qb = $this->createQueryBuilder('i')
        ->select('COUNT(i)');

        if (null != $identifier) {
            $qb->where('i.identifier LIKE :identifier')
            ->setParameter('identifier', '%'.$identifier.'%');
        }

        return $qb->getQuery()
        ->getSingleScalarResult();
    }

    /**
     * @return iterable|I18n[]
     */
    public function findByWithFilter(int $limit, int $from, ?string $identifier): iterable
    {
        $qb = $this->createQueryBuilder('i')
        ->select('i');

        if (null != $identifier) {
            $qb->where('i.identifier LIKE :identifier')
            ->setParameter('identifier', '%'.$identifier.'%');
        }

        $qb->orderBy('i.identifier', 'ASC')
        ->setFirstResult($from)
        ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }
}
