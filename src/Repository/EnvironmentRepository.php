<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;

/**
 * @extends EntityRepository<Environment>
 *
 * @method Environment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Environment|null findOneBy(array $criteria, array $orderBy = null)
 */
class EnvironmentRepository extends EntityRepository
{
    /**
     * @return Environment[]
     */
    public function findAll(): array
    {
        return $this->findBy([]);
    }

    /**
     * @param array<mixed> $criteria
     * @param array<mixed> $orderBy
     *
     * @return Environment[]
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null): array
    {
        if (empty($orderBy)) {
            $orderBy = ['orderKey' => 'asc'];
        }

        return parent::findBy($criteria, $orderBy, $limit, $offset);
    }

    public function findOneByName(string $name): ?Environment
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function findOneById(string $id): ?Environment
    {
        return $this->findOneBy(['id' => $id]);
    }

    /**
     * @return array<array{alias: string, name: string, managed: bool}>
     */
    public function findAllAliases(): array
    {
        $qb = $this->createQueryBuilder('e', 'e.alias');
        $qb->select('e.alias, e.name, e.managed');

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<array{environment: Environment, counter: int}>
     */
    public function getEnvironmentsStats(): array
    {
        $qb = $this->createQueryBuilder('e')
        ->select('e as environment', 'count(r) as counter')
        ->leftJoin('e.revisions', 'r')
        ->groupBy('e.id')
        ->orderBy('e.orderKey', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function getDeletedRevisionsPerEnvironment(Environment $environment): int
    {
        $qb = $this->createQueryBuilder('e');
        $qb->select('count(r) as counter')
            ->leftJoin('e.revisions', 'r')
            ->where($qb->expr()->eq('r.deleted', ':true'))
            ->andWhere($qb->expr()->eq('e', ':environment'))
            ->groupBy('e.id')
            ->orderBy('e.orderKey', 'ASC')
            ->setParameters([
                ':true' => true,
                ':environment' => $environment,
            ]);

        try {
            return (int) $qb->getQuery()->getSingleScalarResult();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function countRevisionPerEnvironment(Environment $env): int
    {
        $qb = $this->createQueryBuilder('e');

        $qb->select('count(r) as counter')
        ->where($qb->expr()->eq('e.id', $env->getId()))
        ->leftJoin('e.revisions', 'r')
        ->groupBy('e.id');

        try {
            return (int) $qb->getQuery()->getSingleScalarResult();
        } catch (NonUniqueResultException) {
            return 0;
        }
    }

    /**
     * @return array<array{alias: string}>
     */
    public function findManagedIndexes(): array
    {
        $qb = $this->createQueryBuilder('e');
        $qb->select('e.alias alias');
        $qb->where($qb->expr()->eq('e.managed', ':true'));
        $qb->setParameters([':true' => true]);
        $qb->orderBy('e.orderKey', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function findByName(string $name): ?Environment
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * @return array<mixed>
     */
    public function findAllAsAssociativeArray(string $field): array
    {
        $qb = $this->createQueryBuilder('e');
        $qb->select('e.'.$field.' key, e.name name, e.label label, e.color color, e.alias alias, e.managed managed, e.baseUrl baseUrl, e.circles circles');

        $out = [];
        $result = $qb->getQuery()->getResult();
        foreach ($result as $record) {
            $out[$record['key']] = [
                'color' => $record['color'],
                'name' => $record['name'],
                'label' => $record['label'] ?? $record['name'],
                'alias' => $record['alias'],
                'managed' => $record['managed'],
                'baseUrl' => $record['baseUrl'],
                'circles' => $record['circles'],
            ];
        }

        return $out;
    }

    /**
     * @return Environment[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('e')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['name', 'label'])) {
            $qb->orderBy(\sprintf('e.%s', $orderField), $orderDirection);
        } else {
            $qb->orderBy('e.orderKey', $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    public function counter(string $searchValue = ''): int
    {
        $qb = $this->createQueryBuilder('e');
        $qb->select('count(e.id)');
        $this->addSearchFilters($qb, $searchValue);

        try {
            return \intval($qb->getQuery()->getSingleScalarResult());
        } catch (NonUniqueResultException) {
            return 0;
        }
    }

    public function create(Environment $environment): void
    {
        $this->getEntityManager()->persist($environment);
        $this->getEntityManager()->flush();
    }

    public function delete(Environment $environment): void
    {
        $this->getEntityManager()->remove($environment);
        $this->getEntityManager()->flush();
    }

    /**
     * @return Collection<int, Environment>
     */
    public function findAllPublishedForRevision(Revision $revision): Collection
    {
        $qb = $this->createQueryBuilder('e');
        $qb
            ->join('e.revisions', 'r')
            ->join('r.contentType', 'c')
            ->andWhere($qb->expr()->eq('c.deleted', $qb->expr()->literal(false)))
            ->andWhere($qb->expr()->eq('c.active', $qb->expr()->literal(true)))
            ->andWhere($qb->expr()->eq('r.deleted', $qb->expr()->literal(false)))
            ->orderBy('e.orderKey', 'ASC');

        if (null !== $versionOuuid = $revision->getVersionUuid()) {
            $qb
                ->andWhere($qb->expr()->eq('r.versionUuid', ':version_ouuid'))
                ->setParameter('version_ouuid', $versionOuuid);
        } else {
            $qb
                ->andWhere($qb->expr()->eq('r.ouuid', ':ouuid'))
                ->setParameter('ouuid', $revision->getOuuid());
        }

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        if (\strlen($searchValue) > 0) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('e.label', ':term'),
                $qb->expr()->like('e.name', ':term')
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }
}
