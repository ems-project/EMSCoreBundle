<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
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

    public function counter(string $searchValue = ''): int
    {
        $qb = $this->createQueryBuilder('ma');
        $qb->select('count(ma.id)');
        $this->addSearchFilters($qb, $searchValue);

        try {
            return (int) $qb->getQuery()->getSingleScalarResult();
        } catch (NonUniqueResultException) {
            return 0;
        }
    }

    /**
     * @return ManagedAlias[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('ma')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['name', 'label'])) {
            $qb->orderBy(\sprintf('ma.%s', $orderField), $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    public function delete(ManagedAlias $managedAlias): void
    {
        $this->getEntityManager()->remove($managedAlias);
        $this->getEntityManager()->flush();
    }

    public function findByName(string $name): ?ManagedAlias
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function update(ManagedAlias $managedAlias): void
    {
        $this->getEntityManager()->persist($managedAlias);
        $this->getEntityManager()->flush();
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        if (\strlen($searchValue) > 0) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('ma.label', ':term'),
                $qb->expr()->like('ma.name', ':term'),
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }
}
