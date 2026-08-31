<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\McpTool;

/**
 * @extends ServiceEntityRepository<McpTool>
 *
 * @method McpTool|null find($id, $lockMode = null, $lockVersion = null)
 * @method McpTool|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method McpTool[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
final class McpToolRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, McpTool::class);
    }

    public function create(McpTool $mcpTool): void
    {
        $this->getEntityManager()->persist($mcpTool);
        $this->getEntityManager()->flush();
    }

    public function delete(McpTool $mcpTool): void
    {
        $this->getEntityManager()->remove($mcpTool);
        $this->getEntityManager()->flush();
    }

    /**
     * @return McpTool[]
     */
    public function getAll(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }

    public function counter(string $searchValue = ''): int
    {
        $qb = $this->createQueryBuilder('mcpTool');
        $qb->select('count(mcpTool.id)');
        $this->addSearchFilters($qb, $searchValue);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param string[] $ids
     *
     * @return McpTool[]
     */
    public function getByIds(array $ids): array
    {
        $queryBuilder = $this->createQueryBuilder('mcpTool');
        $queryBuilder->where('mcpTool.id IN (:ids)')
            ->setParameter('ids', $ids, ArrayParameterType::STRING);

        return $queryBuilder->getQuery()->getResult();
    }

    public function getById(string $id): McpTool
    {
        if (null === $mcpTool = $this->find($id)) {
            throw new \RuntimeException('Unexpected mcp tool type');
        }

        return $mcpTool;
    }

    /**
     * @return McpTool[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('mcpTool')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['label', 'name', 'enabled'], true)) {
            $qb->orderBy(\sprintf('mcpTool.%s', $orderField), $orderDirection);
        } else {
            $qb->orderBy('mcpTool.name', $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    /**
     * @return McpTool[]
     */
    public function findEnabled(): array
    {
        return $this->findBy(['enabled' => true], ['name' => 'ASC']);
    }

    public function getByName(string $name): ?McpTool
    {
        return $this->findOneBy(['name' => $name]);
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        if ('' !== $searchValue) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('mcpTool.label', ':term'),
                $qb->expr()->like('mcpTool.name', ':term'),
                $qb->expr()->like('mcpTool.description', ':term')
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }
}
