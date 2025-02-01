<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\Dashboard;

/**
 * @extends ServiceEntityRepository<Dashboard>
 *
 * @method Dashboard|null find($id, $lockMode = null, $lockVersion = null)
 * @method Dashboard|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method Dashboard[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
final class DashboardRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Dashboard::class);
    }

    /**
     * @return Dashboard[]
     */
    public function getAll(): array
    {
        return $this->findBy([], ['orderKey' => 'ASC']);
    }

    /**
     * @return Dashboard[]
     */
    public function getSidebarMenu(): array
    {
        return $this->findBy([
            'sidebarMenu' => true,
        ], [
            'orderKey' => 'ASC',
        ]);
    }

    /**
     * @return Dashboard[]
     */
    public function getNotificationMenu(): array
    {
        return $this->findBy([
            'notificationMenu' => true,
        ], [
            'orderKey' => 'ASC',
        ]);
    }

    public function counter(string $searchValue = ''): int
    {
        $qb = $this->createQueryBuilder('c');
        $qb->select('count(c.id)');
        $this->addSearchFilters($qb, $searchValue);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function create(Dashboard $dashboard): void
    {
        $this->getEntityManager()->persist($dashboard);
        $this->getEntityManager()->flush();
    }

    public function delete(Dashboard $dashboard): void
    {
        $this->getEntityManager()->remove($dashboard);
        $this->getEntityManager()->flush();
    }

    /**
     * @param string[] $ids
     *
     * @return Dashboard[]
     */
    public function getByIds(array $ids): array
    {
        $queryBuilder = $this->createQueryBuilder('dashboard');
        $queryBuilder->where('dashboard.id IN (:ids)')
            ->setParameter('ids', $ids, ArrayParameterType::STRING);

        return $queryBuilder->getQuery()->getResult();
    }

    public function getById(string $id): Dashboard
    {
        if (null === $dashboard = $this->find($id)) {
            throw new \RuntimeException('Unexpected dashboard type');
        }

        return $dashboard;
    }

    /**
     * @return Dashboard[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('c')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['label', 'name'])) {
            $qb->orderBy(\sprintf('c.%s', $orderField), $orderDirection);
        } else {
            $qb->orderBy('c.orderKey', $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        if (\strlen($searchValue) > 0) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('c.label', ':term'),
                $qb->expr()->like('c.name', ':term'),
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }

    public function getByName(string $name): ?Dashboard
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * @return Collection<string, Dashboard>
     */
    public function getDefinitions(): Collection
    {
        $qb = $this->createQueryBuilder('d', 'd.definition');
        $qb->andWhere($qb->expr()->isNotNull('d.definition'));

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    public function getDefinition(string $definition): ?Dashboard
    {
        return $this->findOneBy(['definition' => $definition]);
    }
}
