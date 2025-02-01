<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use EMS\CommonBundle\Entity\Log;
use EMS\CoreBundle\Core\Log\LogEntityTableContext;

/**
 * @extends ServiceEntityRepository<Log>
 *
 * @method Log|null find($id, $lockMode = null, $lockVersion = null)
 */
class LogRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Log::class);
    }

    public function counter(LogEntityTableContext $context): int
    {
        $qb = $this->createQueryBuilder('log');
        $qb->select('count(log.id)');
        $this->addSearchFilters($qb, $context);

        try {
            return (int) $qb->getQuery()->getSingleScalarResult();
        } catch (NonUniqueResultException) {
            return 0;
        }
    }

    public function create(Log $log): void
    {
        $this->getEntityManager()->persist($log);
        $this->getEntityManager()->flush();
    }

    public function delete(Log $log): void
    {
        $this->getEntityManager()->remove($log);
        $this->getEntityManager()->flush();
    }

    /**
     * @param string[] $ids
     *
     * @return Log[]
     */
    public function getByIds(array $ids): array
    {
        $queryBuilder = $this->createQueryBuilder('log');
        $queryBuilder->where('log.id IN (:ids)')
            ->setParameter('ids', $ids, ArrayParameterType::STRING);

        return $queryBuilder->getQuery()->getResult();
    }

    public function getById(string $id): Log
    {
        if (null === $schedule = $this->find($id)) {
            throw new \RuntimeException('Unexpected log type');
        }

        return $schedule;
    }

    /**
     * @return Log[]
     */
    public function get(LogEntityTableContext $context): array
    {
        $qb = $this->createQueryBuilder('log')
            ->setFirstResult($context->from)
            ->setMaxResults($context->size);
        $this->addSearchFilters($qb, $context);

        if (\in_array($context->orderField, ['created', 'message', 'level', 'levelName', 'channel', 'formatted', 'username'])) {
            $qb->orderBy(\sprintf('log.%s', $context->orderField), $context->orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    private function addSearchFilters(QueryBuilder $qb, LogEntityTableContext $context): void
    {
        if (\strlen($context->searchValue) > 0) {
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('log.message', ':term'),
                    $qb->expr()->like('log.levelName', ':term'),
                    $qb->expr()->like('log.username', ':term')
                ))
                ->setParameter(':term', '%'.$context->searchValue.'%');
        }

        if (null !== $revision = $context->revision) {
            $qb
                ->andWhere($qb->expr()->eq('log.ouuid', ':ouuid'))
                ->setParameter('ouuid', $revision->giveOuuid());
        }

        if (\count($context->channels) > 0) {
            $qb
                ->andWhere($qb->expr()->in('log.channel', ':channels'))
                ->setParameter('channels', $context->channels, Types::SIMPLE_ARRAY);
        }
    }
}
