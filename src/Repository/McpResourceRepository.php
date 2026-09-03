<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\McpResource;

/**
 * @extends ServiceEntityRepository<McpResource>
 *
 * @method McpResource|null find($id, $lockMode = null, $lockVersion = null)
 * @method McpResource|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method McpResource[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
final class McpResourceRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, McpResource::class);
    }

    public function create(McpResource $mcpResource): void
    {
        $this->getEntityManager()->persist($mcpResource);
        $this->getEntityManager()->flush();
    }

    public function delete(McpResource $mcpResource): void
    {
        $this->getEntityManager()->remove($mcpResource);
        $this->getEntityManager()->flush();
    }

    /**
     * @return McpResource[]
     */
    public function getAll(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }

    public function counter(string $searchValue = ''): int
    {
        $qb = $this->createQueryBuilder('mcpResource');
        $qb->select('count(mcpResource.id)');
        $this->addSearchFilters($qb, $searchValue);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param string[] $ids
     *
     * @return McpResource[]
     */
    public function getByIds(array $ids): array
    {
        $queryBuilder = $this->createQueryBuilder('mcpResource');
        $queryBuilder->where('mcpResource.id IN (:ids)')
            ->setParameter('ids', $ids, ArrayParameterType::STRING);

        return $queryBuilder->getQuery()->getResult();
    }

    public function getById(string $id): McpResource
    {
        if (null === $mcpResource = $this->find($id)) {
            throw new \RuntimeException('Unexpected mcp resource type');
        }

        return $mcpResource;
    }

    /**
     * @return McpResource[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('mcpResource')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['label', 'name', 'uri', 'enabled'], true)) {
            $qb->orderBy(\sprintf('mcpResource.%s', $orderField), $orderDirection);
        } else {
            $qb->orderBy('mcpResource.name', $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    /**
     * @return McpResource[]
     */
    public function findEnabled(): array
    {
        return $this->findBy(['enabled' => true], ['name' => 'ASC']);
    }

    public function getByName(string $name): ?McpResource
    {
        return $this->findOneBy(['name' => $name]);
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        if ('' !== $searchValue) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('mcpResource.label', ':term'),
                $qb->expr()->like('mcpResource.name', ':term'),
                $qb->expr()->like('mcpResource.uri', ':term'),
                $qb->expr()->like('mcpResource.description', ':term')
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }
}
