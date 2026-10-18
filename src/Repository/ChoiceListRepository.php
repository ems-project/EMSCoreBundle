<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use EMS\CoreBundle\Entity\ChoiceList;

/**
 * @extends ServiceEntityRepository<ChoiceList>
 *
 * @method ChoiceList|null find($id, $lockMode = null, $lockVersion = null)
 * @method ChoiceList|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method ChoiceList[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
class ChoiceListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChoiceList::class);
    }

    /**
     * @return ChoiceList[]
     */
    public function getAll(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }

    public function counter(string $searchValue = ''): int
    {
        $qb = $this->createQueryBuilder('c');
        $qb->select('count(c.id)');
        $this->addSearchFilters($qb, $searchValue);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return ChoiceList[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('c')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);
        $qb->orderBy(\sprintf('c.%s', \in_array($orderField, ['label', 'name'], true) ? $orderField : 'name'), $orderDirection);

        return $qb->getQuery()->execute();
    }

    public function create(ChoiceList $choiceList): void
    {
        $this->getEntityManager()->persist($choiceList);
        $this->getEntityManager()->flush();
    }

    public function update(ChoiceList $choiceList): void
    {
        $this->getEntityManager()->persist($choiceList);
        $this->getEntityManager()->flush();
    }

    public function delete(ChoiceList $choiceList): void
    {
        $this->getEntityManager()->remove($choiceList);
        $this->getEntityManager()->flush();
    }

    /**
     * @param string[] $ids
     *
     * @return ChoiceList[]
     */
    public function getByIds(array $ids): array
    {
        return $this->createQueryBuilder('choiceList')
            ->where('choiceList.id IN (:ids)')
            ->setParameter('ids', $ids, ArrayParameterType::STRING)
            ->getQuery()
            ->getResult();
    }

    public function getById(string $id): ChoiceList
    {
        if (null === $choiceList = $this->find($id)) {
            throw new \RuntimeException('Choice list not found');
        }

        return $choiceList;
    }

    public function getByName(string $name): ?ChoiceList
    {
        return $this->findOneBy(['name' => $name]);
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        if ('' !== $searchValue) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('c.label', ':term'),
                $qb->expr()->like('c.name', ':term'),
            ))->setParameter(':term', '%'.$searchValue.'%');
        }
    }
}
