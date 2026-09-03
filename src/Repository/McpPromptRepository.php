<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\McpPrompt;

/**
 * @extends ServiceEntityRepository<McpPrompt>
 *
 * @method McpPrompt|null find($id, $lockMode = null, $lockVersion = null)
 * @method McpPrompt|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method McpPrompt[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
final class McpPromptRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, McpPrompt::class);
    }

    public function create(McpPrompt $mcpPrompt): void
    {
        $this->getEntityManager()->persist($mcpPrompt);
        $this->getEntityManager()->flush();
    }

    public function delete(McpPrompt $mcpPrompt): void
    {
        $this->getEntityManager()->remove($mcpPrompt);
        $this->getEntityManager()->flush();
    }

    /**
     * @return McpPrompt[]
     */
    public function getAll(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }

    public function counter(string $searchValue = ''): int
    {
        $qb = $this->createQueryBuilder('mcpPrompt');
        $qb->select('count(mcpPrompt.id)');
        $this->addSearchFilters($qb, $searchValue);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param string[] $ids
     *
     * @return McpPrompt[]
     */
    public function getByIds(array $ids): array
    {
        $queryBuilder = $this->createQueryBuilder('mcpPrompt');
        $queryBuilder->where('mcpPrompt.id IN (:ids)')
            ->setParameter('ids', $ids, ArrayParameterType::STRING);

        return $queryBuilder->getQuery()->getResult();
    }

    public function getById(string $id): McpPrompt
    {
        if (null === $mcpPrompt = $this->find($id)) {
            throw new \RuntimeException('Unexpected mcp prompt type');
        }

        return $mcpPrompt;
    }

    /**
     * @return McpPrompt[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('mcpPrompt')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['label', 'name', 'enabled'], true)) {
            $qb->orderBy(\sprintf('mcpPrompt.%s', $orderField), $orderDirection);
        } else {
            $qb->orderBy('mcpPrompt.name', $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    /**
     * @return McpPrompt[]
     */
    public function findEnabled(): array
    {
        return $this->findBy(['enabled' => true], ['name' => 'ASC']);
    }

    public function getByName(string $name): ?McpPrompt
    {
        return $this->findOneBy(['name' => $name]);
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        if ('' !== $searchValue) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('mcpPrompt.label', ':term'),
                $qb->expr()->like('mcpPrompt.name', ':term'),
                $qb->expr()->like('mcpPrompt.description', ':term')
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }
}
