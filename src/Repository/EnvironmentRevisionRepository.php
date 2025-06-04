<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use EMS\CoreBundle\Entity\EnvironmentRevision;

/**
 * @extends ServiceEntityRepository<EnvironmentRevision>
 *
 * @method EnvironmentRevision|null find($id, $lockMode = null, $lockVersion = null)
 * @method EnvironmentRevision|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method EnvironmentRevision[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
class EnvironmentRevisionRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, EnvironmentRevision::class);
    }

    /**
     * @return array<int, int>
     */
    public function countDocumentsByEnvironments(bool $deleted = false, bool $managed = true): array
    {
        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb
            ->select('er.environment_id', 'count(er.revision_id)')
            ->from('environment_revision', 'er')
            ->join('er', 'revision', 'r', 'r.id = er.revision_id')
            ->join('er', 'environment', 'e', 'e.id = er.environment_id')
            ->andWhere($qb->expr()->eq('e.managed', ':managed'))
            ->setParameter('managed', $managed, ParameterType::BOOLEAN)
            ->groupBy('er.environment_id');

        if ($deleted) {
            $qb
                ->andWhere($qb->expr()->isNotNull('er.deleted'))
                ->andWhere($qb->expr()->isNull('r.end_time'))
                ->andWhere($qb->expr()->eq('r.deleted', ':deleted'))
                ->setParameter('deleted', $deleted, ParameterType::BOOLEAN);
        } else {
            $qb->andWhere($qb->expr()->isNull('er.deleted'));
        }

        /** @var array<int, int> $result */
        $result = $qb->fetchAllKeyValue();

        return $result;
    }
}
