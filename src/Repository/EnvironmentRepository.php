<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;

/**
 * @extends EntityRepository<Environment>
 *
 * @method Environment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Environment|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 */
class EnvironmentRepository extends EntityRepository
{
    /**
     * @return Environment[]
     */
    #[\Override]
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
    #[\Override]
    public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null): array
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
     * @return array<int, int>
     */
    public function countRevisionsById(?bool $deleted = null): array
    {
        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb
            ->select('e.id', 'count(er.revision_id)')
            ->from('environment', 'e')
            ->leftJoin('e', 'environment_revision', 'er', 'e.id = er.environment_id')
            ->join('er', 'revision', 'r', 'r.id = er.revision_id')
            ->andWhere($qb->expr()->eq('e.managed', ':managed'))
            ->setParameter('managed', true, ParameterType::BOOLEAN)
            ->groupBy('e.id');

        if ($deleted) {
            $qb
                ->andWhere($qb->expr()->eq('r.deleted', ':deleted'))
                ->setParameter('deleted', true, ParameterType::BOOLEAN);
        }

        /** @var array<int, int> $result */
        $result = $qb->fetchAllKeyValue();

        return $result;
    }

    /**
     * @return Environment[]
     */
    public function getByIds(string ...$ids): array
    {
        $queryBuilder = $this->createQueryBuilder('environment');
        $queryBuilder
            ->andWhere('environment.id IN (:ids)')
            ->setParameter('ids', $ids, ArrayParameterType::STRING);

        return $queryBuilder->getQuery()->getResult();
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
                'circles' => $record['circles'] ?? [],
            ];
        }

        return $out;
    }

    public function delete(Environment $environment): void
    {
        $this->getEntityManager()->remove($environment);
        $this->getEntityManager()->flush();
    }

    public function getById(string $id): Environment
    {
        if (null === $environment = $this->find($id)) {
            throw new \RuntimeException('Unexpected environment type');
        }

        return $environment;
    }

    /**
     * @return ReadableCollection<int, Environment>
     */
    public function findAllPublishedForRevision(Revision $revision): ReadableCollection
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

    /**
     * @return ArrayCollection<int, int>
     */
    public function findDefaultEnvironmentIds(): ArrayCollection
    {
        $qb = $this->createQueryBuilder('e');
        $qb
            ->select('DISTINCT e.id')
            ->join('e.contentTypesHavingThisAsDefault', 'c');

        return new ArrayCollection($qb->getQuery()->getSingleColumnResult());
    }

    public function save(Environment $environment): void
    {
        $this->getEntityManager()->persist($environment);
        $this->getEntityManager()->flush();
    }

    public function makeQueryBuilder(?bool $isManaged = false, string $searchValue = ''): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e');

        if (null !== $isManaged) {
            $qb->andWhere($qb->expr()->eq('e.managed', $qb->expr()->literal($isManaged)));
        }

        if ('' !== $searchValue) {
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('LOWER(e.name)', ':term'),
                    $qb->expr()->like('LOWER(e.label)', ':term'),
                ))
                ->setParameter(':term', '%'.\strtolower($searchValue).'%');
        }

        return $qb;
    }

    /**
     * @param string[] $ouuids
     *
     * @return array<int|string, mixed>
     */
    public function findAllRevisionIdsByEnvironmentAndOuuids(Environment $environment, array $ouuids): array
    {
        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb
            ->select('r.ouuid, r.id')
            ->from('environment_revision', 'er')
            ->join('er', 'revision', 'r', 'er.revision_id = r.id')
            ->join('er', 'environment', 'e', 'er.environment_id = e.id')
            ->andWhere($qb->expr()->eq('e.id', ':environment_id'))
            ->andWhere($qb->expr()->in('r.ouuid', ':ouuids'))
            ->setParameter('environment_id', $environment->getId())
            ->setParameter('ouuids', $ouuids, ArrayParameterType::STRING);

        return $qb->executeQuery()->fetchAllKeyValue();
    }
}
